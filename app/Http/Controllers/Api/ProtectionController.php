<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProtectionClaim;
use App\Models\ProtectionPolicy;
use App\Models\ProtectionProduct;
use App\Services\ProtectionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class ProtectionController extends Controller
{
    public function __construct(private readonly ProtectionService $protection) {}

    public function products(Request $request): JsonResponse
    {
        $country = strtoupper((string) $request->query('country', config('opfin.default_country', 'UG')));

        return ApiResponse::success('Protection products loaded.', [
            'products' => $this->protection->activeProducts($country),
            'risk_notice' => 'The disclosed insurer or underwriter issues the cover and owns underwriting and claim decisions. OpFin orchestrates enrollment, premium collection and servicing.',
        ]);
    }

    public function policies(Request $request): JsonResponse
    {
        return ApiResponse::success('Protection policies loaded.', [
            'policies' => $this->protection->policiesFor($request->user()),
        ]);
    }

    public function show(ProtectionPolicy $policy, Request $request): JsonResponse
    {
        if ($policy->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        return ApiResponse::success('Protection policy loaded.', [
            'policy' => $policy->load(['product', 'premiumPayments.mobileMoneyTransaction', 'claims']),
        ]);
    }

    public function enroll(ProtectionProduct $product, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'accept_disclosures' => 'required|accepted',
            'disclosure_hash' => 'required|string|size:64',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Explicit acceptance of the protection disclosures is required.', 422, $validator->errors()->toArray());
        }

        try {
            $policy = $this->protection->enroll(
                $request->user(),
                $product,
                (string) $request->input('disclosure_hash'),
                [
                    'disclosure_hash' => strtolower((string) $request->input('disclosure_hash')),
                    'disclosure_version' => $product->disclosure_version,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'accepted_at' => now()->toISOString(),
                ],
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Protection enrollment recorded. Premium payment is required before partner issuance.', [
            'policy' => $policy,
            'next_state' => 'premium_due',
        ], 201);
    }

    public function payPremium(ProtectionPolicy $policy, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'idempotency_key' => 'required|string|min:8|max:128',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $payment = $this->protection->payPremium(
                $policy,
                $request->user(),
                (string) $request->input('idempotency_key'),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Protection premium collection initiated.', [
            'premium_payment' => $payment,
            'policy' => $payment->policy,
            'next_state' => $payment->status === 'confirmed' ? 'partner_confirmed' : 'awaiting_partner_confirmation',
        ], 202);
    }

    public function submitClaim(ProtectionPolicy $policy, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'incident_date' => 'required|date|before_or_equal:today',
            'category' => 'required|string|min:2|max:80',
            'description' => 'required|string|min:10|max:4000',
            'claimed_amount_minor' => 'nullable|integer|min:0',
            'evidence' => 'nullable|array|max:20',
            'evidence.*' => 'string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $claim = $this->protection->submitClaim($policy, $request->user(), $validator->validated());
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Protection claim submitted to the partner workflow.', [
            'claim' => $claim,
            'decision_authority' => 'insurer_or_underwriter',
        ], 201);
    }

    public function disputeClaim(ProtectionClaim $claim, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:2000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $claim = $this->protection->disputeClaim($claim, $request->user(), (string) $request->input('reason'));
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Protection claim dispute opened.', ['claim' => $claim]);
    }
}
