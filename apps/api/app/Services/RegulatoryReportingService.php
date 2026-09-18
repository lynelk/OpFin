<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\CreditDecision;
use App\Models\CreditReferenceSubmission;
use App\Models\CreditTermVariation;
use App\Models\KycCase;
use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\LoanNplControl;
use App\Models\MobileMoneyTransaction;
use App\Models\SupportCase;
use App\Models\TransactionReceipt;
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
        'umra_books_and_records' => 'UMRA',
        'umra_credit_information_exchange' => 'UMRA',
        'umra_npl_recovery' => 'UMRA',
        'umra_transaction_receipts' => 'UMRA',
        'umra_term_variations' => 'UMRA',
        'umra_guarantor_controls' => 'UMRA',
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
            'umra_books_and_records' => $this->umraBooksAndRecords($start, $end),
            'umra_credit_information_exchange' => $this->creditInformationExchange($start, $end),
            'umra_npl_recovery' => $this->nplRecovery($start, $end),
            'umra_transaction_receipts' => $this->transactionReceipts($start, $end),
            'umra_term_variations' => $this->termVariations($start, $end),
            'umra_guarantor_controls' => $this->guarantorControls($start, $end),
            'consumer_protection_complaints' => $this->consumerProtection($start, $end),
            'payment_integrity_oversight' => $this->paymentIntegrity($start, $end),
            default => $this->annualAmlCompliance($start, $end),
        };

        $validation = $this->validate($reportType, $payload, $start, $end);
        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $key = ['report_type' => $reportType, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString()];

        DB::table('regulatory_report_runs')->updateOrInsert($key, [
            'regulator' => self::PROFILES[$reportType],
            'status' => $validation['valid'] ? 'validated' : 'validation_failed',
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
            $this->generate('umra_books_and_records', $monthStart, $monthEnd),
            $this->generate('umra_credit_information_exchange', $monthStart, $monthEnd),
            $this->generate('umra_npl_recovery', $monthStart, $monthEnd),
            $this->generate('umra_transaction_receipts', $monthStart, $monthEnd),
            $this->generate('umra_term_variations', $monthStart, $monthEnd),
            $this->generate('umra_guarantor_controls', $monthStart, $monthEnd),
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
                ->whereIn('purpose', [ConsentRecord::PURPOSE_CREDIT_PROCESSING, 'crb_pull', 'credit_assessment'])->count(),
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

    private function umraBooksAndRecords(Carbon $start, Carbon $end): array
    {
        $loans = Loan::withoutGlobalScopes()->whereBetween('created_at', [$start, $end]);
        $receipts = TransactionReceipt::query()->whereBetween('issued_at', [$start, $end]);
        $crb = CreditReferenceSubmission::query()->whereBetween('created_at', [$start, $end]);
        $complaints = SupportCase::query()->whereBetween('created_at', [$start, $end]);
        $variations = CreditTermVariation::query()->whereBetween('created_at', [$start, $end]);

        return [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'loan_book' => [
                'loans_created' => (clone $loans)->count(),
                'principal_disbursed_minor' => (int) (clone $loans)->sum('amount'),
                'active_loans' => Loan::withoutGlobalScopes()->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected', 'Reversed'])->count(),
                'cleared_loans' => Loan::withoutGlobalScopes()->where('status', 'Cleared')->count(),
            ],
            'credit_reference_exchange' => [
                'records_created' => (clone $crb)->count(),
                'submitted' => (clone $crb)->where('status', CreditReferenceSubmission::STATUS_SUBMITTED)->count(),
                'failed' => (clone $crb)->where('status', CreditReferenceSubmission::STATUS_FAILED)->count(),
                'positive' => (clone $crb)->where('information_type', 'positive')->count(),
                'negative' => (clone $crb)->where('information_type', 'negative')->count(),
            ],
            'transaction_receipts' => [
                'issued' => (clone $receipts)->count(),
                'delivered' => (clone $receipts)->whereNotNull('delivered_at')->count(),
            ],
            'complaints' => [
                'received' => (clone $complaints)->count(),
                'resolved' => (clone $complaints)->whereIn('status', [SupportCase::STATUS_RESOLVED, SupportCase::STATUS_CLOSED])->count(),
                'overdue_open' => SupportCase::query()->whereNull('resolved_at')->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->count(),
            ],
            'term_variations' => [
                'proposed' => (clone $variations)->count(),
                'applied' => (clone $variations)->where('status', CreditTermVariation::STATUS_APPLIED)->count(),
                'interest_rate_variations' => (clone $variations)->where('requires_umra_approval', true)->count(),
            ],
            'books_available_for_inspection' => true,
            'source_of_truth' => 'OpFin production ledger, loan, payment, receipt, complaint and credit-reference records',
        ];
    }

    private function creditInformationExchange(Carbon $start, Carbon $end): array
    {
        $records = CreditReferenceSubmission::query()->whereBetween('created_at', [$start, $end]);

        return [
            'records' => (clone $records)->count(),
            'positive' => (clone $records)->where('information_type', 'positive')->count(),
            'negative' => (clone $records)->where('information_type', 'negative')->count(),
            'submitted' => (clone $records)->where('status', CreditReferenceSubmission::STATUS_SUBMITTED)->count(),
            'pending' => (clone $records)->where('status', CreditReferenceSubmission::STATUS_PENDING)->count(),
            'failed' => (clone $records)->where('status', CreditReferenceSubmission::STATUS_FAILED)->count(),
            'overdue_pending' => (clone $records)->whereIn('status', [CreditReferenceSubmission::STATUS_PENDING, CreditReferenceSubmission::STATUS_FAILED])->where('due_at', '<', now())->count(),
            'submission_register' => (clone $records)->orderBy('id')->get([
                'id', 'loan_id', 'event_type', 'information_type', 'status', 'reporting_date',
                'payload_hash', 'provider_reference', 'due_at', 'submitted_at', 'retry_count',
            ])->toArray(),
        ];
    }

    private function nplRecovery(Carbon $start, Carbon $end): array
    {
        $controls = LoanNplControl::query()
            ->whereNotNull('non_performing_at')
            ->whereBetween('non_performing_at', [$start, $end]);

        return [
            'non_performing_loans' => (clone $controls)->count(),
            'enforcement_mode' => config('opfin.compliance.umra_npl_cap_mode', 'enforce'),
            'principal_at_npl_minor' => (int) (clone $controls)->sum('principal_at_npl_minor'),
            'default_penalty_cap_minor' => (int) (clone $controls)->sum('default_penalty_cap_minor'),
            'recoverable_interest_cap_minor' => (int) (clone $controls)->sum('recoverable_interest_cap_minor'),
            'total_recoverable_cap_minor' => (int) (clone $controls)->sum('total_recoverable_cap_minor'),
            'total_recovered_since_npl_minor' => (int) (clone $controls)->sum('total_recovered_since_npl_minor'),
            'controls' => (clone $controls)->get()->toArray(),
        ];
    }

    private function transactionReceipts(Carbon $start, Carbon $end): array
    {
        $receipts = TransactionReceipt::query()->whereBetween('issued_at', [$start, $end]);

        return [
            'issued' => (clone $receipts)->count(),
            'delivered' => (clone $receipts)->whereNotNull('delivered_at')->count(),
            'undelivered' => (clone $receipts)->whereNull('delivered_at')->count(),
            'total_amount_minor' => (int) (clone $receipts)->sum('amount_minor'),
            'receipts' => (clone $receipts)->orderBy('issued_at')->get([
                'receipt_reference', 'transaction_type', 'amount_minor', 'currency',
                'provider_reference', 'status', 'payload_hash', 'issued_at', 'delivered_at',
            ])->toArray(),
        ];
    }

    private function termVariations(Carbon $start, Carbon $end): array
    {
        $variations = CreditTermVariation::query()->whereBetween('created_at', [$start, $end]);

        return [
            'proposed' => (clone $variations)->count(),
            'requires_umra_approval' => (clone $variations)->where('requires_umra_approval', true)->count(),
            'umra_approved' => (clone $variations)->whereNotNull('umra_approved_at')->count(),
            'customer_consented' => (clone $variations)->whereNotNull('customer_consented_at')->count(),
            'applied' => (clone $variations)->whereNotNull('applied_at')->count(),
            'unauthorized_applied' => (clone $variations)->whereNotNull('applied_at')->where(function ($query) {
                $query->whereNull('customer_consented_at')
                    ->orWhere(function ($inner) {
                        $inner->where('requires_umra_approval', true)->whereNull('umra_approved_at');
                    });
            })->count(),
            'register' => (clone $variations)->orderBy('id')->get()->toArray(),
        ];
    }

    private function guarantorControls(Carbon $start, Carbon $end): array
    {
        $guarantors = LoanGuarantor::query()->whereBetween('created_at', [$start, $end]);

        return [
            'contacts_recorded' => (clone $guarantors)->count(),
            'verified' => (clone $guarantors)->where('status', LoanGuarantor::STATUS_VERIFIED)->count(),
            'electronically_consented' => (clone $guarantors)->whereNotNull('consented_at')->count(),
            'applications_over_two_contacts' => LoanGuarantor::query()
                ->select('loan_application_id', DB::raw('count(*) total'))
                ->groupBy('loan_application_id')
                ->havingRaw('count(*) > 2')
                ->count(),
            'register' => (clone $guarantors)->orderBy('id')->get([
                'loan_application_id', 'position', 'phone', 'status',
                'verification_method', 'verification_reference', 'verified_at', 'consented_at',
            ])->toArray(),
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
            'umra_books_and_records' => ['loan_book', 'credit_reference_exchange', 'transaction_receipts', 'complaints', 'books_available_for_inspection'],
            'umra_credit_information_exchange' => ['records', 'positive', 'negative', 'submitted', 'pending', 'failed', 'submission_register'],
            'umra_npl_recovery' => ['non_performing_loans', 'enforcement_mode', 'principal_at_npl_minor', 'total_recoverable_cap_minor', 'controls'],
            'umra_transaction_receipts' => ['issued', 'delivered', 'undelivered', 'receipts'],
            'umra_term_variations' => ['proposed', 'requires_umra_approval', 'umra_approved', 'customer_consented', 'applied', 'unauthorized_applied'],
            'umra_guarantor_controls' => ['contacts_recorded', 'verified', 'electronically_consented', 'applications_over_two_contacts'],
            'consumer_protection_complaints' => ['received', 'resolved', 'open', 'categories'],
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
