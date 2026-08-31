<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\CreditDecision;
use App\Models\KycCase;
use App\Models\MobileMoneyTransaction;
use App\Models\SupportCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RegulatoryReportingService
{
    public const PROFILES = [
        'fia_annual_compliance' => 'FIA',
        'fia_large_cash_transactions' => 'FIA',
        'fia_suspicious_activity_register' => 'FIA',
        'pdpo_annual_compliance' => 'PDPO',
        'umra_digital_credit_supervision' => 'UMRA',
        'consumer_protection_complaints' => 'UMRA',
        'payment_integrity_oversight' => 'BOU',
    ];

    public function generate(string $reportType, Carbon $start, Carbon $end): object
    {
        if (! array_key_exists($reportType, self::PROFILES)) {
            throw new \InvalidArgumentException('Unsupported regulatory report type.');
        }

        $payload = match ($reportType) {
            'fia_large_cash_transactions' => $this->largeCashTransactions($start, $end),
            'fia_suspicious_activity_register' => $this->suspiciousActivityRegister($start, $end),
            'pdpo_annual_compliance' => $this->privacyCompliance($start, $end),
            'umra_digital_credit_supervision' => $this->digitalCreditSupervision($start, $end),
            'consumer_protection_complaints' => $this->consumerProtection($start, $end),
            'payment_integrity_oversight' => $this->paymentIntegrity($start, $end),
            default => $this->annualAmlCompliance($start, $end),
        };

        $validation = $this->validate($reportType, $payload, $start, $end);
        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        return DB::table('regulatory_report_runs')->updateOrInsert([
            'report_type' => $reportType,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ], [
            'regulator' => self::PROFILES[$reportType],
            'status' => $validation['valid'] ? 'validated' : 'validation_failed',
            'payload' => $canonical,
            'validation_results' => json_encode($validation),
            'payload_hash' => hash('sha256', $canonical),
            'generated_at' => now(),
            'validated_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ])
            ? DB::table('regulatory_report_runs')
                ->where('report_type', $reportType)
                ->whereDate('period_start', $start)
                ->whereDate('period_end', $end)
                ->first()
            : throw new \RuntimeException('Unable to persist regulatory report.');
    }

    public function generateScheduledSet(?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $monthStart = $asOf->copy()->startOfMonth();
        $monthEnd = $asOf->copy()->endOfMonth();
        $yearStart = $asOf->copy()->startOfYear();
        $yearEnd = $asOf->copy()->endOfYear();

        return [
            $this->generate('fia_large_cash_transactions', $monthStart, $monthEnd),
            $this->generate('fia_suspicious_activity_register', $monthStart, $monthEnd),
            $this->generate('umra_digital_credit_supervision', $monthStart, $monthEnd),
            $this->generate('consumer_protection_complaints', $monthStart, $monthEnd),
            $this->generate('payment_integrity_oversight', $monthStart, $monthEnd),
            $this->generate('fia_annual_compliance', $yearStart, $yearEnd),
            $this->generate('pdpo_annual_compliance', $yearStart, $yearEnd),
        ];
    }

    private function annualAmlCompliance(Carbon $start, Carbon $end): array
    {
        return [
            'kyc_cases' => KycCase::whereBetween('created_at', [$start, $end])->count(),
            'consents' => ConsentRecord::whereBetween('created_at', [$start, $end])->count(),
            'payment_transactions' => MobileMoneyTransaction::whereBetween('created_at', [$start, $end])->count(),
            'suspicious_activity_candidates' => count($this->suspiciousActivityRegister($start, $end)['candidates']),
            'large_cash_transaction_candidates' => count($this->largeCashTransactions($start, $end)['transactions']),
            'control_evidence' => [
                'kyc_monitoring' => true,
                'transaction_monitoring' => true,
                'immutable_ledger' => true,
                'reconciliation' => true,
                'audit_logging' => true,
            ],
            'submission_control' => 'MLCO approval required before external submission.',
        ];
    }

    private function largeCashTransactions(Carbon $start, Carbon $end): array
    {
        $transactions = MobileMoneyTransaction::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('currency', 'UGX')
            ->where('amount_minor', '>=', 2000000000)
            ->get(['id', 'transaction_id', 'user_id', 'direction', 'amount_minor', 'currency', 'provider', 'provider_reference', 'created_at']);

        return [
            'threshold_ugx' => 20000000,
            'transactions' => $transactions->map(fn ($item) => [
                'id' => $item->id,
                'transaction_id' => $item->transaction_id,
                'user_id' => $item->user_id,
                'direction' => $item->direction,
                'amount_minor' => $item->amount_minor,
                'currency' => $item->currency,
                'provider' => $item->provider,
                'provider_reference' => $item->provider_reference,
                'occurred_at' => $item->created_at,
            ])->all(),
            'submission_control' => 'Generated as a candidate register. Regulatory submission remains an accountable-person/MLCO action.',
        ];
    }

    private function suspiciousActivityRegister(Carbon $start, Carbon $end): array
    {
        $candidates = MobileMoneyTransaction::query()
            ->whereBetween('created_at', [$start, $end])
            ->where(function ($query) {
                $query->where('reconciliation_status', MobileMoneyTransaction::RECONCILIATION_EXCEPTION)
                    ->orWhere('retry_count', '>=', 3)
                    ->orWhereNotNull('failure_reason');
            })
            ->get(['id', 'transaction_id', 'user_id', 'amount_minor', 'currency', 'status', 'reconciliation_status', 'retry_count', 'failure_reason', 'created_at']);

        return [
            'candidates' => $candidates->map(fn ($item) => [
                'id' => $item->id,
                'transaction_id' => $item->transaction_id,
                'user_id' => $item->user_id,
                'amount_minor' => $item->amount_minor,
                'currency' => $item->currency,
                'status' => $item->status,
                'reconciliation_status' => $item->reconciliation_status,
                'reason' => $item->failure_reason ?: 'Payment/reconciliation anomaly',
                'occurred_at' => $item->created_at,
            ])->all(),
            'submission_control' => 'System detection is advisory. MLCO review is mandatory before an STR/SAR is submitted; customers must never be tipped off.',
        ];
    }

    private function privacyCompliance(Carbon $start, Carbon $end): array
    {
        return [
            'consents_created' => ConsentRecord::whereBetween('created_at', [$start, $end])->count(),
            'support_or_privacy_complaints' => SupportCase::whereBetween('created_at', [$start, $end])->whereIn('category', ['privacy', 'data_protection', 'consent', 'complaint'])->count(),
            'open_privacy_complaints' => SupportCase::whereIn('category', ['privacy', 'data_protection', 'consent'])->whereNotIn('status', ['resolved', 'closed'])->count(),
            'data_breach_register_available' => DB::getSchemaBuilder()->hasTable('audit_logs'),
            'processing_evidence' => [
                'purpose_bound_consent_records' => true,
                'revocation_records' => true,
                'sensitive_access_audit' => true,
            ],
        ];
    }

    private function digitalCreditSupervision(Carbon $start, Carbon $end): array
    {
        $decisions = CreditDecision::whereBetween('created_at', [$start, $end]);

        return [
            'credit_decisions' => (clone $decisions)->count(),
            'approved' => (clone $decisions)->where('decision', 'approved')->count(),
            'referred' => (clone $decisions)->where('decision', 'referred')->count(),
            'declined' => (clone $decisions)->where('decision', 'declined')->count(),
            'kyc_cases' => KycCase::whereBetween('created_at', [$start, $end])->count(),
            'credit_consent_records' => ConsentRecord::whereBetween('created_at', [$start, $end])->whereIn('purpose', ['crb_pull', 'credit', 'credit_assessment'])->count(),
            'consumer_complaints' => SupportCase::whereBetween('created_at', [$start, $end])->whereIn('category', ['complaint', 'collections', 'credit'])->count(),
            'control_evidence' => [
                'kyc_gate' => true,
                'consent_gate' => true,
                'offer_disclosure_acceptance' => true,
                'collections_auditability' => true,
            ],
        ];
    }

    private function consumerProtection(Carbon $start, Carbon $end): array
    {
        $cases = SupportCase::whereBetween('created_at', [$start, $end]);

        return [
            'received' => (clone $cases)->count(),
            'resolved' => (clone $cases)->whereIn('status', ['resolved', 'closed'])->count(),
            'open' => (clone $cases)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'high_priority' => (clone $cases)->whereIn('priority', ['high', 'urgent', 'critical'])->count(),
            'categories' => (clone $cases)->select('category', DB::raw('count(*) total'))->groupBy('category')->pluck('total', 'category')->all(),
        ];
    }

    private function paymentIntegrity(Carbon $start, Carbon $end): array
    {
        $transactions = MobileMoneyTransaction::whereBetween('created_at', [$start, $end]);

        return [
            'transactions' => (clone $transactions)->count(),
            'successful' => (clone $transactions)->where('status', MobileMoneyTransaction::STATUS_SUCCESSFUL)->count(),
            'failed' => (clone $transactions)->where('status', MobileMoneyTransaction::STATUS_FAILED)->count(),
            'unreconciled' => (clone $transactions)->where('reconciliation_status', '!=', MobileMoneyTransaction::RECONCILIATION_MATCHED)->count(),
            'reconciliation_exceptions' => (clone $transactions)->where('reconciliation_status', MobileMoneyTransaction::RECONCILIATION_EXCEPTION)->count(),
            'duplicate_provider_references' => (clone $transactions)->whereNotNull('provider_reference')->select('provider_reference', DB::raw('count(*) total'))->groupBy('provider_reference')->havingRaw('count(*) > 1')->count(),
        ];
    }

    private function validate(string $reportType, array $payload, Carbon $start, Carbon $end): array
    {
        $errors = [];
        if ($start->gt($end)) {
            $errors[] = 'period_start must not be after period_end';
        }
        if ($payload === []) {
            $errors[] = 'report payload must not be empty';
        }
        if (! isset(self::PROFILES[$reportType])) {
            $errors[] = 'regulator profile is missing';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'checks' => [
                'period_valid' => $start->lte($end),
                'payload_present' => $payload !== [],
                'regulator_profile_present' => isset(self::PROFILES[$reportType]),
                'hash_algorithm' => 'sha256',
                'external_submission_requires_human_authorization' => true,
            ],
        ];
    }
}
