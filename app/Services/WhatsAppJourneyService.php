<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\Otp;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WhatsAppJourneyService
{
    public function handle(string $phone, string $body, ?string $providerMessageId = null): array
    {
        $conversation = $this->conversation($phone);

        if ($providerMessageId && DB::table('whatsapp_messages')->where('provider_message_id', $providerMessageId)->exists()) {
            return ['reply' => 'Message already processed.', 'state' => $conversation->state, 'duplicate' => true];
        }

        $this->recordMessage($conversation->id, 'inbound', $body, $providerMessageId);
        $normalized = trim($body);

        if (preg_match('/^VERIFY\s+(\d{6})$/i', $normalized, $matches)) {
            return $this->verify($conversation, $matches[1]);
        }

        if ($conversation->state !== 'verified' || ! $conversation->verified_at || now()->greaterThan($conversation->expires_at)) {
            $reply = 'For your security, verify this WhatsApp session first. Request an OpFin OTP in the app, then send VERIFY followed by the 6-digit code.';
            $this->recordMessage($conversation->id, 'outbound', $reply);

            return ['reply' => $reply, 'state' => 'verification_required'];
        }

        $user = User::find($conversation->user_id);
        if (! $user) {
            return $this->respond($conversation->id, 'We could not match this session to an active OpFin account.', 'blocked');
        }

        if (preg_match('/^(HELP|MENU)$/i', $normalized)) {
            return $this->respond($conversation->id,
                "You can securely use: STATUS, KYC, CONSENTS, GRANT CREDIT CONSENT, REVOKE CREDIT CONSENT, SUPPORT <message>, and LOGOUT. Money movement, offer acceptance and other high-impact actions require step-up confirmation in OpFin.", 'verified');
        }

        if (strcasecmp($normalized, 'STATUS') === 0) {
            $kyc = DB::table('kyc_cases')->where('user_id', $user->id)->latest('id')->value('status') ?? 'not_started';
            $openSupport = DB::table('support_cases')->where('customer_id', $user->id)->whereNotIn('status', ['resolved', 'closed'])->count();
            return $this->respond($conversation->id, "OpFin status: KYC {$kyc}. Open support cases: {$openSupport}.", 'verified');
        }

        if (strcasecmp($normalized, 'KYC') === 0) {
            $kyc = DB::table('kyc_cases')->where('user_id', $user->id)->latest('id')->first();
            $reply = $kyc ? "Your identity verification status is {$kyc->status}." : 'You have not started identity verification.';
            return $this->respond($conversation->id, $reply, 'verified');
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
                'state' => 'unverified', 'verified_at' => null, 'expires_at' => null, 'session_nonce' => null, 'updated_at' => now(),
            ]);
            return $this->respond($conversation->id, 'WhatsApp session closed.', 'unverified');
        }

        if (preg_match('/\b(PAY|REPAY|ACCEPT|WITHDRAW|INVEST|TRANSFER|DISBURSE)\b/i', $normalized)) {
            return $this->respond($conversation->id,
                'This action changes money or a regulated financial commitment. For your protection, OpFin requires step-up confirmation in the authenticated app before it can be completed.', 'step_up_required');
        }

        return $this->respond($conversation->id, 'I did not recognise that command. Send MENU to see secure WhatsApp journeys.', 'verified');
    }

    private function verify(object $conversation, string $otp): array
    {
        $user = User::where('phone', $conversation->wa_phone)->first();
        $record = Otp::where('phone', $conversation->wa_phone)->first();
        $valid = $record && now()->lte($record->expires_at) && $record->attempts < 3 && $this->otpMatches((string) $record->otp, $otp);

        if (! $valid) {
            if ($record && $record->attempts < 3) {
                $record->increment('attempts');
            }
            return $this->respond($conversation->id, 'Verification failed or expired. Request a new OpFin OTP.', 'verification_required');
        }

        $record->delete();
        $nonce = hash('sha256', random_bytes(32));
        DB::table('whatsapp_conversations')->where('id', $conversation->id)->update([
            'user_id' => $user?->id,
            'state' => $user ? 'verified' : 'blocked',
            'session_nonce' => $nonce,
            'verified_at' => now(),
            'expires_at' => now()->addMinutes(15),
            'updated_at' => now(),
        ]);

        return $this->respond($conversation->id,
            $user ? 'WhatsApp session verified for 15 minutes. Send MENU to continue.' : 'No active OpFin account matches this number.',
            $user ? 'verified' : 'blocked');
    }

    private function conversation(string $phone): object
    {
        DB::table('whatsapp_conversations')->updateOrInsert(['wa_phone' => $phone], [
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        return DB::table('whatsapp_conversations')->where('wa_phone', $phone)->first();
    }

    private function respond(int $conversationId, string $reply, string $state): array
    {
        $this->recordMessage($conversationId, 'outbound', $reply);
        return ['reply' => $reply, 'state' => $state];
    }

    private function recordMessage(int $conversationId, string $direction, string $body, ?string $providerMessageId = null): void
    {
        $canonical = json_encode(['conversation_id' => $conversationId, 'direction' => $direction, 'body' => $body, 'provider_message_id' => $providerMessageId], JSON_UNESCAPED_SLASHES);
        DB::table('whatsapp_messages')->insert([
            'conversation_id' => $conversationId,
            'provider_message_id' => $providerMessageId,
            'direction' => $direction,
            'message_type' => 'text',
            'body' => $body,
            'payload' => null,
            'payload_hash' => hash('sha256', $canonical),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function otpMatches(string $stored, string $provided): bool
    {
        return str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon')
            ? Hash::check($provided, $stored)
            : hash_equals($stored, $provided);
    }
}
