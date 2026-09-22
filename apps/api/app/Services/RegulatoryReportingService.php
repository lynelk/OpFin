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
    public function __construct(private readonly RegulatoryFinancialReconciliationService $financialReconciliation) {}

    public const PROFILES = [
        'fia_annual_compliance' => 'FIA',
        'fia_large_cash_transactions' => 'FIA',
        'fia_suspicious_activity_register' => 'FIA',
        'pdpo_annual_compliance' => 'PDPO',
        'umra_digital_credit_supervision' => 'UMRA',
        'umra_credit_information_exchange' => 'UMRA',
        'umra_books_and_records' => 'UMRA',
        'umra_npl_interest_controls' => 'UMRA',
        'umra_transaction_receipts' => 'UMRA',
        'umra_term_and_guarantor_controls' => 'UMRA',
        'consumer_protection_complaints' => 'UMRA',
        'payment_integrity_oversight' => 'BOU',
    ];

    public function generate(string $reportType, Carbon $start, Carbon $end): object
    {
        if (! isset(self::PROFILES[$reportType])) {
            throw new \InvalidArgumentException('Unsupported regulatory report type.');
        }

        $payload = match ($reportType) {
            'fia_large_cash_transactions' => $this->largeCashTransactions($start, $end),
            'fia_suspicious_activity_register' => $this->suspiciousActivityRegister($start, $end),
            'pdpo_annual_compliance' => $this->privacyCompliance($start, $end),
            'umra_digital_credit_supervision' => $this->digitalCreditSupervision($start, $end),
            'umra_credit_information_exchange' => $this->creditInformationExchange($start, $end),
            'umra_books_and_records' => $this->booksAndRecords($start, $end),
            'umra_npl_interest_controls' => $this->nplInterestControls($start, $end),
            'umra_transaction_receipts' => $this->transactionReceipts($start, $end),
            'umra_term_and_guarantor_controls' => $this->termAndGuarantorControls($start, $end),
            'consumer_protection_complaints' => $this->consumerProtection($start, $end),
            'payment_integrity_oversight' => $this->paymentIntegrity($start, $end),
            default => $this->annualAmlCompliance($start, $end),
        };

        $validation = $this->validate($reportType, $payload, $start, $end);
        $financialReportTypes = [
            'umra_books_and_records',
            'umra_npl_interest_controls',
            'umra_transaction_receipts',
            'payment_integrity_oversight',
            'fia_large_cash_transactions',
            'fia_suspicious_activity_register',
            'fia_annual_compliance',
        ];
        $financialEvidence = in_array($reportType, $financialReportTypes, true)
            ? $this->financialReconciliation->assess($start, $end)
            : null;
        if ($financialEvidence !== null) {
            $payload['financial_reconciliation'] = $financialEvidence;
            $validation['financial_reconciliation'] = $financialEvidence;
        }
        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $key = ['report_type' => $reportType, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString()];

        DB::table('regulatory_report_runs')->updateOrInsert($key, [
            'regulator' => self::PROFILES[$reportType],
            'status' => ! $validation['valid']
                ? 'validation_failed'
                : ($financialEvidence !== null
                    ? ($financialEvidence['passed'] ? 'financially_reconciled' : 'reconciliation_failed')
                    : 'validated'),
            'payload' => $canonical,
            'validation_results' => json_encode($validation),
            'payload_hash' => hash('sha256', $canonical),
            'generated_at' => now(),
            'validated_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        return DB::table('regulatory_report_runs')->where($key)->first();
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
            $this->generate('umra_credit_information_exchange', $monthStart, $monthEnd),
            $this->generate('umra_books_and_records', $monthStart, $monthEnd),
            $this->generate('umra_npl_interest_controls', $monthStart, $monthEnd),
            $this->generate('umra_transaction_receipts', $monthStart, $monthEnd),
            $this->generate('umra_term_and_guarantor_controls', $monthStart, $monthEnd),
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
        $exponent = max(0, (int) config('services.cpay.minor_unit_exponent', 0));
        $thresholdMinor = 20000000 * (10 ** $exponent);
        $transactions = MobileMoneyTransaction::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('currency', 'UGX')
            ->where('amount_minor', '>=', $thresholdMinor)
            ->get(['id', 'transaction_id', 'user_id', 'direction', 'amount_minor', 'currency', 'provider', 'provider_reference', 'created_at']);

        return [
            'threshold_ugx' => 20000000,
            'threshold_minor' => $thresholdMinor,
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
            'submission_control' => 'Candidate register only; MLCO/accountable-person authorization is required before submission.',
        ];
    }

    private function suspiciousActivityRegister(Carbon $start, Carbon $end): array
    {
        $candidates = MobileMoneyTransaction::query()
            ->whereBetween('created_at', [$start, $end])
            ->where(fn ($query) => $query
                ->where('reconciliation_status', MobileMoneyTransaction::RECONCILIATION_EXCEPTION)
                ->orWhere('retry_count', '>=', 3)
                ->orWhereNotNull('failure_reason'))
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
            'submission_control' => 'System detection is advisory. MLCO review is mandatory before STR/SAR submission; no customer tip-off is permitted.',
        ];
    }

    private function privacyCompliance(Carbon $start, Carbon $end): array
    {
        $breaches = DB::table('data_breach_incidents')->whereBetween('detected_at', [$start, $end]);

        return [
            'consents_created' => ConsentRecord::whereBetween('created_at', [$start, $end])->count(),
            'privacy_related_complaints' => SupportCase::whereBetween('created_at', [$start, $end])
                ->whereIn('category', ['privacy', 'data_protection', 'consent', 'complaint'])->count(),
            'open_privacy_complaints' => SupportCase::whereIn('category', ['privacy', 'data_protection', 'consent'])
                ->whereNotIn('status', ['resolved', 'closed'])->count(),
            'data_breach_incidents' => (clone $breaches)->count(),
            'affected_data_subjects' => (int) (clone $breaches)->sum('affected_subjects'),
            'contained_breach_incidents' => (clone $breaches)->whereNotNull('contained_at')->count(),
            'pdpo_notified_breach_incidents' => (clone $breaches)->whereNotNull('notified_pdpo_at')->count(),
            'unnotified_breach_incidents' => (clone $breaches)->whereNull('notified_pdpo_at')->count(),
            'open_breach_incidents' => (clone $breaches)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'security_audit_log_available' => DB::getSchemaBuilder()->hasTable('audit_logs'),
            'processing_evidence' => [
                'purpose_bound_consent_records' => true,
                'revocation_records' => true,
                'sensitive_access_audit' => true,
                'data_breach_register' => true,
                'containment_and_remediation_fields' => true,
                'pdpo_notification_tracking' => true,
            ],
            'submission_control' => 'Generated evidence pack must be reviewed against PDPO portal questions before filing.',
        ];
    }

    private function digitalCreditSupervision(Carbon $start, Carbon $end): array
    {
        $decisions = CreditDecision::whereBetween('created_at', [$start, $end]);

        return [
            'credit_decisions' => (clone $decisions)->count(),
            'approved' => (clone $decisions)->where('status', CreditDecision::STATUS_APPROVED)->count(),
            'referred' => (clone $decisions)->where('status', CreditDecision::STATUS_REFERRED)->count(),
            'declined' => (clone $decisions)->where('status', CreditDecision::STATUS_DECLINED)->count(),
            'kyc_cases' => KycCase::whereBetween('created_at', [$start, $end])->count(),
            'credit_consent_records' => ConsentRecord::whereBetween('created_at', [$start, $end])
                ->whereIn('purpose', [
                    ConsentRecord::PURPOSE_CREDIT_PROCESSING,
                    ConsentRecord::PURPOSE_CREDIT_INFORMATION_REPORTING,
                    'crb_pull',
                    'credit_assessment',
                ])->count(),
            'consumer_complaints' => SupportCase::whereBetween('created_at', [$start, $end])
                ->whereIn('category', ['complaint', 'collections', 'credit'])->count(),
            'control_evidence' => [
                'kyc_gate' => true,
                'consent_gate' => true,
                'offer_disclosure_acceptance' => true,
                'collections_auditability' => true,
            ],
        ];
    }

    private function creditInformationExchange(Carbon $start, Carbon $end): array
    {
        $records = DB::table('credit_information_reports')->whereBetween('created_at', [$start, $end]);

        return [
            'generated' => (clone $records)->count(),
            'positive' => (clone $records)->where('report_type', 'positive')->count(),
            'negative' => (clone $records)->where('report_type', 'negative')->count(),
            'submitted' => (clone $records)->where('status', 'submitted')->count(),
            'pending' => (clone $records)->where('status', 'pending')->count(),
            'failed' => (clone $records)->where('status', 'failed')->count(),
            'overdue_for_submission' => (clone $records)
                ->whereIn('status', ['pending', 'failed'])
                ->where('due_at', '<', now())
                ->count(),
            'register' => (clone $records)
                ->orderBy('id')
                ->get([
                    'id', 'user_id', 'loan_id', 'report_type', 'event_type', 'status',
                    'provider', 'provider_reference', 'payload_hash', 'due_at', 'submitted_at',
                    'attempts', 'failure_reason', 'created_at',
                ])
                ->all(),
            'control_evidence' => [
                'positive_and_negative_reporting' => true,
                'payload_hashing' => true,
                'idempotent_event_key' => true,
                'submission_due_days' => (int) config('opfin.regulatory.credit_reporting_due_days', 30),
            ],
        ];
    }

    private function booksAndRecords(Carbon $start, Carbon $end): array
    {
        $loans = DB::table('loans')->whereBetween('created_at', [$start, $end]);
        $payments = DB::table('mobile_money_transactions')->whereBetween('created_at', [$start, $end]);
        $ledger = DB::table('ledger_transactions')->whereBetween('posted_at', [$start, $end]);

        return [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'loan_register' => [
                'count' => (clone $loans)->count(),
                'principal_minor' => (int) (clone $loans)->sum('amount'),
                'records' => (clone $loans)->orderBy('id')->get([
                    'id', 'user_id', 'loan_product_id', 'loan_product_term_id', 'institution_id',
                    'credit_offer_id', 'amount', 'status', 'disbursed_at', 'duration',
                    'repayment_amount', 'repayment_start_date', 'non_performing_at',
                    'principal_at_npl_minor', 'default_interest_accrued_minor',
                    'default_interest_cap_minor', 'npl_recovery_cap_minor', 'created_at',
                ])->all(),
            ],
            'payment_register' => [
                'count' => (clone $payments)->count(),
                'records' => (clone $payments)->orderBy('id')->get([
                    'id', 'transaction_id', 'loan_id', 'user_id', 'provider', 'direction',
                    'amount_minor', 'currency', 'internal_reference', 'provider_reference',
                    'status', 'reconciliation_status', 'created_at',
                ])->all(),
            ],
            'ledger_register' => [
                'count' => (clone $ledger)->count(),
                'records' => (clone $ledger)->orderBy('id')->get([
                    'id', 'reference', 'event_type', 'currency', 'source_type', 'source_id', 'posted_at',
                ])->all(),
            ],
            'receipt_count' => DB::table('transaction_receipts')->whereBetween('issued_at', [$start, $end])->count(),
            'complaint_count' => DB::table('support_cases')->whereBetween('created_at', [$start, $end])->count(),
            'credit_information_report_count' => DB::table('credit_information_reports')->whereBetween('created_at', [$start, $end])->count(),
            'term_change_count' => DB::table('credit_term_change_requests')->whereBetween('created_at', [$start, $end])->count(),
            'guarantor_contact_count' => DB::table('guarantor_contacts')->whereBetween('created_at', [$start, $end])->count(),
            'submission_control' => 'Generated from OpFin system-of-record tables; officer review is required before furnishing books or returns externally.',
        ];
    }

    private function nplInterestControls(Carbon $start, Carbon $end): array
    {
        $loans = DB::table('loans')
            ->whereNotNull('non_performing_at')
            ->whereBetween('non_performing_at', [$start, $end]);

        return [
            'non_performing_loans' => (clone $loans)->count(),
            'default_interest_cap_breaches' => (clone $loans)
                ->whereColumn('default_interest_accrued_minor', '>', 'default_interest_cap_minor')
                ->count(),
            'enforcement_enabled' => (bool) config('opfin.regulatory.enforce_umra_npl_cap', true),
            'register' => (clone $loans)->orderBy('id')->get([
                'id', 'user_id', 'amount', 'status', 'non_performing_at',
                'principal_at_npl_minor', 'initial_interest_minor',
                'default_interest_accrued_minor', 'default_interest_cap_minor',
                'npl_recovery_cap_minor', 'umra_npl_cap_enforcement_enabled',
                'npl_policy_checked_at',
            ])->all(),
        ];
    }

    private function transactionReceipts(Carbon $start, Carbon $end): array
    {
        $receipts = DB::table('transaction_receipts')->whereBetween('issued_at', [$start, $end]);

        return [
            'receipts_issued' => (clone $receipts)->count(),
            'disbursement_receipts' => (clone $receipts)->whereIn('receipt_type', ['loan_disbursement', 'disbursement_reversal'])->count(),
            'repayment_receipts' => (clone $receipts)->where('receipt_type', 'loan_repayment')->count(),
            'register' => (clone $receipts)->orderBy('id')->get([
                'id', 'receipt_number', 'receipt_type', 'user_id', 'loan_id',
                'mobile_money_transaction_id', 'amount_minor', 'currency', 'status',
                'receipt_hash', 'issued_at',
            ])->all(),
        ];
    }

    private function termAndGuarantorControls(Carbon $start, Carbon $end): array
    {
        $changes = DB::table('credit_term_change_requests')->whereBetween('created_at', [$start, $end]);
        $guarantors = DB::table('guarantor_contacts')->whereBetween('created_at', [$start, $end]);

        return [
            'term_changes' => [
                'total' => (clone $changes)->count(),
                'approved' => (clone $changes)->whereIn('status', ['approved', 'applied'])->count(),
                'applied' => (clone $changes)->where('status', 'applied')->count(),
                'register' => (clone $changes)->orderBy('id')->get([
                    'id', 'loan_product_term_id', 'status', 'requested_by', 'approved_by',
                    'reason', 'customer_consent_required', 'umra_approval_reference',
                    'umra_approved_at', 'approved_at', 'effective_at', 'applied_at',
                ])->all(),
            ],
            'guarantors' => [
                'total' => (clone $guarantors)->count(),
                'confirmed' => (clone $guarantors)->where('status', 'confirmed')->count(),
                'rejected' => (clone $guarantors)->where('status', 'rejected')->count(),
                'pending' => (clone $guarantors)->where('status', 'pending')->count(),
                'applications_over_two_contacts' => DB::table('guarantor_contacts')
                    ->select('loan_application_id')
                    ->groupBy('loan_application_id')
                    ->havingRaw('count(*) > 2')
                    ->count(),
                'register' => (clone $guarantors)->orderBy('id')->get([
                    'id', 'loan_application_id', 'borrower_user_id', 'name', 'phone',
                    'relationship', 'status', 'verification_channel', 'confirmed_at', 'rejected_at',
                ])->all(),
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
            'sla_breached' => (clone $cases)->where('sla_breached', true)->count(),
            'overdue_open' => (clone $cases)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->whereNotNull('regulatory_due_at')
                ->where('regulatory_due_at', '<', now())
                ->count(),
            'resolution_target_days' => (int) config('opfin.regulatory.complaint_resolution_days', 30),
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
            'duplicate_provider_references' => (clone $transactions)
                ->whereNotNull('provider_reference')
                ->select('provider_reference')
                ->groupBy('provider_reference')
                ->havingRaw('count(*) > 1')
                ->count(),
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

        $requiredKeys = match ($reportType) {
            'fia_large_cash_transactions' => ['threshold_ugx', 'transactions', 'submission_control'],
            'fia_suspicious_activity_register' => ['candidates', 'submission_control'],
            'pdpo_annual_compliance' => ['consents_created', 'privacy_related_complaints', 'data_breach_incidents', 'affected_data_subjects', 'processing_evidence'],
            'umra_digital_credit_supervision' => ['credit_decisions', 'approved', 'referred', 'declined', 'kyc_cases', 'credit_consent_records'],
            'umra_credit_information_exchange' => ['generated', 'positive', 'negative', 'submitted', 'pending', 'register', 'control_evidence'],
            'umra_books_and_records' => ['period', 'loan_register', 'payment_register', 'ledger_register', 'receipt_count', 'complaint_count'],
            'umra_npl_interest_controls' => ['non_performing_loans', 'default_interest_cap_breaches', 'enforcement_enabled', 'register'],
            'umra_transaction_receipts' => ['receipts_issued', 'disbursement_receipts', 'repayment_receipts', 'register'],
            'umra_term_and_guarantor_controls' => ['term_changes', 'guarantors'],
            'consumer_protection_complaints' => ['received', 'resolved', 'open', 'sla_breached', 'categories'],
            'payment_integrity_oversight' => ['transactions', 'successful', 'failed', 'unreconciled', 'reconciliation_exceptions'],
            default => ['kyc_cases', 'payment_transactions', 'control_evidence', 'submission_control'],
        };

        $missingKeys = array_values(array_filter($requiredKeys, fn (string $key) => ! array_key_exists($key, $payload)));
        if ($missingKeys !== []) {
            $errors[] = 'required report fields are missing: '.implode(', ', $missingKeys);
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'checks' => [
                'period_valid' => $start->lte($end),
                'payload_present' => $payload !== [],
                'regulator_profile_present' => isset(self::PROFILES[$reportType]),
                'required_fields_present' => $missingKeys === [],
                'required_fields' => $requiredKeys,
                'hash_algorithm' => 'sha256',
                'external_submission_requires_human_authorization' => true,
            ],
        ];
    }
}
