<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycCase;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductionKycController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success('KYC status loaded.', [
            'latest_case' => KycCase::where('user_id', $request->user()->id)->latest()->first(),
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'national_id' => 'required|string|max:64',
            'provider' => 'nullable|string|max:64',
            'provider_reference' => 'nullable|string|max:128',
            'evidence' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $case = KycCase::create([
            ...$validator->validated(),
            'user_id' => $request->user()->id,
            'status' => KycCase::STATUS_PENDING_REVIEW,
            'submitted_at' => now(),
        ]);

        $this->auditLogger->record('kyc.submitted', $request->user(), $case, [], $request);

        return ApiResponse::success('KYC case submitted for review.', ['kyc_case' => $case], 201);
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
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $case->update([
            ...$validator->validated(),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $case->user->update(['nin_status' => $case->status === KycCase::STATUS_VERIFIED ? 'VALID' : 'REJECTED']);
        $this->auditLogger->record('kyc.reviewed', $request->user(), $case, ['status' => $case->status], $request);

        return ApiResponse::success('KYC case reviewed.', ['kyc_case' => $case->fresh()]);
    }
}
