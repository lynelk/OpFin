<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProtectionClaim;
use App\Models\ProtectionPolicy;
use App\Models\ProtectionPremiumPayment;
use App\Models\ProtectionProduct;
use App\Models\SavingsMovement;
use App\Models\SavingsProduct;
use App\Services\AuditLogger;
use App\Services\ProtectionService;
use App\Services\SavingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class SaveProtectionOperationsController extends Controller
{
    public function __construct(
        private readonly SavingsService $savings,
        private readonly ProtectionService $protection,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function savingsProducts(): JsonResponse
    {
        return ApiResponse::success('Savings product catalogue loaded.', [
            'products' => SavingsProduct::query()->latest()->get(),
        ]);
    }

    public function createSavingsProduct(Request $request): JsonResponse
    {
        $validator = $this->savingsProductValidator($request);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $product = SavingsProduct::create([
            ...$validator->validated(),
            'status' => SavingsProduct::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);
        $this->auditLogger->record('savings.product.created', $request->user(), $product, [
            'status' => SavingsProduct::STATUS_DRAFT,
            'requires_independent_approval' => true,
        ], $request);

        return ApiResponse::success('Savings product created in draft status.', ['product' => $product], 201);
    }

    public function updateSavingsProduct(SavingsProduct $product, Request $request): JsonResponse
    {
        $validator = $this->savingsProductValidator($request, true, $product);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $changes = $validator->validated();
        $materialFields = array_values(array_diff(array_keys($changes), ['status']));
        if ($product->status === SavingsProduct::STATUS_ACTIVE && $materialFields !== []) {
            return ApiResponse::error('Pause the active savings product before changing controlled terms or disclosures.', 409);
        }

        if ($materialFields !== []) {
            $changes = array_merge($changes, [
                'status' => SavingsProduct::STATUS_DRAFT,
                'approved_by' => null,
                'approved_at' => null,
                'approval_evidence' => null,
            ]);
        }

        $product->update($changes);
        $this->auditLogger->record('savings.product.updated', $request->user(), $product, [
            'changed_fields' => array_keys($validator->validated()),
            'approval_reset' => $materialFields !== [],
        ], $request);

        return ApiResponse::success('Savings product updated.', ['product' => $product->fresh()]);
    }

    public function activateSavingsProduct(SavingsProduct $product, Request $request): JsonResponse
    {
        $validator = $this->approvalValidator($request);
        if ($validator->fails()) {
            return ApiResponse::error('Independent product approval evidence is required.', 422, $validator->errors()->toArray());
        }
        if ($product->created_by && $product->created_by === $request->user()->id) {
            return ApiResponse::error('Maker-checker control prevents the product author from approving activation.', 409);
        }

        $candidate = array_merge($product->toArray(), ['status' => SavingsProduct::STATUS_ACTIVE]);
        if ($error = $this->savingsActivationError($candidate)) {
            return ApiResponse::error($error, 409);
        }

        $product->update([
            'status' => SavingsProduct::STATUS_ACTIVE,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approval_evidence' => $this->approvalEvidence($request),
        ]);
        $this->auditLogger->record('savings.product.activated', $request->user(), $product, [
            'created_by' => $product->created_by,
            'approval_reference' => $request->input('approval_reference'),
            'approval_evidence_hash' => strtolower((string) $request->input('approval_evidence_hash')),
        ], $request);

        return ApiResponse::success('Savings product independently approved and activated.', ['product' => $product->fresh()]);
    }

    public function confirmSavingsContribution(SavingsMovement $movement, Request $request): JsonResponse
    {
        $validator = $this->partnerEvidenceValidator($request);
        if ($validator->fails()) {
            return ApiResponse::error('Partner settlement evidence is required.', 422, $validator->errors()->toArray());
        }

        try {
            $movement = $this->savings->confirmContribution(
                $movement,
                $request->user(),
                (string) $request->input('partner_reference'),
                (string) $request->input('partner_evidence_hash'),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Savings contribution confirmed by partner evidence.', ['movement' => $movement]);
    }

    public function releaseSavingsWithdrawal(SavingsMovement $movement, Request $request): JsonResponse
    {
        $validator = $this->partnerEvidenceValidator($request);
        if ($validator->fails()) {
            return ApiResponse::error('Partner release evidence is required.', 422, $validator->errors()->toArray());
        }

        try {
            $movement = $this->savings->releaseWithdrawal(
                $movement,
                $request->user(),
                (string) $request->input('partner_reference'),
                (string) $request->input('partner_evidence_hash'),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Savings withdrawal released and payout initiated.', ['movement' => $movement]);
    }

    public function retrySavingsWithdrawalPayout(SavingsMovement $movement, Request $request): JsonResponse
    {
        try {
            $movement = $this->savings->retryWithdrawalPayout($movement, $request->user());
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Savings withdrawal payout retried.', ['movement' => $movement]);
    }

    public function protectionProducts(): JsonResponse
    {
        return ApiResponse::success('Protection product catalogue loaded.', [
            'products' => ProtectionProduct::query()->latest()->get()->map(fn (ProtectionProduct $product) => [
                ...$product->toArray(),
                'disclosure_hash' => $product->disclosureHash(),
            ]),
        ]);
    }

    public function createProtectionProduct(Request $request): JsonResponse
    {
        $validator = $this->protectionProductValidator($request);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $product = ProtectionProduct::create([
            ...$validator->validated(),
            'status' => ProtectionProduct::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);
        $this->auditLogger->record('protection.product.created', $request->user(), $product, [
            'status' => ProtectionProduct::STATUS_DRAFT,
            'requires_independent_approval' => true,
        ], $request);

        return ApiResponse::success('Protection product created in draft status.', [
            'product' => $product,
            'disclosure_hash' => $product->disclosureHash(),
        ], 201);
    }

    public function updateProtectionProduct(ProtectionProduct $product, Request $request): JsonResponse
    {
        $validator = $this->protectionProductValidator($request, true, $product);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $changes = $validator->validated();
        $materialFields = array_values(array_diff(array_keys($changes), ['status']));
        if ($product->status === ProtectionProduct::STATUS_ACTIVE && $materialFields !== []) {
            return ApiResponse::error('Pause the active protection product before changing controlled terms or disclosures.', 409);
        }

        if ($materialFields !== []) {
            $changes = array_merge($changes, [
                'status' => ProtectionProduct::STATUS_DRAFT,
                'approved_by' => null,
                'approved_at' => null,
                'approval_evidence' => null,
            ]);
        }

        $product->update($changes);
        $this->auditLogger->record('protection.product.updated', $request->user(), $product, [
            'changed_fields' => array_keys($validator->validated()),
            'approval_reset' => $materialFields !== [],
        ], $request);

        return ApiResponse::success('Protection product updated.', [
            'product' => $product->fresh(),
            'disclosure_hash' => $product->fresh()->disclosureHash(),
        ]);
    }

    public function activateProtectionProduct(ProtectionProduct $product, Request $request): JsonResponse
    {
        $validator = $this->approvalValidator($request);
        if ($validator->fails()) {
            return ApiResponse::error('Independent product approval evidence is required.', 422, $validator->errors()->toArray());
        }
        if ($product->created_by && $product->created_by === $request->user()->id) {
            return ApiResponse::error('Maker-checker control prevents the product author from approving activation.', 409);
        }

        $candidate = array_merge($product->toArray(), ['status' => ProtectionProduct::STATUS_ACTIVE]);
        if ($error = $this->protectionActivationError($candidate)) {
            return ApiResponse::error($error, 409);
        }

        $product->update([
            'status' => ProtectionProduct::STATUS_ACTIVE,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approval_evidence' => $this->approvalEvidence($request),
        ]);
        $this->auditLogger->record('protection.product.activated', $request->user(), $product, [
            'created_by' => $product->created_by,
            'approval_reference' => $request->input('approval_reference'),
            'approval_evidence_hash' => strtolower((string) $request->input('approval_evidence_hash')),
        ], $request);

        return ApiResponse::success('Protection product independently approved and activated.', [
            'product' => $product->fresh(),
            'disclosure_hash' => $product->fresh()->disclosureHash(),
        ]);
    }

    public function confirmPremium(ProtectionPremiumPayment $payment, Request $request): JsonResponse
    {
        $validator = $this->partnerEvidenceValidator($request);
        if ($validator->fails()) {
            return ApiResponse::error('Insurer premium settlement evidence is required.', 422, $validator->errors()->toArray());
        }

        try {
            $payment = $this->protection->confirmPremiumSettlement(
                $payment,
                $request->user(),
                (string) $request->input('partner_reference'),
                (string) $request->input('partner_evidence_hash'),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Protection premium settlement confirmed.', ['premium_payment' => $payment]);
    }

    public function issuePolicy(ProtectionPolicy $policy, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'external_policy_number' => 'required|string|max:160',
            'partner_reference' => 'required|string|max:160',
            'cover_start_date' => 'required|date',
            'cover_end_date' => 'required|date|after:cover_start_date',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Policy issuance evidence is incomplete.', 422, $validator->errors()->toArray());
        }

        try {
            $policy = $this->protection->issuePolicy(
                $policy,
                $request->user(),
                (string) $request->input('external_policy_number'),
                (string) $request->input('partner_reference'),
                (string) $request->input('cover_start_date'),
                (string) $request->input('cover_end_date'),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Protection policy issuance recorded.', ['policy' => $policy]);
    }

    public function updateClaim(ProtectionClaim $claim, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in([
                ProtectionClaim::STATUS_PARTNER_REVIEW,
                ProtectionClaim::STATUS_APPROVED,
                ProtectionClaim::STATUS_DECLINED,
                ProtectionClaim::STATUS_PAID,
                ProtectionClaim::STATUS_CLOSED,
            ])],
            'partner_claim_reference' => 'nullable|string|max:160',
            'decision_reason' => 'nullable|string|max:3000',
            'approved_amount_minor' => 'nullable|integer|min:0',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $claim = $this->protection->updateClaim(
                $claim,
                $request->user(),
                (string) $request->input('status'),
                $request->input('partner_claim_reference'),
                $request->input('decision_reason'),
                $request->filled('approved_amount_minor') ? (int) $request->input('approved_amount_minor') : null,
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Protection claim partner state updated.', ['claim' => $claim]);
    }

    private function partnerEvidenceValidator(Request $request): mixed
    {
        return Validator::make($request->all(), [
            'partner_reference' => 'required|string|max:160',
            'partner_evidence_hash' => 'required|string|size:64|regex:/^[a-fA-F0-9]{64}$/',
        ]);
    }

    private function approvalValidator(Request $request): mixed
    {
        return Validator::make($request->all(), [
            'approval_reference' => 'required|string|max:160',
            'approval_evidence_hash' => 'required|string|size:64|regex:/^[a-fA-F0-9]{64}$/',
            'approval_note' => 'required|string|min:10|max:2000',
        ]);
    }

    private function approvalEvidence(Request $request): array
    {
        return [
            'approval_reference' => (string) $request->input('approval_reference'),
            'approval_evidence_hash' => strtolower((string) $request->input('approval_evidence_hash')),
            'approval_note' => trim((string) $request->input('approval_note')),
            'approved_at' => now()->toISOString(),
        ];
    }

    private function savingsProductValidator(Request $request, bool $partial = false, ?SavingsProduct $product = null): mixed
    {
        $prefix = $partial ? 'sometimes|' : 'required|';

        return Validator::make($request->all(), [
            'code' => [$partial ? 'sometimes' : 'required', 'string', 'max:64', Rule::unique('savings_products', 'code')->ignore($product?->id)],
            'name' => $prefix.'string|max:160',
            'partner_name' => $prefix.'string|max:160',
            'partner_product_reference' => 'nullable|string|max:160',
            'country_code' => $prefix.'string|size:2',
            'currency' => $prefix.'string|size:3',
            'product_type' => [$partial ? 'sometimes' : 'required', Rule::in(['goal', 'emergency', 'notice', 'group', 'sacco', 'employer'])],
            'status' => ['sometimes', Rule::in([SavingsProduct::STATUS_DRAFT, SavingsProduct::STATUS_PAUSED, SavingsProduct::STATUS_RETIRED])],
            'custody_model' => [$partial ? 'sometimes' : 'required', Rule::in(['partner_held'])],
            'minimum_contribution_minor' => 'sometimes|integer|min:0',
            'maximum_contribution_minor' => 'nullable|integer|min:1',
            'minimum_withdrawal_minor' => 'sometimes|integer|min:0',
            'notice_days' => 'sometimes|integer|min:0|max:3650',
            'lock_days' => 'sometimes|integer|min:0|max:3650',
            'terms_version' => $prefix.'string|max:64',
            'terms_url' => 'nullable|string|max:500',
            'disclosures' => 'nullable|array|min:1',
            'effective_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:effective_at',
        ]);
    }

    private function protectionProductValidator(Request $request, bool $partial = false, ?ProtectionProduct $product = null): mixed
    {
        $prefix = $partial ? 'sometimes|' : 'required|';

        return Validator::make($request->all(), [
            'code' => [$partial ? 'sometimes' : 'required', 'string', 'max:64', Rule::unique('protection_products', 'code')->ignore($product?->id)],
            'name' => $prefix.'string|max:160',
            'insurer_name' => $prefix.'string|max:160',
            'underwriter_name' => 'nullable|string|max:160',
            'partner_product_reference' => 'nullable|string|max:160',
            'country_code' => $prefix.'string|size:2',
            'currency' => $prefix.'string|size:3',
            'product_type' => [$partial ? 'sometimes' : 'required', Rule::in(['micro', 'loan', 'health', 'event', 'device', 'asset'])],
            'status' => ['sometimes', Rule::in([ProtectionProduct::STATUS_DRAFT, ProtectionProduct::STATUS_PAUSED, ProtectionProduct::STATUS_RETIRED])],
            'premium_amount_minor' => $prefix.'integer|min:1',
            'premium_frequency' => [$partial ? 'sometimes' : 'required', Rule::in(['weekly', 'monthly', 'quarterly', 'annual', 'yearly', 'one_off', 'single'])],
            'coverage_limit_minor' => 'nullable|integer|min:0',
            'disclosure_version' => $prefix.'string|max:64',
            'benefits' => 'nullable|array|min:1',
            'exclusions' => 'nullable|array',
            'disclosure_payload' => $prefix.'array|min:1',
            'terms_url' => 'nullable|string|max:500',
            'effective_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:effective_at',
        ]);
    }

    private function savingsActivationError(array $data): ?string
    {
        if (($data['custody_model'] ?? null) !== 'partner_held') {
            return 'Active savings products must use partner-held custody.';
        }
        if (empty($data['partner_product_reference']) || empty($data['disclosures']) || empty($data['terms_url'])) {
            return 'Active savings products require partner product reference, customer disclosures and controlled terms URL.';
        }

        return null;
    }

    private function protectionActivationError(array $data): ?string
    {
        if (empty($data['partner_product_reference']) || empty($data['insurer_name']) || empty($data['benefits']) || ! array_key_exists('exclusions', $data) || empty($data['disclosure_payload']) || empty($data['terms_url'])) {
            return 'Active protection products require insurer ownership, partner reference, benefits, exclusions, disclosure payload and controlled terms URL.';
        }

        return null;
    }
}
