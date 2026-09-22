<?php

namespace App\Services;

use App\Models\CreditOffer;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Loan;
use App\Models\LoanImpairmentAssessment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoanImpairmentService
{
    public const STAGES = ['stage_1', 'stage_2', 'stage_3'];

    public function __construct(private readonly ProductionLedgerService $ledger) {}

    public function assess(
        Loan $loan,
        CarbonInterface $asOf,
        int $expectedCreditLossMinor,
        string $stage,
        string $policyVersion,
        array $evidence,
        User $actor,
    ): LoanImpairmentAssessment {
        $stage = strtolower(trim($stage));
        $policyVersion = trim($policyVersion);

        if (! in_array($stage, self::STAGES, true)) {
            throw new InvalidArgumentException('Impairment stage must be stage_1, stage_2 or stage_3.');
        }
        if ($expectedCreditLossMinor < 0) {
            throw new InvalidArgumentException('Expected credit loss cannot be negative.');
        }
        if ($policyVersion === '') {
            throw new InvalidArgumentException('Impairment assessment requires an approved policy version.');
        }
        if ($evidence === []) {
            throw new InvalidArgumentException('Impairment assessment requires supporting finance/risk evidence.');
        }

        return DB::transaction(function () use (
            $loan, $asOf, $expectedCreditLossMinor, $stage, $policyVersion, $evidence, $actor
        ) {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $latest = LoanImpairmentAssessment::query()
                ->where('loan_id', $lockedLoan->id)
                ->orderByDesc('as_of_date')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($latest && $asOf->toDateString() <= $latest->as_of_date->toDateString()) {
                throw new InvalidArgumentException('Impairment assessments must advance the loan assessment date; prior assessments are immutable.');
            }

            $grossExposureMinor = $this->grossRecordedExposureMinor($lockedLoan);
            if ($expectedCreditLossMinor > $grossExposureMinor) {
                throw new InvalidArgumentException('Expected credit loss cannot exceed the recorded gross credit exposure.');
            }
            if ($grossExposureMinor === 0 && $expectedCreditLossMinor !== 0) {
                throw new InvalidArgumentException('A zero recorded credit exposure requires a zero expected credit loss.');
            }

            $currency = $this->currency($lockedLoan);
            $assessment = LoanImpairmentAssessment::create([
                'loan_id' => $lockedLoan->id,
                'as_of_date' => $asOf->toDateString(),
                'stage' => $stage,
                'gross_exposure_minor' => $grossExposureMinor,
                'expected_credit_loss_minor' => $expectedCreditLossMinor,
                'currency' => $currency,
                'policy_version' => $policyVersion,
                'evidence' => $evidence,
                'approved_by' => $actor->id,
                'assessed_at' => now(),
            ]);

            $previousLoss = (int) ($latest?->expected_credit_loss_minor ?? 0);
            $delta = $expectedCreditLossMinor - $previousLoss;
            if ($delta !== 0) {
                $expense = $this->account(
                    'expense.credit_impairment.product_'.$lockedLoan->loan_product_id,
                    'Credit impairment expense product '.$lockedLoan->loan_product_id,
                    'expense',
                    $currency,
                );
                $allowance = $this->account(
                    'contra_asset.credit_loss_allowance.product_'.$lockedLoan->loan_product_id,
                    'Credit loss allowance product '.$lockedLoan->loan_product_id,
                    'contra_asset',
                    $currency,
                );

                $increase = $delta > 0;
                $amount = abs($delta);
                $posted = $this->ledger->post(
                    'credit.impairment:loan:'.$lockedLoan->id.':'.$asOf->format('Ymd'),
                    'credit.impairment.adjustment',
                    $assessment,
                    [
                        [
                            'account_id' => $increase ? $expense->id : $allowance->id,
                            'direction' => LedgerEntry::DIRECTION_DEBIT,
                            'amount_minor' => $amount,
                            'memo' => $increase
                                ? 'Increase expected credit-loss allowance'
                                : 'Reduce expected credit-loss allowance',
                        ],
                        [
                            'account_id' => $increase ? $allowance->id : $expense->id,
                            'direction' => LedgerEntry::DIRECTION_CREDIT,
                            'amount_minor' => $amount,
                            'memo' => $increase
                                ? 'Recognise expected credit-loss allowance'
                                : 'Reverse excess expected credit-loss expense',
                        ],
                    ],
                    $actor,
                    $currency,
                    [
                        'loan_id' => $lockedLoan->id,
                        'loan_product_id' => $lockedLoan->loan_product_id,
                        'as_of_date' => $asOf->toDateString(),
                        'stage' => $stage,
                        'policy_version' => $policyVersion,
                        'gross_exposure_minor' => $grossExposureMinor,
                        'previous_expected_credit_loss_minor' => $previousLoss,
                        'expected_credit_loss_minor' => $expectedCreditLossMinor,
                        'adjustment_minor' => $delta,
                    ],
                );
                $assessment->update(['ledger_transaction_id' => $posted->id]);
            }

            return $assessment->fresh();
        });
    }

    public function grossRecordedExposureMinor(Loan $loan): int
    {
        if ($loan->credit_offer_id) {
            $row = DB::table('credit_repayment_schedule_items')
                ->where('loan_id', $loan->id)
                ->selectRaw('COALESCE(SUM(principal_outstanding_minor), 0) AS principal_minor')
                ->selectRaw('COALESCE(SUM(fees_outstanding_minor), 0) AS financed_fee_minor')
                ->first();

            return (int) ($row->principal_minor ?? 0) + (int) ($row->financed_fee_minor ?? 0);
        }

        return (int) round((float) DB::table('loan_schedules')
            ->where('loan_id', $loan->id)
            ->sum('principal_outstanding'));
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
        $currency = strtoupper($currency);

        return LedgerAccount::query()->firstOrCreate(
            ['code' => $code, 'currency' => $currency],
            ['name' => $name, 'type' => $type, 'is_active' => true],
        );
    }
}
