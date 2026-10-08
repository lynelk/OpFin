<?php

declare(strict_types=1);

namespace App\Services\AssetFinance;

use App\Models\AssetPassport;
use App\Models\FinancialProduct;
use App\Models\FinancialSpace;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FundingPoolService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Canonical Auto/Device/Productive Asset lifecycle.
 *
 * This service deliberately does not move money or remotely control devices. It binds a verified
 * asset, approved product, approved capital mandate and evidence-led supplier workflow, then records
 * provider/reconciliation evidence for settlement. CPay/provider execution remains a separate
 * governed rail and remote device controls remain disabled unless separately approved.
 */
final class AssetFinanceLifecycleService
{
    private const SPACE_MANAGERS = ['owner', 'administrator', 'admin', 'chairperson', 'treasurer', 'secretary', 'director', 'manager'];

    private const LIFECYCLE = [
        'active' => ['arrears', 'hardship', 'settled'],
        'arrears' => ['active', 'hardship', 'recovery'],
        'hardship' => ['active', 'arrears', 'recovery'],
        'recovery' => ['active', 'repossessed'],
        'repossessed' => ['resale_pending', 'trade_in_pending', 'settled'],
        'resale_pending' => ['settled'],
        'trade_in_pending' => ['settled'],
        'settled' => ['closed'],
    ];

    public function __construct(
        private readonly FundingPoolService $funding,
        private readonly AuditLogger $audit,
    ) {}

    public function list(FinancialSpace $space, User $actor): array
    {
        $this->assertMember($space, $actor);

        return DB::table('asset_finance_cases')
            ->where('financial_space_id', $space->id)
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn (object $case): array => $this->view($case, false))->all();
    }

    public function show(FinancialSpace $space, User $actor, int $caseId): array
    {
        $this->assertMember($space, $actor);
        $case = $this->case($caseId);
        abort_unless((int) $case->financial_space_id === (int) $space->id, 404);

        return $this->view($case, true);
    }

    public function create(FinancialSpace $space, User $actor, array $data, string $key): array
    {
        $this->assertMember($space, $actor);
        $asset = AssetPassport::query()->findOrFail((int) $data['asset_passport_id']);
        abort_unless((int) $asset->financial_space_id === (int) $space->id, 422, 'The asset must belong to this Financial Space.');
        abort_if(in_array($asset->status, ['review_required', 'reported_stolen', 'disposed'], true), 422,
            'The asset is not eligible for a financing case in its current registry state.');

        $vertical = (string) $data['vertical'];
        $rules = config('asset_finance.verticals.'.$vertical);
        abort_unless(is_array($rules), 422, 'This asset-finance vertical is not supported.');
        $assetFamily = (string) config("asset_registry.classes.{$asset->asset_class}.family", '');
        abort_unless(in_array($assetFamily, $rules['asset_families'] ?? [], true), 422,
            'The selected asset class does not belong to this finance vertical.');

        if ($vertical === 'device') {
            abort_if(trim((string) $asset->sku) === '', 422, 'Device finance requires an approved SKU on the Asset Passport.');
        }

        $supplier = SupplierProfile::query()->findOrFail((int) $data['supplier_profile_id']);
        $product = FinancialProduct::query()->findOrFail((int) $data['financial_product_id']);
        abort_unless(in_array($product->family, $rules['product_families'] ?? [], true), 422,
            'The selected financial product is not approved for this asset-finance vertical.');
        abort_unless($product->status === 'live', 422, 'The selected financial product is not live.');
        abort_unless($product->currency === (string) $data['currency'], 422, 'Product and case currency must match.');
        $this->assertProductPassport($product);

        $price = (int) $data['asset_price_minor'];
        $contribution = (int) ($data['customer_contribution_minor'] ?? 0);
        abort_if($price <= 0 || $contribution < 0 || $contribution >= $price, 422,
            'Customer contribution must be non-negative and lower than the asset price.');
        $finance = $price - $contribution;

        $attributes = [
            'user_id' => $actor->id,
            'financial_space_id' => $space->id,
            'asset_passport_id' => $asset->id,
            'supplier_profile_id' => $supplier->id,
            'financial_product_id' => $product->id,
            'capital_mandate_id' => (int) $data['capital_mandate_id'],
            'vertical' => $vertical,
            'currency' => $data['currency'],
            'asset_price_minor' => $price,
            'customer_contribution_minor' => $contribution,
            'requested_finance_minor' => $finance,
            'term_months' => (int) $data['term_months'],
        ];
        $instruction = hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($space, $actor, $attributes, $key, $instruction, $asset, $product): array {
            $existing = DB::table('asset_finance_cases')
                ->where('financial_space_id', $space->id)->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                abort_unless(hash_equals((string) $existing->instruction_hash, $instruction), 409,
                    'This Idempotency-Key was already used for a different asset-finance instruction.');

                return $this->view($existing, true);
            }

            $id = DB::table('asset_finance_cases')->insertGetId([
                ...$attributes,
                'reference' => (string) Str::uuid(),
                'status' => 'submitted',
                'policy_snapshot' => json_encode([
                    'jurisdiction' => config('asset_finance.jurisdiction'),
                    'asset_class' => $asset->asset_class,
                    'product_code' => $product->code,
                    'product_version' => $product->version,
                    'remote_device_controls' => false,
                    'money_movement' => 'separate_governed_provider_and_reconciliation_rail',
                ], JSON_THROW_ON_ERROR),
                'idempotency_key' => $key,
                'instruction_hash' => $instruction,
                'created_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $case = $this->case($id);
            $this->event($case, 'submitted', null, 'submitted', $actor, $key, null, [
                'requested_finance_minor' => $attributes['requested_finance_minor'],
                'customer_contribution_minor' => $attributes['customer_contribution_minor'],
            ]);
            $this->audit->record('asset_finance.case_submitted', $actor, null, ['case_reference' => $case->reference, 'vertical' => $case->vertical]);

            return $this->view($case, true);
        });
    }

    public function submitEvidence(FinancialSpace $space, User $actor, int $caseId, array $data): array
    {
        $this->assertMember($space, $actor);
        $case = $this->case($caseId);
        abort_unless((int) $case->financial_space_id === (int) $space->id, 404);
        abort_unless((int) $case->user_id === (int) $actor->id || $this->isManager($space, $actor), 403);
        abort_if(in_array($case->status, ['declined', 'closed'], true), 409, 'This case no longer accepts evidence.');

        $existing = DB::table('asset_finance_case_evidence')
            ->where('asset_finance_case_id', $case->id)
            ->where('evidence_type', $data['evidence_type'])
            ->where('evidence_reference', $data['evidence_reference'])->first();
        if ($existing !== null) {
            return $this->view($case, true);
        }

        DB::table('asset_finance_case_evidence')->insert([
            'asset_finance_case_id' => $case->id,
            'evidence_type' => $data['evidence_type'],
            'evidence_reference' => $data['evidence_reference'],
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata'], JSON_THROW_ON_ERROR) : null,
            'status' => 'submitted',
            'submitted_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit->record('asset_finance.evidence_submitted', $actor, null, [
            'case_reference' => $case->reference, 'evidence_type' => $data['evidence_type'],
        ]);

        return $this->view($case, true);
    }

    public function verifyEvidence(User $actor, int $caseId, int $evidenceId, array $data): array
    {
        $case = $this->case($caseId);
        $evidence = DB::table('asset_finance_case_evidence')->where('id', $evidenceId)
            ->where('asset_finance_case_id', $case->id)->first();
        abort_unless($evidence !== null, 404);
        abort_if((int) $evidence->submitted_by === (int) $actor->id, 403, 'Evidence requires a different verifier.');

        DB::table('asset_finance_case_evidence')->where('id', $evidence->id)->update([
            'status' => $data['status'],
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit->record('asset_finance.evidence_reviewed', $actor, null, [
            'case_reference' => $case->reference, 'evidence_type' => $evidence->evidence_type, 'status' => $data['status'],
        ]);

        return $this->view($case, true);
    }

    public function review(User $actor, int $caseId, array $data, string $key): array
    {
        return DB::transaction(function () use ($actor, $caseId, $data, $key): array {
            $case = DB::table('asset_finance_cases')->where('id', $caseId)->lockForUpdate()->first();
            abort_unless($case !== null, 404);
            $replay = $this->eventReplay($case, $key);
            if ($replay !== null) {
                return $this->view($case, true);
            }
            abort_unless(in_array($case->status, ['submitted', 'under_review'], true), 409, 'This case is no longer reviewable.');
            abort_if((int) $case->created_by === (int) $actor->id || (int) $case->user_id === (int) $actor->id, 403,
                'The applicant or case maker cannot approve the same case.');

            if ($data['decision'] === 'decline') {
                DB::table('asset_finance_cases')->where('id', $case->id)->update([
                    'status' => 'declined', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now(),
                ]);
                $this->event($case, 'declined', $case->status, 'declined', $actor, $key, $data['reason'], null);
                $this->audit->record('asset_finance.case_declined', $actor, null, ['case_reference' => $case->reference]);

                return $this->view($this->case($case->id), true);
            }

            $arrangementId = (int) $data['financing_arrangement_id'];
            $this->assertApprovalReady($case, $arrangementId);
            DB::table('asset_finance_cases')->where('id', $case->id)->update([
                'financing_arrangement_id' => $arrangementId,
                'approved_finance_minor' => (int) $case->requested_finance_minor,
                'status' => 'approved',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event($case, 'approved', $case->status, 'approved', $actor, $key, $data['reason'], [
                'financing_arrangement_id' => $arrangementId,
                'approved_finance_minor' => (int) $case->requested_finance_minor,
            ]);
            $this->audit->record('asset_finance.case_approved', $actor, null, [
                'case_reference' => $case->reference, 'financing_arrangement_id' => $arrangementId,
            ]);

            return $this->view($this->case($case->id), true);
        });
    }

    public function createSettlement(User $actor, int $caseId, array $data, string $key): array
    {
        return DB::transaction(function () use ($actor, $caseId, $data, $key): array {
            $case = DB::table('asset_finance_cases')->where('id', $caseId)->lockForUpdate()->first();
            abort_unless($case !== null, 404);
            abort_unless(in_array($case->status, ['approved', 'settlement_pending', 'settlement_reconciled'], true), 409,
                'Settlement instructions require an approved asset-finance case.');

            $expected = $data['settlement_type'] === 'customer_contribution'
                ? (int) $case->customer_contribution_minor
                : (int) $case->approved_finance_minor;
            abort_if($expected <= 0 || (int) $data['amount_minor'] !== $expected, 422,
                'Settlement amount must exactly match the approved case component.');

            $instruction = hash('sha256', json_encode([
                'case' => $case->reference, 'type' => $data['settlement_type'], 'amount' => $expected, 'currency' => $case->currency,
            ], JSON_THROW_ON_ERROR));
            $existing = DB::table('asset_finance_settlements')
                ->where('asset_finance_case_id', $case->id)->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                abort_unless(hash_equals((string) $existing->instruction_hash, $instruction), 409,
                    'This Idempotency-Key was already used for a different settlement instruction.');

                return $this->view($case, true);
            }

            DB::table('asset_finance_settlements')->insert([
                'reference' => (string) Str::uuid(),
                'asset_finance_case_id' => $case->id,
                'settlement_type' => $data['settlement_type'],
                'amount_minor' => $expected,
                'currency' => $case->currency,
                'status' => 'pending_provider_confirmation',
                'idempotency_key' => $key,
                'instruction_hash' => $instruction,
                'created_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($data['settlement_type'] === 'supplier_settlement') {
                DB::table('asset_finance_cases')->where('id', $case->id)->update(['status' => 'settlement_pending', 'updated_at' => now()]);
            }
            $this->event($case, $data['settlement_type'].'_requested', $case->status,
                $data['settlement_type'] === 'supplier_settlement' ? 'settlement_pending' : $case->status,
                $actor, $key, null, ['amount_minor' => $expected, 'provider_execution' => 'external_governed_rail']);
            $this->audit->record('asset_finance.settlement_requested', $actor, null, [
                'case_reference' => $case->reference, 'settlement_type' => $data['settlement_type'], 'amount_minor' => $expected,
            ]);

            return $this->view($this->case($case->id), true);
        });
    }

    public function transitionSettlement(User $actor, int $settlementId, array $data): array
    {
        return DB::transaction(function () use ($actor, $settlementId, $data): array {
            $settlement = DB::table('asset_finance_settlements')->where('id', $settlementId)->lockForUpdate()->first();
            abort_unless($settlement !== null, 404);
            $case = DB::table('asset_finance_cases')->where('id', $settlement->asset_finance_case_id)->lockForUpdate()->first();
            abort_unless($case !== null, 404);

            if ($data['status'] === 'provider_confirmed') {
                abort_unless($settlement->status === 'pending_provider_confirmation', 409, 'Settlement is not awaiting provider confirmation.');
                abort_if((int) $settlement->created_by === (int) $actor->id, 403, 'Settlement confirmation requires a different operator.');
                abort_if(trim((string) ($data['provider_reference'] ?? '')) === '', 422, 'Provider reference is required.');
                DB::table('asset_finance_settlements')->where('id', $settlement->id)->update([
                    'status' => 'provider_confirmed', 'provider_reference' => $data['provider_reference'],
                    'confirmed_by' => $actor->id, 'confirmed_at' => now(), 'updated_at' => now(),
                ]);
            } elseif ($data['status'] === 'reconciled') {
                abort_unless($settlement->status === 'provider_confirmed', 409, 'Provider confirmation is required before reconciliation.');
                abort_if((int) $settlement->confirmed_by === (int) $actor->id, 403, 'Reconciliation requires a different operator from provider confirmation.');
                abort_if(trim((string) ($data['reconciliation_reference'] ?? '')) === '', 422, 'Reconciliation reference is required.');
                DB::table('asset_finance_settlements')->where('id', $settlement->id)->update([
                    'status' => 'reconciled', 'reconciliation_reference' => $data['reconciliation_reference'],
                    'reconciled_by' => $actor->id, 'reconciled_at' => now(), 'updated_at' => now(),
                ]);
                if ($settlement->settlement_type === 'supplier_settlement') {
                    DB::table('asset_finance_cases')->where('id', $case->id)->update(['status' => 'settlement_reconciled', 'updated_at' => now()]);
                }
            } else {
                abort_if($settlement->status === 'reversed', 409, 'Settlement is already reversed.');
                abort_if(trim((string) ($data['reason'] ?? '')) === '', 422, 'A reversal reason is required.');
                DB::table('asset_finance_settlements')->where('id', $settlement->id)->update([
                    'status' => 'reversed', 'updated_at' => now(),
                ]);
                if ($settlement->settlement_type === 'supplier_settlement') {
                    DB::table('asset_finance_cases')->where('id', $case->id)->update(['status' => 'approved', 'updated_at' => now()]);
                }
            }

            $this->audit->record('asset_finance.settlement_transitioned', $actor, null, [
                'case_reference' => $case->reference, 'settlement_reference' => $settlement->reference, 'status' => $data['status'],
            ]);

            return $this->view($this->case($case->id), true);
        });
    }

    public function activate(User $actor, int $caseId, array $data, string $key): array
    {
        return DB::transaction(function () use ($actor, $caseId, $data, $key): array {
            $case = DB::table('asset_finance_cases')->where('id', $caseId)->lockForUpdate()->first();
            abort_unless($case !== null, 404);
            if ($this->eventReplay($case, $key) !== null) {
                return $this->view($case, true);
            }
            abort_unless($case->status === 'settlement_reconciled', 409, 'Supplier settlement must be reconciled before activation.');
            $this->assertVerifiedEvidence($case, config("asset_finance.verticals.{$case->vertical}.activation_evidence", []));

            if ((int) $case->customer_contribution_minor > 0) {
                abort_unless(DB::table('asset_finance_settlements')->where('asset_finance_case_id', $case->id)
                    ->where('settlement_type', 'customer_contribution')->where('status', 'reconciled')->exists(), 409,
                    'Customer contribution must be provider-confirmed and reconciled before activation.');
            }
            abort_unless(DB::table('asset_encumbrances')->where('asset_passport_id', $case->asset_passport_id)
                ->where('financing_arrangement_id', $case->financing_arrangement_id)->where('status', 'active')->exists(), 409,
                'The financed asset must carry the active lien for this financing arrangement before activation.');

            DB::table('asset_finance_cases')->where('id', $case->id)->update([
                'status' => 'active', 'activated_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($case, 'activated', $case->status, 'active', $actor, $key, null, [
                'activation_reference' => $data['activation_reference'],
                'remote_device_controls' => false,
            ]);
            $this->audit->record('asset_finance.case_activated', $actor, null, ['case_reference' => $case->reference]);

            return $this->view($this->case($case->id), true);
        });
    }

    public function transition(User $actor, int $caseId, array $data, string $key): array
    {
        return DB::transaction(function () use ($actor, $caseId, $data, $key): array {
            $case = DB::table('asset_finance_cases')->where('id', $caseId)->lockForUpdate()->first();
            abort_unless($case !== null, 404);
            if ($this->eventReplay($case, $key) !== null) {
                return $this->view($case, true);
            }
            $target = (string) $data['status'];
            abort_unless(in_array($target, self::LIFECYCLE[$case->status] ?? [], true), 409,
                'This lifecycle transition is not available from the current state.');

            if ($target === 'settled') {
                $arrangement = DB::table('financing_arrangements')->where('id', $case->financing_arrangement_id)->first();
                abort_unless($arrangement !== null && $arrangement->settled_at !== null, 409,
                    'The financing arrangement must be financially settled before the asset-finance case can be settled.');
            }
            if ($target === 'closed') {
                abort_if(DB::table('asset_encumbrances')->where('asset_passport_id', $case->asset_passport_id)
                    ->where('status', 'active')->exists(), 409, 'Release the active lien before closing the asset-finance case.');
            }

            DB::table('asset_finance_cases')->where('id', $case->id)->update([
                'status' => $target,
                'closed_at' => $target === 'closed' ? now() : null,
                'updated_at' => now(),
            ]);
            $this->event($case, 'lifecycle_'.$target, $case->status, $target, $actor, $key, $data['reason'], [
                'evidence_reference' => $data['evidence_reference'],
            ]);
            $this->audit->record('asset_finance.lifecycle_transitioned', $actor, null, [
                'case_reference' => $case->reference, 'from' => $case->status, 'to' => $target,
            ]);

            return $this->view($this->case($case->id), true);
        });
    }

    public function workQueue(): array
    {
        return DB::table('asset_finance_cases')
            ->whereNotIn('status', ['declined', 'closed'])
            ->orderBy('id')->limit(200)->get()
            ->map(fn (object $case): array => $this->view($case, false))->all();
    }

    private function assertApprovalReady(object $case, int $arrangementId): void
    {
        $asset = AssetPassport::query()->findOrFail($case->asset_passport_id);
        abort_unless($asset->verified_at !== null && in_array($asset->status, ['verified', 'encumbered'], true), 409,
            'Asset Passport verification must be complete before approval.');
        abort_if($asset->review_reason !== null, 409, 'Resolve the Asset Passport review before approval.');

        $supplier = SupplierProfile::query()->findOrFail($case->supplier_profile_id);
        abort_unless(in_array(strtolower($supplier->verification_status), ['verified', 'approved', 'active'], true), 409,
            'Merchant/dealer/supplier KYB must be verified before approval.');
        abort_if(empty($supplier->kyb_evidence) || empty($supplier->settlement_details), 409,
            'Verified supplier KYB evidence and settlement details are required before approval.');

        $product = FinancialProduct::query()->findOrFail($case->financial_product_id);
        abort_unless($product->status === 'live', 409, 'Financial product must remain live at approval.');
        $this->assertProductPassport($product);

        $rules = config("asset_finance.verticals.{$case->vertical}");
        abort_unless(is_array($rules), 422, 'Unknown vertical policy.');
        $this->assertVerifiedEvidence($case, $rules['pre_approval_evidence'] ?? []);

        try {
            $this->funding->validateSelection((int) $case->capital_mandate_id, (int) $case->requested_finance_minor);
        } catch (InvalidArgumentException $error) {
            throw ValidationException::withMessages(['capital_mandate_id' => [$error->getMessage()]]);
        }

        $mandate = DB::table('capital_mandates')->where('id', $case->capital_mandate_id)->first();
        $policy = json_decode((string) ($mandate?->investment_policy ?? '{}'), true);
        $policy = is_array($policy) ? $policy : [];
        $allowedClasses = $policy['allowed_asset_classes'] ?? [];
        if (is_array($allowedClasses) && $allowedClasses !== []) {
            abort_unless(in_array($asset->asset_class, $allowedClasses, true), 422, 'Capital mandate does not allow this asset class.');
        }
        $allowedFamilies = $policy['allowed_product_families'] ?? [];
        if (is_array($allowedFamilies) && $allowedFamilies !== []) {
            abort_unless(in_array($product->family, $allowedFamilies, true), 422, 'Capital mandate does not allow this product family.');
        }
        $maxTerm = (int) ($policy['max_term_months'] ?? 0);
        abort_if($maxTerm > 0 && (int) $case->term_months > $maxTerm, 422, 'Requested term exceeds the capital mandate limit.');

        $arrangement = DB::table('financing_arrangements')->where('id', $arrangementId)->first();
        abort_unless($arrangement !== null, 422, 'Financing arrangement was not found.');
        abort_unless((int) $arrangement->financial_space_id === (int) $case->financial_space_id
            && (int) $arrangement->user_id === (int) $case->user_id
            && (int) $arrangement->financial_product_id === (int) $case->financial_product_id, 422,
            'Financing arrangement must belong to the same customer, Space and product.');
        abort_unless((int) $arrangement->principal_or_cost_minor === (int) $case->requested_finance_minor, 422,
            'Financing arrangement principal/cost must match the approved finance amount.');
        abort_if($arrangement->settled_at !== null || in_array($arrangement->status, ['settled', 'closed', 'cancelled', 'declined'], true), 422,
            'Financing arrangement is no longer available for activation.');
    }

    private function assertProductPassport(FinancialProduct $product): void
    {
        $passport = $product->legal_product_passport_id
            ? DB::table('legal_product_passports')->where('id', $product->legal_product_passport_id)->first()
            : null;
        abort_unless($passport !== null && $passport->status === 'approved' && $passport->approved_at !== null, 422,
            'An approved Legal Product Passport is required.');
        abort_if($passport->effective_from !== null && now()->lt($passport->effective_from), 422, 'Legal Product Passport is not yet effective.');
        abort_if($passport->effective_to !== null && now()->gt($passport->effective_to), 422, 'Legal Product Passport has expired.');
        abort_if($passport->revoked_at !== null, 422, 'Legal Product Passport has been revoked.');
    }

    private function assertVerifiedEvidence(object $case, array $required): void
    {
        if ($required === []) {
            return;
        }
        $verified = DB::table('asset_finance_case_evidence')->where('asset_finance_case_id', $case->id)
            ->where('status', 'verified')->pluck('evidence_type')->all();
        $missing = array_values(array_diff($required, $verified));
        abort_if($missing !== [], 409, 'Verified evidence is still required: '.implode(', ', $missing).'.');
    }

    private function eventReplay(object $case, string $key): ?object
    {
        return DB::table('asset_finance_case_events')
            ->where('asset_finance_case_id', $case->id)->where('idempotency_key', $key)->first();
    }

    private function event(object $case, string $type, ?string $from, string $to, ?User $actor, string $key, ?string $reason, ?array $evidence): void
    {
        DB::table('asset_finance_case_events')->insert([
            'asset_finance_case_id' => $case->id,
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => $actor?->id,
            'idempotency_key' => $key,
            'reason' => $reason,
            'evidence' => $evidence ? json_encode($evidence, JSON_THROW_ON_ERROR) : null,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function view(object $case, bool $detail): array
    {
        $asset = DB::table('asset_passports')->where('id', $case->asset_passport_id)->first();
        $supplier = DB::table('supplier_profiles')->where('id', $case->supplier_profile_id)->first();
        $product = DB::table('financial_products')->where('id', $case->financial_product_id)->first();
        $mandate = DB::table('capital_mandates')->where('id', $case->capital_mandate_id)->first();

        $view = [
            'id' => $case->id,
            'reference' => $case->reference,
            'vertical' => $case->vertical,
            'status' => $case->status,
            'currency' => $case->currency,
            'asset_price_minor' => (int) $case->asset_price_minor,
            'customer_contribution_minor' => (int) $case->customer_contribution_minor,
            'requested_finance_minor' => (int) $case->requested_finance_minor,
            'approved_finance_minor' => $case->approved_finance_minor !== null ? (int) $case->approved_finance_minor : null,
            'term_months' => (int) $case->term_months,
            'asset' => ['reference' => $asset?->reference, 'class' => $asset?->asset_class, 'status' => $asset?->status],
            'supplier' => ['reference' => $supplier?->reference, 'name' => $supplier?->name, 'verification_status' => $supplier?->verification_status],
            'product' => ['reference' => $product?->reference, 'code' => $product?->code, 'version' => $product?->version, 'family' => $product?->family],
            'capital_mandate' => ['reference' => $mandate?->reference, 'name' => $mandate?->name, 'status' => $mandate?->status],
            'financing_arrangement_id' => $case->financing_arrangement_id,
            'remote_device_controls' => 'not_available',
        ];
        if ($detail) {
            $view['required_pre_approval_evidence'] = config("asset_finance.verticals.{$case->vertical}.pre_approval_evidence", []);
            $view['required_activation_evidence'] = config("asset_finance.verticals.{$case->vertical}.activation_evidence", []);
            $view['evidence'] = DB::table('asset_finance_case_evidence')->where('asset_finance_case_id', $case->id)
                ->orderBy('id')->get(['id', 'evidence_type', 'evidence_reference', 'status', 'verified_at']);
            $view['settlements'] = DB::table('asset_finance_settlements')->where('asset_finance_case_id', $case->id)
                ->orderBy('id')->get(['id', 'reference', 'settlement_type', 'amount_minor', 'currency', 'status', 'provider_reference', 'reconciliation_reference']);
            $view['history'] = DB::table('asset_finance_case_events')->where('asset_finance_case_id', $case->id)
                ->orderBy('id')->get(['event_type', 'from_status', 'to_status', 'reason', 'evidence', 'occurred_at']);
        }

        return $view;
    }

    private function case(int $id): object
    {
        $case = DB::table('asset_finance_cases')->where('id', $id)->first();
        abort_unless($case !== null, 404);

        return $case;
    }

    private function assertMember(FinancialSpace $space, User $actor): void
    {
        abort_unless($space->status === 'active', 409, 'Financial Space is not active.');
        abort_unless(DB::table('financial_space_memberships')->where('financial_space_id', $space->id)
            ->where('user_id', $actor->id)->where('status', 'active')->exists(), 403);
    }

    private function isManager(FinancialSpace $space, User $actor): bool
    {
        return DB::table('financial_space_memberships')->where('financial_space_id', $space->id)
            ->where('user_id', $actor->id)->where('status', 'active')->whereIn('role', self::SPACE_MANAGERS)->exists();
    }
}
