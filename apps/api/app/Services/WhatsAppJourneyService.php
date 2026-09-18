<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\KycCase;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppJourneyService
{
    private readonly SmsService $smsService;

    private readonly CustomerCreditProfileService $profiles;

    private readonly IdentityVerificationService $identityVerification;

    public function __construct(SmsService $smsService, CustomerCreditProfileService $profiles, IdentityVerificationService $identityVerification)
    {
        $this->smsService = $smsService;
        $this->profiles = $profiles;
        $this->identityVerification = $identityVerification;
    }

    public function handle(string $phone, string $body, ?string $providerMessageId = null): array
    {
        $conversation = $this->conversation($phone);

        if ($providerMessageId && DB::table('whatsapp_messages')->where('provider_message_id', $providerMessageId)->exists()) {
            return ['reply' => 'Message already processed.', 'state' => $conversation->state, 'duplicate' => true];
        }

        $this->recordMessage($conversation->id, 'inbound', $body, $providerMessageId);
        $normalized = trim($body);

        if (strcasecmp($normalized, 'START') === 0) {
            return $this->startVerification($conversation);
        }

        if (preg_match('/^VERIFY\s+(\d{6})$/i', $normalized, $matches)) {
            return $this->verify($conversation, $matches[1]);
        }

        if ($conversation->state !== 'verified' || $conversation->verified_at === null || $conversation->expires_at === null || now()->greaterThan($conversation->expires_at)) {
            return $this->respond(
                $conversation->id,
                'For your security, send START. OpFin will send a channel-specific verification code to your registered phone. Then send VERIFY followed by the 6-digit code.',
                'verification_required'
            );
        }

        $user = User::find($conversation->user_id);
        if ($user === null) {
            return $this->respond($conversation->id, 'We could not match this session to an active OpFin account.', 'blocked');
        }

        if (preg_match('/^(HELP|MENU)$/i', $normalized)) {
            return $this->respond(
                $conversation->id,
                'You can use: STATUS, LIMIT, PROFILE, KYC, BORROW <amount>, REPAY, CONSENTS, GRANT CREDIT CONSENT, REVOKE CREDIT CONSENT, SUPPORT <message>, and LOGOUT. We never ask you to send your OpFin PIN in WhatsApp.',
                'verified'
            );
        }

        if (strcasecmp($normalized, 'STATUS') === 0) {
            $kyc = DB::table('kyc_cases')->where('user_id', $user->id)->latest('id')->value('status') ?? 'not_started';
            $openSupport = DB::table('support_cases')->where('customer_id', $user->id)->whereNotIn('status', ['resolved', 'closed'])->count();

            return $this->respond($conversation->id, "OpFin status: KYC {$kyc}. Open support cases: {$openSupport}.", 'verified');
        }

        if (strcasecmp($normalized, 'LIMIT') === 0) {
            $state = $this->profiles->status($user);
            $profile = $state['profile'];
            if ($profile === null || $profile->status === 'pending') {
                return $this->respond($conversation->id, 'Your limit is not ready yet. Next step: '.$state['next_action']['label'].'.', 'verified');
            }

            return $this->respond(
                $conversation->id,
                'Your OpFin Score is '.round((float) $profile->composite_score).'/100. Available loan limit: UGX '.number_format((int) $profile->available_to_borrow_minor).'.',
                'verified'
            );
        }

        if (strcasecmp($normalized, 'PROFILE') === 0) {
            $state = $this->profiles->status($user);
            return $this->respond($conversation->id, 'Next step: '.$state['next_action']['label'].'. A second phone is optional.', 'verified');
        }

        if (preg_match('/^BORROW(?:\\s+(\\d+))?$/i', $normalized, $matches)) {
            $state = $this->profiles->status($user);
            $profile = $state['profile'];
            if ($profile === null || $profile->available_to_borrow_minor <= 0) {
                return $this->respond($conversation->id, 'There is no amount available to borrow right now. Next step: '.$state['next_action']['label'].'.', 'verified');
            }
            $amount = isset($matches[1]) ? (int) $matches[1] : 0;
            if ($amount > 0 && $amount > $profile->available_to_borrow_minor) {
                return $this->respond($conversation->id, 'Choose an amount up to UGX '.number_format((int) $profile->available_to_borrow_minor).'.', 'verified');
            }
            $web = rtrim((string) config('services.opfin.web_url'), '/');
            $link = $web !== '' ? $web.'/loans/apply'.($amount > 0 ? '?amount='.$amount.'&source=whatsapp' : '?source=whatsapp') : null;
            return $this->respond(
                $conversation->id,
                'Available: UGX '.number_format((int) $profile->available_to_borrow_minor).'. '.($link ? 'Continue securely: '.$link : 'Continue in the OpFin app to review costs and confirm.'),
                'step_up_required'
            );
        }

        if (strcasecmp($normalized, 'REPAY') === 0) {
            $state = $this->profiles->status($user);
            $profile = $state['profile'];
            if ($profile === null || $profile->total_outstanding_minor <= 0) {
                return $this->respond($conversation->id, 'You have no outstanding OpFin loan.', 'verified');
            }
            $web = rtrim((string) config('services.opfin.web_url'), '/');
            return $this->respond(
                $conversation->id,
                'Outstanding: UGX '.number_format((int) $profile->total_outstanding_minor).'. Amount due now: UGX '.number_format((int) $profile->amount_due_minor).'. '.($web !== '' ? 'Continue securely: '.$web.'/loans/account?source=whatsapp' : 'Continue in the OpFin app to repay.'),
                'step_up_required'
            );
        }

        if (strcasecmp($normalized, 'KYC') === 0) {
            $kyc = KycCase::where('user_id', $user->id)->latest('id')->first();
            if ($kyc?->status === KycCase::STATUS_VERIFIED) {
                return $this->respond($conversation->id, 'Your identity is verified.', 'verified');
            }

            return $this->respond(
                $conversation->id,
                'To verify your identity here, send NIN followed by your 14-character NIN. Example: NIN CM123456789012. Then I will ask for the front of your ID, the back, and a photo of you holding it.',
                'verified'
            );
        }

        if (preg_match('/^NIN\s+([A-Z0-9]{14})$/i', $normalized, $matches)) {
            $case = KycCase::create([
                'user_id' => $user->id,
                'provider' => 'whatsapp',
                'national_id' => strtoupper($matches[1]),
                'status' => KycCase::STATUS_PENDING_REVIEW,
                'evidence' => ['capture_channel' => 'whatsapp'],
                'submitted_at' => now(),
            ]);

            DB::table('whatsapp_conversations')->where('id', $conversation->id)->update([
                'journey' => 'kyc',
                'context' => json_encode([
                    'kyc_case_id' => $case->id,
                    'kyc_step' => 'front',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return $this->respond(
                $conversation->id,
                'Step 1 of 3: send a clear photo of the FRONT of your National ID. Keep the whole card visible.',
                'verified'
            );
        }

        if (strcasecmp($normalized, 'CONSENTS') === 0) {
            $consents = ConsentRecord::where('user_id', $user->id)->where('status', ConsentRecord::STATUS_GRANTED)->pluck('purpose')->all();

            return $this->respond($conversation->id, $consents ? 'Active permissions: '.implode(', ', $consents).'.' : 'You currently have no active permissions.', 'verified');
        }

        if (strcasecmp($normalized, 'GRANT CREDIT CONSENT') === 0) {
            ConsentRecord::updateOrCreate([
                'user_id' => $user->id,
                'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
                'status' => ConsentRecord::STATUS_GRANTED,
            ], [
                'policy_version' => '2026-08-31',
                'channel' => 'whatsapp',
                'granted_at' => now(),
                'revoked_at' => null,
                'metadata' => ['conversation_id' => $conversation->id, 'explicit_phrase' => true],
            ]);

            return $this->respond($conversation->id, 'Credit-processing consent recorded. You may revoke it at any time by sending REVOKE CREDIT CONSENT.', 'verified');
        }

        if (strcasecmp($normalized, 'REVOKE CREDIT CONSENT') === 0) {
            ConsentRecord::where('user_id', $user->id)
                ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
                ->where('status', ConsentRecord::STATUS_GRANTED)
                ->update(['status' => ConsentRecord::STATUS_REVOKED, 'revoked_at' => now()]);

            return $this->respond($conversation->id, 'Credit-processing consent revoked.', 'verified');
        }

        if (preg_match('/^SUPPORT\s+(.{3,2000})$/is', $normalized, $matches)) {
            $case = SupportCase::create([
                'customer_id' => $user->id,
                'case_number' => 'WA-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'category' => 'whatsapp',
                'priority' => 'normal',
                'subject' => 'WhatsApp support request',
                'description' => trim($matches[1]),
                'created_by' => $user->id,
                'status' => SupportCase::STATUS_OPEN,
            ]);

            return $this->respond($conversation->id, "Support case {$case->case_number} has been created and is fully auditable.", 'verified');
        }

        if (strcasecmp($normalized, 'LOGOUT') === 0) {
            DB::table('whatsapp_conversations')->where('id', $conversation->id)->update([
                'state' => 'unverified',
                'verified_at' => null,
                'expires_at' => null,
                'session_nonce' => null,
                'updated_at' => now(),
            ]);

            return $this->respond($conversation->id, 'WhatsApp session closed.', 'unverified');
        }

        if (preg_match('/\b(PAY|REPAY|ACCEPT|WITHDRAW|INVEST|TRANSFER|DISBURSE)\b/i', $normalized)) {
            return $this->respond(
                $conversation->id,
                'This action changes money or a regulated financial commitment. For your protection, OpFin requires step-up confirmation in the authenticated app before it can be completed.',
                'step_up_required'
            );
        }

        return $this->respond($conversation->id, 'I did not recognise that command. Send MENU to see secure WhatsApp journeys.', 'verified');
    }

    public function handleImage(string $phone, string $bytes, string $mimeType, ?string $providerMessageId = null): array
    {
        $conversation = $this->conversation($phone);

        if ($providerMessageId && DB::table('whatsapp_messages')->where('provider_message_id', $providerMessageId)->exists()) {
            return ['reply' => 'Photo already processed.', 'state' => $conversation->state, 'duplicate' => true];
        }

        $this->recordMessage($conversation->id, 'inbound', '[image received]', $providerMessageId);

        if ($conversation->state !== 'verified'
            || $conversation->verified_at === null
            || $conversation->expires_at === null
            || now()->greaterThan($conversation->expires_at)) {
            return $this->respond(
                $conversation->id,
                'For your security, send START and verify your WhatsApp session before sending identity photos.',
                'verification_required'
            );
        }

        if ($conversation->journey !== 'kyc') {
            return $this->respond(
                $conversation->id,
                'I was not expecting a photo. Send KYC to start identity verification.',
                'verified'
            );
        }

        $context = is_array($conversation->context)
            ? $conversation->context
            : (json_decode((string) $conversation->context, true) ?: []);
        $case = KycCase::query()
            ->whereKey($context['kyc_case_id'] ?? null)
            ->where('user_id', $conversation->user_id)
            ->first();

        if ($case === null) {
            return $this->respond($conversation->id, 'This identity session expired. Send KYC to start again.', 'verified');
        }

        if (str_starts_with(strtolower($mimeType), 'image/') === false) {
            return $this->respond(
                $conversation->id,
                'Please send a photo image, not a document or video.',
                'verified'
            );
        }

        $step = (string) ($context['kyc_step'] ?? 'front');
        $extension = str_contains(strtolower($mimeType), 'png') ? 'png' : 'jpg';
        $disk = (string) config('services.identity_verification.disk', 'local');
        $path = 'kyc/'.$case->user_id.'/whatsapp/'.$case->id.'/'.$step.'-'.Str::uuid().'.'.$extension;
        Storage::disk($disk)->put($path, $bytes);

        $evidence = array_merge($case->evidence ?? [], [$step => true, 'capture_channel' => 'whatsapp']);

        if ($step === 'front') {
            $case->update(['national_id_front_path' => $path, 'evidence' => $evidence]);
            $this->setKycContext($conversation->id, $case->id, 'back');

            return $this->respond(
                $conversation->id,
                'Step 2 of 3: send a clear photo of the BACK of your National ID.',
                'verified'
            );
        }

        if ($step === 'back') {
            $case->update(['national_id_back_path' => $path, 'evidence' => $evidence]);
            $this->setKycContext($conversation->id, $case->id, 'selfie');

            return $this->respond(
                $conversation->id,
                'Step 3 of 3: send a photo of YOU HOLDING the National ID. Keep your face and the ID visible.',
                'verified'
            );
        }

        if ($step === 'selfie') {
            $case->update([
                'selfie_with_id_path' => $path,
                'evidence' => $evidence,
                'evidence_complete_at' => now(),
            ]);
            DB::table('whatsapp_conversations')->where('id', $conversation->id)->update([
                'journey' => null,
                'context' => null,
                'updated_at' => now(),
            ]);

            $case = $this->identityVerification->verify($case);
            $user = User::find($conversation->user_id);
            if ($case->status === KycCase::STATUS_VERIFIED && $user) {
                $user->forceFill([
                    'national_id' => $case->national_id,
                    'nin_status' => 'VALID',
                    'validated_at' => now(),
                ])->save();

                $consent = ConsentRecord::where('user_id', $user->id)
                    ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
                    ->where('status', ConsentRecord::STATUS_GRANTED)
                    ->exists();
                if ($consent) {
                    $this->profiles->refresh($user->fresh(), true);
                }

                return $this->respond(
                    $conversation->id,
                    'Your identity is verified. Send GRANT CREDIT CONSENT if you have not yet allowed the credit check, or send LIMIT to see your current profile.',
                    'verified'
                );
            }

            return $this->respond(
                $conversation->id,
                'We received all three photos. Your identity check is pending review. You can send KYC later to check the status.',
                'verified'
            );
        }

        return $this->respond($conversation->id, 'Send KYC to restart identity verification.', 'verified');
    }

    private function setKycContext(int $conversationId, int $caseId, string $step): void
    {
        DB::table('whatsapp_conversations')->where('id', $conversationId)->update([
            'journey' => 'kyc',
            'context' => json_encode([
                'kyc_case_id' => $caseId,
                'kyc_step' => $step,
            ], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    private function startVerification(object $conversation): array
    {
        $user = User::where('phone', $conversation->wa_phone)->first();
        if ($user === null) {
            $web = rtrim((string) config('services.opfin.web_url'), '/');
            $message = $web !== ''
                ? 'This number does not have an OpFin account yet. Create one securely at '.$web.'/signup?source=whatsapp. OpFin will verify your phone there and will never ask you to send a PIN in WhatsApp.'
                : 'This number does not have an OpFin account yet. Open the OpFin app to create one. OpFin will never ask you to send a PIN in WhatsApp.';

            return $this->respond($conversation->id, $message, 'registration_required');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('whatsapp_conversations')->where('id', $conversation->id)->update([
            'user_id' => $user->id,
            'state' => 'challenge_sent',
            'challenge_hash' => Hash::make($code),
            'challenge_attempts' => 0,
            'challenge_expires_at' => now()->addMinutes(5),
            'verified_at' => null,
            'expires_at' => null,
            'updated_at' => now(),
        ]);
        $this->smsService->queueSms($user->phone, 'OpFin WhatsApp verification code: '.$code.'. It expires in 5 minutes.');

        return $this->respond($conversation->id, 'A WhatsApp verification code was sent to your registered phone. Send VERIFY followed by the 6-digit code.', 'challenge_sent');
    }

    private function verify(object $conversation, string $code): array
    {
        $conversation = DB::table('whatsapp_conversations')->find($conversation->id);
        $valid = $conversation
            && $conversation->challenge_hash
            && $conversation->challenge_expires_at
            && now()->lte($conversation->challenge_expires_at)
            && $conversation->challenge_attempts < 3
            && Hash::check($code, $conversation->challenge_hash);

        if ($valid === false) {
            if ($conversation && $conversation->challenge_attempts < 3) {
                DB::table('whatsapp_conversations')->where('id', $conversation->id)->increment('challenge_attempts');
            }

            return $this->respond($conversation->id, 'Verification failed or expired. Send START to request a new WhatsApp verification code.', 'verification_required');
        }

        $nonce = hash('sha256', random_bytes(32));
        DB::table('whatsapp_conversations')->where('id', $conversation->id)->update([
            'state' => 'verified',
            'session_nonce' => $nonce,
            'challenge_hash' => null,
            'challenge_attempts' => 0,
            'challenge_expires_at' => null,
            'verified_at' => now(),
            'expires_at' => now()->addMinutes(15),
            'updated_at' => now(),
        ]);

        return $this->respond($conversation->id, 'WhatsApp session verified for 15 minutes. Send MENU to continue.', 'verified');
    }

    private function conversation(string $phone): object
    {
        $existing = DB::table('whatsapp_conversations')->where('wa_phone', $phone)->first();
        if ($existing) {
            return $existing;
        }

        $id = DB::table('whatsapp_conversations')->insertGetId([
            'wa_phone' => $phone,
            'state' => 'unverified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('whatsapp_conversations')->find($id);
    }

    private function respond(int $conversationId, string $reply, string $state): array
    {
        $this->recordMessage($conversationId, 'outbound', $reply);

        return ['reply' => $reply, 'state' => $state];
    }

    private function recordMessage(int $conversationId, string $direction, string $body, ?string $providerMessageId = null): void
    {
        $canonical = json_encode([
            'conversation_id' => $conversationId,
            'direction' => $direction,
            'body' => $body,
            'provider_message_id' => $providerMessageId,
        ], JSON_UNESCAPED_SLASHES);

        DB::table('whatsapp_messages')->insert([
            'conversation_id' => $conversationId,
            'provider_message_id' => $providerMessageId,
            'direction' => $direction,
            'message_type' => 'text',
            'body' => Crypt::encryptString($body),
            'payload' => null,
            'payload_hash' => hash('sha256', $canonical),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
