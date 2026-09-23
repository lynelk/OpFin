<?php

namespace App\Services;

use App\Models\CreditOffer;
use App\Models\CreditWriteOff;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Loan;
use App\Models\LoanImpairmentAssessment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditWriteOffService
{
    public function __construct(
        private readonly ProductionLedgerService $ledger,
        private readonly AuditLogger $auditLogger,
        private readonly CreditReferenceReportingService $creditReporting,
    ) {}

    public function writeOff(
        Loan $loan,
        string $policyVersion,
        array $evidence,
        User $actor,
    ): CreditWriteOff {
        $policyVersion = trim($policyVersion);
        if ($policyVersion === '' || $evidence === []) {
            throw new InvalidArgumentException('Credit write-off requires an approved policy version and supporting evidence.');
        }

        return DB::transaction(function () use ($loan, $policyVersion, $evidence, $actor) {
            $lockedLoan = Loan::withoutGlobalScopes()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            if (in_array((string) $lockedLoan->status, ['Cleared', 'Reversed'], true)) {
                throw new InvalidArgumentException('Cleared or reversed loans cannot be written off.');
            }
            if (CreditWriteOff::query()->where('loan_id', $lockedLoan->id)->exists()) {
                throw new InvalidArgumentException('This loan already has an accounting write-off.');
            }

            [$principalMinor, $financedFeeMinor] = $this->outstandingComponents($lockedLoan);
            $defaultInterestMinor = max(
                0,
                (int) $lockedLoan->default_interest_accrued_minor - (int) $lockedLoan->default_interest_paid_minor,
            );
            if ($principalMinor <= 0) {
                throw new InvalidArgumentException('Credit write-off requires outstanding principal.');
            }

            $assessment = LoanImpairmentAssessment::query()
                ->where('loan_id', $lockedLoan->id)
                ->orderByDesc('as_of_date')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $assessment
                || $assessment->stage !== 'stage_3'
                || (int) $assessment->gross_exposure_minor !== $principalMinor
                || (int) $assessment->expected_credit_loss_minor !== $principalMinor) {
                throw new InvalidArgumentException(
                    'Credit write-off requires a current stage-3 impairment assessment with 100% principal loss allowance.'
                );
            }

            $currency = $this->currency($lockedLoan);
            $offer = $lockedLoan->credit_offer_id
                ? CreditOffer::query()->findOrFail($lockedLoan->credit_offer_id)
                : null;
            $recognisedFeesMinor = $offer
                ? (int) DB::table('credit_fee_recognition_events')->where('loan_id', $lockedLoan->id)->sum('amount_minor')
                : 0;
            $remainingDeferredFeesMinor = $offer
                ? max(0, (int) $offer->fees_minor - $recognisedFeesMinor)
                : 0;
            $deferredFeeReleasedMinor = min($financedFeeMinor, $remainingDeferredFeesMinor);
            $recognisedFeeLossMinor = max(0, $financedFeeMinor - $deferredFeeReleasedMinor);

            $writeOff = CreditWriteOff::create([
                'loan_id' => $lockedLoan->id,
                'principal_written_off_minor' => $principalMinor,
                'financed_fee_written_off_minor' => $financedFeeMinor,
                'deferred_fee_released_minor' => $deferredFeeReleasedMinor,
                'recognised_fee_loss_minor' => $recognisedFeeLossMinor,
                'default_interest_written_off_minor' => $defaultInterestMinor,
                'currency' => $currency,
                'policy_version' => $policyVersion,
                'evidence' => $evidence,
                'legal_obligation_preserved' => true,
                'approved_by' => $actor->id,
                'impairment_assessment_id' => $assessment->id,
                'written_off_at' => now(),
            ]);

            $entries = [
                [
                    'account_id' => $this->account(
                        'contra_asset.credit_loss_allowance.product_'.$lockedLoan->loan_product_id,
                        'Credit loss allowance product '.$lockedLoan->loan_product_id,
                        'contra_asset',
                        $currency,
                    )->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $principalMinor,
                    'memo' => 'Consume approved stage-3 credit loss allowance on accounting write-off',
                ],
                [
                    'account_id' => $this->account(
                        'asset.loan_receivable.product_'.$lockedLoan->loan_product_id,
                        'Loan receivable product '.$lockedLoan->loan_product_id,
                        'asset',
                        $currency,
                    )->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => $principalMinor,
                    'memo' => 'Derecognise written-off loan principal without forgiving legal obligation',
                ],
            ];

            if ($financedFeeMinor > 0) {
                if ($deferredFeeReleasedMinor > 0) {
                    $entries[] = [
                        'account_id' => $this->account(
                            'liability.credit_fee_clearing.product_'.$lockedLoan->loan_product_id,
                            'Credit fee clearing product '.$lockedLoan->loan_product_id,
                            'liability',
                            $currency,
                        )->id,
                        'direction' => LedgerEntry::DIRECTION_DEBIT,
                        'amount_minor' => $deferredFeeReleasedMinor,
                        'memo' => 'Release deferred unearned financed fee on accounting write-off',
                    ];
                }
                if ($recognisedFeeLossMinor > 0) {
                    $entries[] = [
                        'account_id' => $this->account(
                            'expense.credit_fee_write_off.product_'.$lockedLoan->loan_product_id,
                            'Recognised credit fee write-off expense product '.$lockedLoan->loan_product_id,
                            'expense',
                            $currency,
                        )->id,
                        'direction' => LedgerEntry::DIRECTION_DEBIT,
                        'amount_minor' => $recognisedFeeLossMinor,
                        'memo' => 'Write off recognised but unpaid financed fee receivable',
                    ];
                }
                $entries[] = [
                    'account_id' => $this->account(
                        'asset.credit_fee_receivable.product_'.$lockedLoan->loan_product_id,
                        'Credit fee receivable product '.$lockedLoan->loan_product_id,
                        'asset',
                        $currency,
                    )->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => $financedFeeMinor,
                    'memo' => 'Derecognise unpaid financed-fee receivable on accounting write-off',
                ];
            }

            if ($defaultInterestMinor > 0) {
                $entries[] = [
                    'account_id' => $this->account(
                        'expense.default_interest_write_off.product_'.$lockedLoan->loan_product_id,
                        'Default interest write-off expense product '.$lockedLoan->loan_product_id,
                        'expense',
                        $currency,
                    )->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $defaultInterestMinor,
                    'memo' => 'Write off accrued but unpaid default-interest receivable',
                ];
                $entries[] = [
                    'account_id' => $this->account(
                        'asset.default_interest_receivable.product_'.$lockedLoan->loan_product_id,
                        'Default interest receivable product '.$lockedLoan->loan_product_id,
                        'asset',
                        $currency,
                    )->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => $defaultInterestMinor,
                    'memo' => 'Derecognise unpaid accrued default interest',
                ];
            }

            $posted = $this->ledger->post(
                'credit.write_off:loan:'.$lockedLoan->id,
                'credit.write_off',
                $writeOff,
                $entries,
                $actor,
                $currency,
                [
                    'loan_id' => $lockedLoan->id,
                    'loan_product_id' => $lockedLoan->loan_product_id,
                    'impairment_assessment_id' => $assessment->id,
                    'principal_written_off_minor' => $principalMinor,
                    'financed_fee_written_off_minor' => $financedFeeMinor,
                    'deferred_fee_released_minor' => $deferredFeeReleasedMinor,
                    'recognised_fee_loss_minor' => $recognisedFeeLossMinor,
                    'default_interest_written_off_minor' => $defaultInterestMinor,
                    'legal_obligation_preserved' => true,
                    'policy_version' => $policyVersion,
                ],
            );

            $writeOff->update(['ledger_transaction_id' => $posted->id]);
            $lockedLoan->update(['status' => 'Written Off']);

            $this->auditLogger->record('credit.loan.written_off', $actor, $lockedLoan, [
                'credit_write_off_id' => $writeOff->id,
                'principal_written_off_minor' => $principalMinor,
                'financed_fee_written_off_minor' => $financedFeeMinor,
                'deferred_fee_released_minor' => $deferredFeeReleasedMinor,
                'recognised_fee_loss_minor' => $recognisedFeeLossMinor,
                'default_interest_written_off_minor' => $defaultInterestMinor,
                'policy_version' => $policyVersion,
                'legal_obligation_preserved' => true,
            ]);

            DB::afterCommit(function () use ($lockedLoan) {
                $this->creditReporting->queueLoanEvent(
                    Loan::withoutGlobalScopes()->findOrFail($lockedLoan->id),
                    'write_off',
                );
            });

            return $writeOff->fresh();
        });
    }

    private function outstandingComponents(Loan $loan): array
    {
        if ($loan->credit_offer_id) {
            $row = DB::table('credit_repayment_schedule_items')
                ->where('loan_id', $loan->id)
                ->selectRaw('COALESCE(SUM(principal_outstanding_minor), 0) AS principal_minor')
                ->selectRaw('COALESCE(SUM(fees_outstanding_minor), 0) AS fee_minor')
                ->first();

            return [(int) ($row->principal_minor ?? 0), (int) ($row->fee_minor ?? 0)];
        }

        return [
            (int) round((float) DB::table('loan_schedules')->where('loan_id', $loan->id)->sum('principal_outstanding')),
            0,
        ];
    }

    private function currency(Loan $loan): string
    {
        if ($loan->credit_offer_id) {
            $currency = CreditOffer::query()->whereKey($loan->credit_offer_id)->value('currency');
            if ($currency) {
                return strtoupper((string) $currency);
            }
        }

        return strtoupper((string) config('services.mobile_money.currency', 'UGX'));
    }

    private function account(string $code, string $name, string $type, string $currency): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            ['code' => $code, 'currency' => strtoupper($currency)],
            ['name' => $name, 'type' => $type, 'is_active' => true],
        );
    }
}
