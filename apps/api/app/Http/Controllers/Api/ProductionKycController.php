<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Models\KycCase;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CustomerCreditProfileService;
use App\Services\IdentityVerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductionKycController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly IdentityVerificationService $identityVerification,
        private readonly CustomerCreditProfileService $profiles,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $case = KycCase::where('user_id', $request->user()->id)->latest()->first();

        return ApiResponse::success('KYC status loaded.', [
            'latest_case' => $case ? [
                'id' => $case->id,
                'status' => $case->status,
                'submitted_at' => $case->submitted_at,
                'reviewed_at' => $case->reviewed_at,
                'expires_at' => $case->expires_at,
                'evidence_complete' => $case->evidence_complete_at !== null,
                'checks' => [
                    'liveness' => $case->liveness_status,
                    'face_match' => $case->face_match_status,
                    'nin_phone_link' => $case->nin_phone_link_status,
                ],
            ] : null,
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'national_id' => ['required', 'string', 'size:14', 'regex:/^[A-Za-z0-9]+$/'],
            'national_id_front' => 'required|file|image|mimes:jpeg,jpg,png,webp|max:8192',
            'national_id_back' => 'required|file|image|mimes:jpeg,jpg,png,webp|max:8192',
            'selfie_with_id' => 'required|file|image|mimes:jpeg,jpg,png,webp|max:8192',
            'capture_channel' => ['nullable', Rule::in(['app', 'whatsapp', 'mobile_web'])],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Please provide your NIN, both sides of your National ID, and a photo of you holding the ID.', 422, $validator->errors()->toArray());
        }

        $user = $request->user();
        $directory = 'kyc/'.$user->id;
        $disk = (string) config('services.identity_verification.disk', 'local');
        $front = $request->file('national_id_front')->store($directory, $disk);
        $back = $request->file('national_id_back')->store($directory, $disk);
        $selfie = $request->file('selfie_with_id')->store($directory, $disk);

        $case = KycCase::create([
            'user_id' => $user->id,
            'provider' => 'pending',
            'national_id' => strtoupper((string) $request->input('national_id')),
            'national_id_front_path' => $front,
            'national_id_back_path' => $back,
            'selfie_with_id_path' => $selfie,
            'status' => KycCase::STATUS_PENDING_REVIEW,
            'evidence' => [
                'national_id_front' => true,
                'national_id_back' => true,
                'selfie_with_id' => true,
                'capture_channel' => $request->input('capture_channel', 'app'),
            ],
            'submitted_at' => now(),
            'evidence_complete_at' => now(),
        ]);

        $case->update([
            'evidence' => array_merge($case->evidence ?? [], [
                'identity_verification_requested' => true,
                'identity_verification_purpose' => 'identity_verification',
                'identity_verification_reference' => 'kyc-case:'.$case->id,
            ]),
        ]);

        $this->auditLogger->record('kyc.submitted', $user, $case, [
            'evidence_complete' => true,
            'capture_channel' => $request->input('capture_channel', 'app'),
            'identity_verification_reference' => 'kyc-case:'.$case->id,
        ], $request);

        $case = $this->identityVerification->verify($case);

        if ($case->status === KycCase::STATUS_VERIFIED) {
            $user->forceFill([
                'national_id' => $case->national_id,
                'nin_status' => 'VALID',
                'validated_at' => now(),
            ])->save();

            $hasConsent = ConsentRecord::query()
                ->where('user_id', $user->id)
                ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
                ->where('status', ConsentRecord::STATUS_GRANTED)
                ->exists();

            if ($hasConsent) {
                $this->profiles->refresh($user->fresh(), true);
            }
        }

        return ApiResponse::success(
            $case->status === KycCase::STATUS_VERIFIED
                ? 'Identity verified.'
                : 'Identity evidence received and queued for verification.',
            [
                'kyc_case' => [
                    'id' => $case->id,
                    'status' => $case->status,
                    'evidence_complete' => true,
                    'checks' => [
                        'liveness' => $case->liveness_status,
                        'face_match' => $case->face_match_status,
                        'nin_phone_link' => $case->nin_phone_link_status,
                    ],
                ],
            ],
            201,
        );
    }

    public function review(KycCase $case, Request $request): JsonResponse
    {
        if (! $request->user()->hasAnyRole([User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS, User::ROLE_SUPPORT])) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in([KycCase::STATUS_VERIFIED, KycCase::STATUS_REJECTED])],
            'review_notes' => 'nullable|string|max:1000',
            'risk_flags' => 'nullable|array',
            'expires_at' => 'nullable|date|after:today',
            'liveness_status' => ['nullable', Rule::in(['valid', 'failed', 'manual_review'])],
            'face_match_status' => ['nullable', Rule::in(['valid', 'failed', 'manual_review'])],
            'nin_phone_link_status' => ['nullable', Rule::in(['valid', 'failed', 'manual_review'])],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        if ($request->input('status') === KycCase::STATUS_VERIFIED && $case->evidence_complete_at === null) {
            return ApiResponse::error('Front ID, back ID and selfie-with-ID evidence are required before verification.', 409);
        }

        $case->update([
            ...$validator->validated(),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'expires_at' => $request->input('status') === KycCase::STATUS_VERIFIED
                ? ($request->input('expires_at') ?? now()->addYear())
                : $request->input('expires_at'),
        ]);

        $case->user->update([
            'national_id' => $case->national_id,
            'nin_status' => $case->status === KycCase::STATUS_VERIFIED ? 'VALID' : 'REJECTED',
            'validated_at' => now(),
        ]);

        if ($case->status === KycCase::STATUS_VERIFIED) {
            $this->profiles->refresh($case->user->fresh(), true);
        }

        $this->auditLogger->record('kyc.reviewed', $request->user(), $case, ['status' => $case->status], $request);

        return ApiResponse::success('KYC case reviewed.', [
            'kyc_case' => [
                'id' => $case->id,
                'status' => $case->status,
                'reviewed_at' => $case->reviewed_at,
                'expires_at' => $case->expires_at,
            ],
        ]);
    }
}
