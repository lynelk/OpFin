<?php

declare(strict_types=1);

namespace App\Services\Stolets;

use App\Models\ConsentRecord;
use App\Models\FinancialSpace;
use App\Models\FinancialSpaceMembership;
use App\Models\LoanProduct;
use App\Services\LenderAuthorityEvidence;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Optional, read-only and non-consequential Stolets evidence adapter.
 * No loan, offer, eligibility, customer score or ledger writes are performed.
 */
final class BusinessPassportShadowService
{
    /** @param array<string, mixed> $references */
    public function inspect(array $references): array
    {
        if (! config('services.stolets_shadow.enabled', false)) {
            throw new RuntimeException('Optional merchant shadow evidence integration is disabled.');
        }

        $base = rtrim((string) config('services.stolets_shadow.base_url', ''), '/');
        $token = (string) config('services.stolets_shadow.partner_token', '');
        $url = parse_url($base);
        if (! is_array($url) || ($url['scheme'] ?? '') !== 'https'
            || empty($url['host']) || isset($url['user']) || isset($url['pass'])
            || isset($url['query']) || isset($url['fragment']) || strlen($token) < 32) {
            throw new RuntimeException('Stolets shadow integration requires an approved HTTPS endpoint and independent partner credential.');
        }

        foreach (['tenantId', 'consentGrantId', 'passportShareId', 'featureSnapshotId'] as $key) {
            if (! isset($references[$key]) || ! is_string($references[$key])
                || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D', $references[$key]) !== 1) {
                throw new InvalidArgumentException('Invalid merchant evidence reference.');
            }
        }

        // OpFin-owned customer grant, Financial Space and lender configuration must
        // be resolved server-side before passing any merchant references to Stolets.
        $borrowerId = (int) ($references['borrowerUserId'] ?? 0);
        $productId = (int) ($references['loanProductId'] ?? 0);
        $spaceId = (int) ($references['financialSpaceId'] ?? 0);
        $product = LoanProduct::withoutGlobalScopes()->with('institution')->find($productId);
        $space = FinancialSpace::query()->find($spaceId);
        $lender = $product?->institution;
        if (! $product || ! $lender || ! $space
            || $space->country !== 'UG' || $product->country !== 'UG'
            || $product->currency !== 'UGX'
            || stripos((string) $product->product_category, 'merchant') === false
            || strcasecmp((string) $product->status, 'Active') !== 0
            || strcasecmp((string) $lender->status, 'Active') !== 0
            || ! LenderAuthorityEvidence::complete([
                'authority_basis' => $lender->authority_basis,
                'authority_reference' => $lender->authority_reference,
                'regulator' => $lender->regulator_code,
            ])
            || ($lender->authority_valid_until && $lender->authority_valid_until->isPast())) {
            throw new RuntimeException('The merchant product and lender authority configuration are not eligible for shadow assessment.');
        }
        $member = FinancialSpaceMembership::query()
            ->where('financial_space_id', $spaceId)
            ->where('user_id', $borrowerId)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin', 'administrator'])
            ->exists();
        if (! $member || ($space->metadata['stolets_tenant_id'] ?? null) !== $references['tenantId']) {
            throw new RuntimeException('Merchant business ownership or source linkage requires independent verification.');
        }

        // An authenticated borrower must have granted a separate purpose-bound
        // OpFin consent. An administrator cannot supply or mint this authorisation.
        $consent = ConsentRecord::query()
            ->where('user_id', $borrowerId)
            ->where('purpose', 'merchant_finance_assessment')
            ->latest('id')->first();
        $expectedLender = 'opfin-lender-'.$lender->id;
        $meta = $consent?->metadata ?? [];
        if (! $consent || $consent->status !== ConsentRecord::STATUS_GRANTED
            || $consent->revoked_at || ! $consent->granted_at
            || $consent->granted_at->isFuture()
            || ($meta['financial_space_id'] ?? null) != $spaceId
            || ($meta['stolets_tenant_id'] ?? null) !== $references['tenantId']
            || ($meta['lender_recipient_id'] ?? null) !== $expectedLender) {
            throw new RuntimeException('Independent borrower consent for this merchant and lender is absent or revoked.');
        }

        $providerRequest = array_intersect_key($references, array_flip([
            'tenantId', 'consentGrantId', 'passportShareId', 'featureSnapshotId',
        ]));
        $providerRequest['providerOfRecordId'] = $expectedLender;

        try {
            $response = Http::acceptJson()->withToken($token)
                ->timeout(6)->connectTimeout(3)->withoutRedirecting()
                ->post($base.'/v1/partner-evidence/opfin/credit-shadow', $providerRequest);
        } catch (ConnectionException) {
            throw new RuntimeException('Stolets evidence provider is currently unavailable.');
        }

        if (! $response->successful()) {
            // Never surface upstream details, a consent grant or partner secret to callers.
            throw new RuntimeException('Merchant evidence could not be independently verified.');
        }

        $body = $response->json();
        if (! is_array($body)
            || ($body['contractVersion'] ?? null) !== 'opfin-partner-shadow-v1'
            || ($body['source'] ?? null) !== 'stolets'
            || ($body['recipient'] ?? null) !== 'opfin'
            || ($body['purpose'] ?? null) !== 'credit_assessment'
            || ($body['evidenceOnly'] ?? null) !== true
            || ($body['creditOrPaymentAuthorised'] ?? null) !== false
            || ($body['tenantId'] ?? null) !== $references['tenantId']
            || ($body['consentGrantId'] ?? null) !== $references['consentGrantId']
            || ($body['sharePackageId'] ?? null) !== $references['passportShareId']
            || ($body['featureSnapshotId'] ?? null) !== $references['featureSnapshotId']
            || ($body['providerOfRecordId'] ?? null) !== $expectedLender) {
            throw new RuntimeException('Merchant evidence provider returned an invalid or mismatched contract.');
        }

        $metrics = $body['metrics'] ?? null;
        if (! is_array($metrics) || ($metrics['currency'] ?? null) !== 'UGX') {
            throw new RuntimeException('Merchant evidence currency or shape is invalid.');
        }

        $numeric = [
            'netSalesMinor', 'refundsMinor', 'purchaseOrderValueMinor',
            'transactionCount', 'uniqueTradingDays', 'dataCoverageDays',
            'dataQualityBps', 'reconciliationBps',
            'expectedBaseGrossCashGenerationMinor',
            'expectedDownsideGrossCashGenerationMinor',
            'expectedInventoryCycleDays',
        ];
        foreach ($numeric as $key) {
            if (! array_key_exists($key, $metrics) || ! is_int($metrics[$key])
                || $metrics[$key] < 0 || $metrics[$key] > 9007199254740991) {
                throw new RuntimeException('Merchant evidence metric contract is invalid.');
            }
        }
        foreach (['dataQualityBps', 'reconciliationBps'] as $key) {
            if ($metrics[$key] > 10000) {
                throw new RuntimeException('Merchant evidence quality fraction is invalid.');
            }
        }

        $asOf = $body['sourcePeriodUntil'] ?? null;
        if (! is_string($asOf) || strtotime($asOf) === false
            || strtotime($asOf) > time() || strtotime($asOf) < time() - 7 * 86400) {
            throw new RuntimeException('Merchant evidence is outside the seven-day freshness window.');
        }
        $ends = $body['expiresAt'] ?? null;
        if ($ends !== null && (! is_string($ends) || strtotime($ends) === false || strtotime($ends) <= time())) {
            throw new RuntimeException('Merchant evidence permission has expired.');
        }

        // These are investigatory quality observations, NOT a score or risk policy.
        $reasons = [];
        if ($metrics['dataCoverageDays'] < 30) {
            $reasons[] = 'SHORT_OBSERVATION_WINDOW';
        }
        if ($metrics['dataQualityBps'] < 8000) {
            $reasons[] = 'SOURCE_DATA_QUALITY_REVIEW';
        }
        if ($metrics['reconciliationBps'] < 9000) {
            $reasons[] = 'RECONCILIATION_REVIEW';
        }
        if ($metrics['transactionCount'] === 0) {
            $reasons[] = 'INSUFFICIENT_TRADING_EVIDENCE';
        }

        return [
            'status' => 'shadow_evidence_received',
            'decision' => 'manual_review',
            'execution_authorised' => false,
            'existing_credit_policy_unchanged' => true,
            'lender_authority_status' => 'source_configuration_only_external_confirmation_required',
            'customer_consent_status' => 'opfin_granted_and_stolets_verified_at_request',
            'borrower_space_link_status' => 'self_reported_source_link_requires_operational_confirmation',
            'probability_of_default' => null,
            'approved_loan_amount_minor' => null,
            'verified_disposable_cash_flow_minor' => null,
            'source' => 'stolets',
            'evidence_references' => [
                'tenant_id' => $references['tenantId'],
                'feature_snapshot_id' => $references['featureSnapshotId'],
                'consent_grant_id' => $references['consentGrantId'],
                'share_package_id' => $references['passportShareId'],
                'borrower_user_id' => $borrowerId,
                'financial_space_id' => $spaceId,
                'loan_product_id' => $productId,
                'lender_id' => $lender->id,
                'lender_recipient_id' => $expectedLender,
            ],
            'observation_window_end' => $asOf,
            'quality_reason_codes' => $reasons,
            'indicators' => $metrics,
            'limitations' => [
                'These are signed and consent-scoped business indicators, not borrower affordability.',
                'A lender-approved product, independently verified obligations, pricing and funding are still required.',
                'No credit, payment, investment, pricing or customer decision has been changed.',
            ],
        ];
    }
}
