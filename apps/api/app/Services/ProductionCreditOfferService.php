<?php

namespace App\Services;

use App\Models\CreditDecision;
use App\Models\CreditOffer;
use App\Models\CreditRepaymentScheduleItem;
use App\Models\CustomerWallet;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\MobileMoneyTransaction;
use App\Models\User;
use App\Services\MobileMoney\MobileMoneyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductionCreditOfferService
{
    public function __construct(
        private readonly MobileMoneyService $mobileMoney,
        private readonly ProductionLoanLedgerService $loanLedger,
        private readonly CreditEconomicsService $economics,
        private readonly AffordabilityService $affordability,
        private readonly AuditLogger $auditLogger,
        private readonly CreditReferenceReportingService $creditReporting,
        private readonly TransactionReceiptService $receipts,
    ) {}

    public function createOffer(LoanApplication $application, User $actor, array $pricing): CreditOffer
    {
        $application->loadMissing(['loanProductTerm', 'creditDecision']);
        $decision = $application->creditDecision;
        if (! $decision || $decision->status !== CreditDecision::STATUS_APPROVED) {
            throw new InvalidArgumentException('An approved production credit decision is required before an offer can be generated.');
        }
        if ($decision->approved_amount_minor <= 0) {
            throw new InvalidArgumentException('The approved amount must be positive before an offer can be generated.');
        }

        $term = $application->loanProductTerm;
        if (! $term) {
            throw new InvalidArgumentException('The selected product term is unavailable.');
        }
        $principalMinor = (int) $decision->approved_amount_minor;
        $quote = $this->economics->quoteForApplication($application, $principalMinor, $pricing);
        $affordability = $this->affordability->assess($application->user()->firstOrFail(), $application, $principalMinor, $quote);
        if ($affordability['status'] !== 'eligible') {
            throw new InvalidArgumentException('The exact offer economics do not satisfy the current affordability policy.');
        }

        $durationDays = (int) $quote['duration_days'];
        $ratePercent = (float) $quote['interest_rate_percent'];
        $interestMinor = (int) $quote['interest_amount_minor'];
        $accessFeeMinor = (int) $quote['access_fee_minor'];
        $disbursementFeeMinor = (int) $quote['disbursement_fee_minor'];
        $feesMinor = (int) $quote['fees_minor'];
        $feeTreatment = (string) $quote['fee_treatment'];
        $netDisbursementMinor = (int) $quote['net_disbursement_minor'];
        $totalRepaymentMinor = (int) $quote['total_repayment_minor'];
        $totalCostOfCreditMinor = (int) $quote['total_cost_of_credit_minor'];
        $defaultInterestRate = max(0, (float) ($term->default_interest_rate ?? 0));
        $defaultInterestRules = (array) (($quote['policy']['rules']['default_interest'] ?? []));
        $defaultInterestCapMinor = null;
        if (isset($defaultInterestRules['cap_percent_of_initial_interest'])) {
            $defaultInterestCapMinor = (int) floor(
                $interestMinor * ((float) $defaultInterestRules['cap_percent_of_initial_interest'] / 100)
            );
        }
        $complaintsProcedure = [
            'resolution_target_days' => (int) config('opfin.regulatory.complaint_resolution_days', 30),
            'email' => config('opfin.regulatory.complaints_email'),
            'phone' => config('opfin.regulatory.complaints_phone'),
            'url' => config('opfin.regulatory.complaints_url'),
        ];
        $regulatedIdentity = [
            'licensed_entity_name' => config('opfin.regulatory.licensed_entity_name'),
            'trading_name' => config('opfin.regulatory.licensed_trading_name', 'OpFin'),
            'umra_license_number' => config('opfin.regulatory.umra_license_number'),
            'business_address' => config('opfin.regulatory.business_address'),
        ];
        $expiresInMinutes = max(5, min((int) ($pricing['expires_in_minutes'] ?? 1440), 10080));

        return DB::transaction(function () use (
            $application, $actor, $decision, $term, $principalMinor, $interestMinor, $feesMinor,
            $accessFeeMinor, $disbursementFeeMinor, $netDisbursementMinor, $totalRepaymentMinor,
            $durationDays, $ratePercent, $feeTreatment, $expiresInMinutes, $quote,
            $totalCostOfCreditMinor, $defaultInterestRate, $defaultInterestCapMinor, $defaultInterestRules, $complaintsProcedure, $regulatedIdentity,
        ) {
            CreditOffer::query()->where('loan_application_id', $application->id)
                ->where('status', CreditOffer::STATUS_OFFERED)->where('expires_at', '<=', now())
                ->update(['status' => CreditOffer::STATUS_EXPIRED]);

            $activeOfferExists = CreditOffer::query()->where('loan_application_id', $application->id)
                ->whereIn('status', [CreditOffer::STATUS_OFFERED, CreditOffer::STATUS_ACCEPTED, CreditOffer::STATUS_DISBURSEMENT_PENDING, CreditOffer::STATUS_DISBURSED])
                ->exists();
            if ($activeOfferExists) {
                throw new InvalidArgumentException('This application already has an active or completed offer.');
            }

            $version = ((int) CreditOffer::query()->where('loan_application_id', $application->id)->max('version')) + 1;
            $offeredAt = now();
            $offer = CreditOffer::create([
                'loan_application_id' => $application->id,
                'credit_decision_id' => $decision->id,
                'user_id' => $application->user_id,
                'institution_id' => $application->institution_id,
                'created_by' => $actor->id,
                'offer_reference' => 'OPF-OFR-'.Str::upper(Str::random(16)),
                'version' => $version,
                'status' => CreditOffer::STATUS_OFFERED,
                'currency' => (string) config('services.mobile_money.currency', 'UGX'),
                'principal_amount_minor' => $principalMinor,
                'interest_amount_minor' => $interestMinor,
                'fees_minor' => $feesMinor,
                'net_disbursement_minor' => $netDisbursementMinor,
                'total_repayment_minor' => $totalRepaymentMinor,
                'duration_days' => $durationDays,
                'interest_rate_percent' => $ratePercent,
                'interest_cycle' => (string) $term->interest_cycle,
                'interest_type' => (string) $term->interest_type,
                'repayment_frequency' => (string) $term->repayment_frequency,
                'fee_treatment' => $feeTreatment,
                'policy_version' => (string) $decision->policy_version,
                'pricing_snapshot' => [
                    'algorithm_version' => $quote['algorithm_version'],
                    'product_term_id' => $term->id,
                    'configured_rate_percent' => $ratePercent,
                    'configured_interest_cycle' => (string) $term->interest_cycle,
                    'access_fee_minor' => $accessFeeMinor,
                    'disbursement_fee_minor' => $disbursementFeeMinor,
                    'fee_treatment' => $feeTreatment,
                    'schedule' => $quote['schedule'],
                    'regulatory_policy' => $quote['policy'],
                    'simple_annualised_cost_percent' => $quote['simple_annualised_cost_percent'],
                    'fees_accrue_at' => 'offer acceptance/disbursement according to fee treatment',
                    'default_interest_rate_percent' => $defaultInterestRate,
                    'default_interest_cycle' => (string) ($term->default_interest_cycle ?? 'monthly'),
                    'default_interest_rules' => $defaultInterestRules,
                ],
                'disclosure_snapshot' => [
                    'currency' => (string) config('services.mobile_money.currency', 'UGX'),
                    'principal_amount_minor' => $principalMinor,
                    'interest_amount_minor' => $interestMinor,
                    'fees_minor' => $feesMinor,
                    'net_disbursement_minor' => $netDisbursementMinor,
                    'total_repayment_minor' => $totalRepaymentMinor,
                    'duration_days' => $durationDays,
                    'repayment_frequency' => (string) $term->repayment_frequency,
                    'fee_treatment' => $feeTreatment,
                    'interest_rate_percent' => $ratePercent,
                    'interest_type' => (string) $term->interest_type,
                    'interest_cycle' => (string) $term->interest_cycle,
                    'interest_calculation' => 'Interest and instalments are calculated by the canonical credit-economics service under the active effective-dated regulatory pricing policy. Exact monetary amounts shown in this offer control.',
                    'fee_breakdown' => [
                        'access_fee_minor' => $accessFeeMinor,
                        'disbursement_fee_minor' => $disbursementFeeMinor,
                        'total_fees_minor' => $feesMinor,
                        'treatment' => $feeTreatment,
                    ],
                    'total_cost_of_credit_minor' => $totalCostOfCreditMinor,
                    'first_payment_due_days_after_disbursement' => $this->frequencyDays((string) $term->repayment_frequency),
                    'final_payment_due_days_after_disbursement' => $durationDays,
                    'simple_annualised_cost_percent' => $quote['simple_annualised_cost_percent'],
                    'schedule' => $quote['schedule'],
                    'regulatory_policy' => [
                        'code' => $quote['policy']['code'],
                        'version' => $quote['policy']['version'],
                        'licence_class' => $quote['policy']['licence_class'],
                        'effective_from' => $quote['policy']['effective_from'],
                    ],
                    'default_and_penalty_terms' => [
                        'default_interest_rate_percent' => $defaultInterestRate,
                        'default_interest_cycle' => (string) ($term->default_interest_cycle ?? 'monthly'),
                        'default_interest_cap_minor' => $defaultInterestCapMinor,
                        'policy_rules' => $defaultInterestRules,
                        'npl_recovery_cap_tracking' => true,
                    ],
                    'complaints_procedure' => $complaintsProcedure,
                    'regulated_provider' => $regulatedIdentity,
                    'variation_control' => [
                        'accepted_offer_is_immutable' => true,
                        'interest_rate_change_requires_umra_approval' => true,
                        'customer_consent_required_for_credit_term_variation' => true,
                    ],
                    'credit_information_reporting' => [
                        'positive_and_negative_information_may_be_reported' => true,
                        'consent_required_before_external_submission' => true,
                        'purpose' => 'Credit-reference reporting and responsible lending.',
                    ],
                ],
                'offered_at' => $offeredAt,
                'expires_at' => $offeredAt->copy()->addMinutes($expiresInMinutes),
            ]);

            $application->update(['status' => 'Offer Ready']);
            $this->auditLogger->record('credit.offer.created', $actor, $offer, [
                'offer_reference' => $offer->offer_reference,
                'policy_version' => $offer->policy_version,
                'principal_amount_minor' => $principalMinor,
                'total_repayment_minor' => $totalRepaymentMinor,
            ]);

            return $offer;
        });
    }

    public function acceptOffer(CreditOffer $offer, User $user, array $acceptanceMetadata = []): array
    {
        $offer = DB::transaction(function () use ($offer, $user, $acceptanceMetadata) {
            $locked = CreditOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($locked->user_id !== $user->id) {
                throw new InvalidArgumentException('This offer does not belong to the authenticated customer.');
            }
            if ($locked->status === CreditOffer::STATUS_DISBURSED || $locked->status === CreditOffer::STATUS_DISBURSEMENT_PENDING) {
                return $locked;
            }
            if ($locked->status !== CreditOffer::STATUS_OFFERED) {
                throw new InvalidArgumentException('This offer is no longer available for acceptance.');
            }
            if ($locked->expires_at->isPast()) {
                $locked->update(['status' => CreditOffer::STATUS_EXPIRED]);
                throw new InvalidArgumentException('This offer has expired.');
            }
            $locked->update(['status' => CreditOffer::STATUS_DISBURSEMENT_PENDING, 'accepted_at' => now(), 'acceptance_metadata' => $acceptanceMetadata]);
            $locked->application()->update(['status' => 'Accepted']);
            $this->auditLogger->record('credit.offer.accepted', $user, $locked, [
                'offer_reference' => $locked->offer_reference,
                'disclosed_total_repayment_minor' => $locked->total_repayment_minor,
            ]);

            return $locked->fresh();
        });

        $walletId = isset($acceptanceMetadata['wallet_id']) ? (int) $acceptanceMetadata['wallet_id'] : null;
        $walletQuery = CustomerWallet::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('verified_at');

        $wallet = $walletId
            ? (clone $walletQuery)->whereKey($walletId)->first()
            : (clone $walletQuery)->where('is_default_disbursement', true)->first();

        if ($walletId && ! $wallet) {
            throw new InvalidArgumentException('Choose a verified wallet that belongs to your OpFin profile.');
        }

        $wallet ??= (clone $walletQuery)->orderByDesc('is_default_disbursement')->first();
        $disbursementPhone = $wallet?->msisdn ?? $user->phone;

        $existing = MobileMoneyTransaction::query()->where('credit_offer_id', $offer->id)
            ->where('direction', MobileMoneyTransaction::DIRECTION_DISBURSEMENT)->latest()->first();
        $transaction = $existing ?: $this->mobileMoney->disburse([
            'credit_offer_id' => $offer->id,
            'user_id' => $offer->user_id,
            'institution_id' => $offer->institution_id,
            'amount_minor' => $offer->net_disbursement_minor,
            'currency' => $offer->currency,
            'phone' => $disbursementPhone,
            'idempotency_key' => "credit-offer:{$offer->id}:disbursement:v{$offer->version}",
            'internal_reference' => $offer->offer_reference,
            'description' => 'OpFin credit offer disbursement',
            'purpose' => 'credit_offer_disbursement',
        ]);

        $loan = $this->syncDisbursementState($transaction);

        return ['offer' => $offer->fresh(), 'mobile_money' => $transaction->fresh(), 'loan' => $loan];
    }

    public function syncDisbursementState(MobileMoneyTransaction $transaction): ?Loan
    {
        if (! $transaction->credit_offer_id || $transaction->direction !== MobileMoneyTransaction::DIRECTION_DISBURSEMENT) {
            return null;
        }

        if ($transaction->status === MobileMoneyTransaction::STATUS_REVERSED) {
            return $this->handleDisbursementReversal($transaction);
        }

        if ($transaction->status === MobileMoneyTransaction::STATUS_FAILED) {
            CreditOffer::query()->whereKey($transaction->credit_offer_id)->update(['status' => CreditOffer::STATUS_DISBURSEMENT_FAILED]);
            $transaction->update([
                'accounting_status' => MobileMoneyTransaction::ACCOUNTING_NOT_REQUIRED,
                'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
            ]);

            return null;
        }
        if ($transaction->status !== MobileMoneyTransaction::STATUS_SUCCESSFUL) {
            return null;
        }

        return DB::transaction(function () use ($transaction) {
            $lockedTransaction = MobileMoneyTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $offer = CreditOffer::query()->lockForUpdate()->findOrFail($lockedTransaction->credit_offer_id);
            $existing = Loan::query()->where('credit_offer_id', $offer->id)->first();
            if ($existing) {
                if ((int) $lockedTransaction->loan_id !== (int) $existing->id) {
                    $lockedTransaction->update(['loan_id' => $existing->id]);
                }
                $this->loanLedger->postCreditOfferDisbursement($lockedTransaction->fresh(), $existing, $offer);
                $lockedTransaction->update([
                    'accounting_status' => MobileMoneyTransaction::ACCOUNTING_POSTED,
                    'accounting_posted_at' => now(),
                    'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
                ]);
                DB::afterCommit(function () use ($lockedTransaction, $existing) {
                    $this->receipts->issue(MobileMoneyTransaction::findOrFail($lockedTransaction->id), 'loan_disbursement');
                    $this->creditReporting->queueLoanEvent(Loan::findOrFail($existing->id), 'origination');
                });

                return $existing;
            }

            $application = LoanApplication::query()->findOrFail($offer->loan_application_id);
            $disbursedAt = now();
            $firstDueDate = $disbursedAt->copy()->addDays($this->frequencyDays($offer->repayment_frequency));
            $loan = Loan::withoutEvents(function () use ($application, $offer, $firstDueDate, $disbursedAt) {
                $loan = new Loan;
                $loan->forceFill([
                    'user_id' => $application->user_id,
                    'loan_product_id' => $application->loan_product_id,
                    'loan_product_term_id' => $application->loan_product_term_id,
                    'institution_id' => $application->institution_id,
                    'loan_application_id' => $application->id,
                    'credit_offer_id' => $offer->id,
                    'amount' => $offer->principal_amount_minor,
                    'status' => 'Active',
                    'reason' => $application->reason,
                    'disbursed_at' => $disbursedAt,
                    'duration' => $offer->duration_days,
                    'repayment_amount' => $offer->total_repayment_minor,
                    'repayment_start_date' => $firstDueDate->toDateString(),
                    'umra_npl_cap_enforcement_enabled' => (bool) config('opfin.regulatory.enforce_umra_npl_cap', true),
                    'initial_interest_minor' => $offer->interest_amount_minor,
                    'default_interest_cap_minor' => $offer->pricing_snapshot['default_interest_rules']['cap_percent_of_initial_interest'] ?? null
                        ? (int) floor(
                            (int) $offer->interest_amount_minor
                            * ((float) $offer->pricing_snapshot['default_interest_rules']['cap_percent_of_initial_interest'] / 100)
                        )
                        : null,
                    'default_interest_policy_snapshot' => $offer->pricing_snapshot['regulatory_policy'] ?? null,
                ]);
                $loan->save();

                return $loan;
            });

            $this->createExactSchedule($loan, $offer, $disbursedAt);
            $lockedTransaction->update(['loan_id' => $loan->id]);
            $this->loanLedger->postCreditOfferDisbursement($lockedTransaction->fresh(), $loan, $offer);
            $offer->update(['status' => CreditOffer::STATUS_DISBURSED]);
            $application->update(['status' => 'Disbursed', 'disbursed_at' => $disbursedAt]);
            $lockedTransaction->update([
                    'accounting_status' => MobileMoneyTransaction::ACCOUNTING_POSTED,
                    'accounting_posted_at' => now(),
                    'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
                ]);

            $this->auditLogger->record('credit.disbursement.fulfilled', null, $loan, [
                'offer_reference' => $offer->offer_reference,
                'mobile_money_transaction_id' => $lockedTransaction->id,
                'provider_reference' => $lockedTransaction->provider_reference,
                'ledger_reference' => 'loan.disbursement:credit-offer:'.$offer->offer_reference,
            ]);
            DB::afterCommit(function () use ($lockedTransaction, $loan) {
                $this->receipts->issue(MobileMoneyTransaction::findOrFail($lockedTransaction->id), 'loan_disbursement');
                $this->creditReporting->queueLoanEvent(Loan::findOrFail($loan->id), 'origination');
            });

            return $loan;
        });
    }

    private function handleDisbursementReversal(MobileMoneyTransaction $transaction): ?Loan
    {
        return DB::transaction(function () use ($transaction) {
            $lockedTransaction = MobileMoneyTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $offer = CreditOffer::query()->lockForUpdate()->findOrFail($lockedTransaction->credit_offer_id);
            $loan = Loan::query()->where('credit_offer_id', $offer->id)->lockForUpdate()->first();
            $offer->update(['status' => CreditOffer::STATUS_DISBURSEMENT_FAILED]);

            if (! $loan) {
                $originalReference = 'loan.disbursement:credit-offer:'.$offer->offer_reference;
                if (DB::table('ledger_transactions')->where('reference', $originalReference)->exists()) {
                    $lockedTransaction->update([
                        'accounting_status' => MobileMoneyTransaction::ACCOUNTING_EXCEPTION,
                        'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_EXCEPTION,
                        'failure_reason' => 'Provider reversal is linked to an original credit ledger posting but no loan record can be found.',
                    ]);
                } else {
                    $lockedTransaction->update([
                    'accounting_status' => MobileMoneyTransaction::ACCOUNTING_POSTED,
                    'accounting_posted_at' => now(),
                    'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
                ]);
                }

                return null;
            }

            $schedule = CreditRepaymentScheduleItem::query()->where('loan_id', $loan->id)->lockForUpdate()->get();
            $totalDue = (int) $schedule->sum('total_due_minor');
            $totalOutstanding = (int) $schedule->sum('total_outstanding_minor');
            if ($totalOutstanding !== $totalDue) {
                $lockedTransaction->update([
                    'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_EXCEPTION,
                    'failure_reason' => 'Disbursement reversed after repayment activity; automatic economic reversal is blocked and operations review is required.',
                ]);
                $loan->update(['status' => 'Exception']);
                $this->auditLogger->record('credit.disbursement.reversal_exception', null, $loan, [
                    'credit_offer_id' => $offer->id,
                    'mobile_money_transaction_id' => $lockedTransaction->id,
                    'total_due_minor' => $totalDue,
                    'total_outstanding_minor' => $totalOutstanding,
                ]);

                return $loan;
            }

            $this->loanLedger->reverseCreditOfferDisbursement($lockedTransaction, $loan, $offer);
            CreditRepaymentScheduleItem::query()->where('loan_id', $loan->id)->update([
                'principal_outstanding_minor' => 0,
                'interest_outstanding_minor' => 0,
                'fees_outstanding_minor' => 0,
                'total_outstanding_minor' => 0,
                'status' => CreditRepaymentScheduleItem::STATUS_VOIDED,
                'paid_at' => null,
            ]);
            $loan->update(['status' => 'Reversed']);
            LoanApplication::query()->whereKey($offer->loan_application_id)->update(['status' => 'Disbursement Reversed']);
            $lockedTransaction->update([
                    'accounting_status' => MobileMoneyTransaction::ACCOUNTING_POSTED,
                    'accounting_posted_at' => now(),
                    'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
                ]);

            $this->auditLogger->record('credit.disbursement.reversed', null, $loan, [
                'credit_offer_id' => $offer->id,
                'mobile_money_transaction_id' => $lockedTransaction->id,
                'provider_reference' => $lockedTransaction->provider_reference,
            ]);
            DB::afterCommit(function () use ($lockedTransaction, $loan) {
                $this->receipts->issue(MobileMoneyTransaction::findOrFail($lockedTransaction->id), 'disbursement_reversal');
                $this->creditReporting->queueLoanEvent(Loan::findOrFail($loan->id), 'correction');
            });

            return $loan->fresh();
        });
    }

    private function createExactSchedule(Loan $loan, CreditOffer $offer, $anchor): void
    {
        $schedule = (array) ($offer->pricing_snapshot['schedule'] ?? []);
        if ($schedule === []) {
            throw new InvalidArgumentException('The accepted production offer is missing its canonical repayment schedule snapshot.');
        }

        foreach ($schedule as $row) {
            $principal = (int) ($row['principal_minor'] ?? 0);
            $interest = (int) ($row['interest_minor'] ?? 0);
            $fees = (int) ($row['fees_minor'] ?? 0);
            $total = (int) ($row['total_due_minor'] ?? ($principal + $interest + $fees));
            $dueOffsetDays = (int) ($row['due_offset_days'] ?? 0);
            if ($principal < 0 || $interest < 0 || $fees < 0 || $total <= 0 || $dueOffsetDays <= 0) {
                throw new InvalidArgumentException('Canonical repayment schedule contains invalid monetary or due-date data.');
            }

            CreditRepaymentScheduleItem::create([
                'loan_id' => $loan->id,
                'credit_offer_id' => $offer->id,
                'installment_number' => (int) $row['installment_number'],
                'due_date' => $anchor->copy()->addDays($dueOffsetDays)->toDateString(),
                'principal_minor' => $principal,
                'interest_minor' => $interest,
                'fees_minor' => $fees,
                'total_due_minor' => $total,
                'principal_outstanding_minor' => $principal,
                'interest_outstanding_minor' => $interest,
                'fees_outstanding_minor' => $fees,
                'total_outstanding_minor' => $total,
                'status' => CreditRepaymentScheduleItem::STATUS_DUE,
            ]);
        }
    }

    private function allocate(int $total, int $count, int $position): int
    {
        if ($total < 0 || $count <= 0 || $position < 1 || $position > $count) {
            throw new InvalidArgumentException('Invalid production monetary allocation parameters.');
        }
        $base = intdiv($total, $count);

        return $position === $count ? $base + ($total % $count) : $base;
    }

}
