<?php

namespace App\Services;

use App\Models\CreditDecision;
use App\Models\CreditOffer;
use App\Models\CreditRepaymentScheduleItem;
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
        private readonly AuditLogger $auditLogger,
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
        if (strcasecmp((string) $term->interest_type, 'Flat') !== 0) {
            throw new InvalidArgumentException('Production offer generation currently requires a flat-interest product term so the disclosed schedule is exact and reproducible.');
        }

        $durationDays = (int) $term->duration;
        if ($durationDays <= 0) {
            throw new InvalidArgumentException('The selected product term has an invalid duration.');
        }
        $ratePercent = (float) $term->interest_rate;
        if ($ratePercent < 0) {
            throw new InvalidArgumentException('The selected product term has an invalid interest rate.');
        }

        $cycleDays = $this->cycleDays((string) $term->interest_cycle);
        $termRatePercent = ($ratePercent / $cycleDays) * $durationDays;
        $principalMinor = (int) $decision->approved_amount_minor;
        $interestMinor = (int) round($principalMinor * ($termRatePercent / 100));
        $accessFeeMinor = (int) ($pricing['access_fee_minor'] ?? 0);
        $disbursementFeeMinor = (int) ($pricing['disbursement_fee_minor'] ?? 0);
        $feesMinor = $accessFeeMinor + $disbursementFeeMinor;
        $feeTreatment = (string) ($pricing['fee_treatment'] ?? 'financed');

        if (! in_array($feeTreatment, ['financed', 'deducted'], true)) {
            throw new InvalidArgumentException('Fee treatment must be financed or deducted.');
        }
        if ($accessFeeMinor < 0 || $disbursementFeeMinor < 0) {
            throw new InvalidArgumentException('Offer fees cannot be negative.');
        }
        if ($feeTreatment === 'deducted' && $feesMinor >= $principalMinor) {
            throw new InvalidArgumentException('Deducted fees must be lower than the approved principal amount.');
        }

        $netDisbursementMinor = $feeTreatment === 'deducted' ? $principalMinor - $feesMinor : $principalMinor;
        $repayableFeesMinor = $feeTreatment === 'financed' ? $feesMinor : 0;
        $totalRepaymentMinor = $principalMinor + $interestMinor + $repayableFeesMinor;
        $expiresInMinutes = max(5, min((int) ($pricing['expires_in_minutes'] ?? 1440), 10080));

        return DB::transaction(function () use (
            $application, $actor, $decision, $term, $principalMinor, $interestMinor, $feesMinor,
            $accessFeeMinor, $disbursementFeeMinor, $netDisbursementMinor, $totalRepaymentMinor,
            $durationDays, $ratePercent, $termRatePercent, $feeTreatment, $expiresInMinutes,
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
                    'algorithm_version' => 'flat-v1',
                    'product_term_id' => $term->id,
                    'configured_rate_percent' => $ratePercent,
                    'configured_interest_cycle' => (string) $term->interest_cycle,
                    'term_rate_percent' => round($termRatePercent, 6),
                    'access_fee_minor' => $accessFeeMinor,
                    'disbursement_fee_minor' => $disbursementFeeMinor,
                    'fee_treatment' => $feeTreatment,
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

        $existing = MobileMoneyTransaction::query()->where('credit_offer_id', $offer->id)
            ->where('direction', MobileMoneyTransaction::DIRECTION_DISBURSEMENT)->latest()->first();
        $transaction = $existing ?: $this->mobileMoney->disburse([
            'credit_offer_id' => $offer->id,
            'user_id' => $offer->user_id,
            'institution_id' => $offer->institution_id,
            'amount_minor' => $offer->net_disbursement_minor,
            'currency' => $offer->currency,
            'phone' => $user->phone,
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
            $transaction->update(['reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED]);

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
                $lockedTransaction->update(['reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED]);

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
                ]);
                $loan->save();

                return $loan;
            });

            $this->createExactSchedule($loan, $offer, $disbursedAt);
            $lockedTransaction->update(['loan_id' => $loan->id]);
            $this->loanLedger->postCreditOfferDisbursement($lockedTransaction->fresh(), $loan, $offer);
            $offer->update(['status' => CreditOffer::STATUS_DISBURSED]);
            $application->update(['status' => 'Disbursed', 'disbursed_at' => $disbursedAt]);
            $lockedTransaction->update(['reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED]);

            $this->auditLogger->record('credit.disbursement.fulfilled', null, $loan, [
                'offer_reference' => $offer->offer_reference,
                'mobile_money_transaction_id' => $lockedTransaction->id,
                'provider_reference' => $lockedTransaction->provider_reference,
                'ledger_reference' => 'loan.disbursement:credit-offer:'.$offer->offer_reference,
            ]);

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
                        'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_EXCEPTION,
                        'failure_reason' => 'Provider reversal is linked to an original credit ledger posting but no loan record can be found.',
                    ]);
                } else {
                    $lockedTransaction->update(['reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED]);
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
            $lockedTransaction->update(['reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED]);

            $this->auditLogger->record('credit.disbursement.reversed', null, $loan, [
                'credit_offer_id' => $offer->id,
                'mobile_money_transaction_id' => $lockedTransaction->id,
                'provider_reference' => $lockedTransaction->provider_reference,
            ]);

            return $loan->fresh();
        });
    }

    private function createExactSchedule(Loan $loan, CreditOffer $offer, $anchor): void
    {
        $frequencyDays = $this->frequencyDays($offer->repayment_frequency);
        $installments = max(1, (int) ceil($offer->duration_days / $frequencyDays));
        $repayableFeesMinor = $offer->fee_treatment === 'financed' ? $offer->fees_minor : 0;
        for ($installment = 1; $installment <= $installments; $installment++) {
            $principal = $this->allocate($offer->principal_amount_minor, $installments, $installment);
            $interest = $this->allocate($offer->interest_amount_minor, $installments, $installment);
            $fees = $this->allocate($repayableFeesMinor, $installments, $installment);
            $dueOffsetDays = min($offer->duration_days, $installment * $frequencyDays);
            $total = $principal + $interest + $fees;
            CreditRepaymentScheduleItem::create([
                'loan_id' => $loan->id,
                'credit_offer_id' => $offer->id,
                'installment_number' => $installment,
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

    private function cycleDays(string $cycle): int
    {
        return match (strtolower($cycle)) {
            'daily' => 1,
            'weekly' => 7,
            'monthly' => 30,
            default => throw new InvalidArgumentException("Unsupported production interest cycle: {$cycle}"),
        };
    }

    private function frequencyDays(string $frequency): int
    {
        return match (strtolower($frequency)) {
            'daily' => 1,
            'weekly' => 7,
            'fortnightly' => 14,
            'monthly' => 30,
            default => throw new InvalidArgumentException("Unsupported production repayment frequency: {$frequency}"),
        };
    }
}
