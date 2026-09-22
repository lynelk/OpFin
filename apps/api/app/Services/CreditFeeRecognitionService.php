<?php

namespace App\Services;

use App\Models\CreditOffer;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Loan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditFeeRecognitionService
{
    public function __construct(
        private readonly FinancialPolicyService $policies,
        private readonly ProductionLedgerService $ledger,
    ) {}

    public function recognise(
        Loan $loan,
        ?Carbon $asOf = null,
        bool $settlement = false,
        int $rebatedFeeMinor = 0,
    ): int {
        if (! $loan->credit_offer_id) {
            return 0;
        }

        $asOf ??= now();
        $offer = CreditOffer::query()->findOrFail($loan->credit_offer_id);
        $totalFees = (int) $offer->fees_minor;
        if ($totalFees <= 0) {
            return 0;
        }

        $policy = $this->policies->active(
            (string) config('opfin.accounting.fee_recognition_policy_type', 'credit_fee_recognition'),
            (string) $loan->loan_product_id,
            null,
            null,
            $asOf,
        );
        $rules = $this->policies->rules($policy);
        $method = strtolower((string) ($rules['method'] ?? ''));
        if (! in_array($method, ['upfront', 'straight_line'], true)) {
            throw new InvalidArgumentException('Active credit-fee recognition policy must define method upfront or straight_line.');
        }

        $already = (int) DB::table('credit_fee_recognition_events')
            ->where('loan_id', $loan->id)
            ->sum('amount_minor');

        if ($settlement && (bool) ($rules['recognise_remaining_on_early_settlement'] ?? false)) {
            $target = max(0, $totalFees - $rebatedFeeMinor);
        } elseif ($method === 'upfront') {
            $target = $totalFees;
        } else {
            $start = Carbon::parse($loan->disbursed_at);
            $elapsed = max(0, min((int) $loan->duration, $start->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay())));
            $target = (int) floor($totalFees * ($elapsed / max(1, (int) $loan->duration)));
        }

        $delta = max(0, $target - $already);
        if ($delta <= 0) {
            return 0;
        }

        $reference = 'loan.fee_recognition:'.$loan->id.':'.$asOf->toDateString().':'.$target;
        $posted = $this->ledger->post(
            $reference,
            'loan.fee_recognition',
            $loan,
            [
                [
                    'account_id' => $this->account(
                        'liability.credit_fee_clearing.product_'.$loan->loan_product_id,
                        'Credit fee clearing product '.$loan->loan_product_id,
                        'liability',
                        (string) $offer->currency,
                    )->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $delta,
                    'memo' => 'Deferred credit fee released under effective-dated accounting policy',
                ],
                [
                    'account_id' => $this->account(
                        'income.credit_fee.product_'.$loan->loan_product_id,
                        'Credit fee income product '.$loan->loan_product_id,
                        'income',
                        (string) $offer->currency,
                    )->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => $delta,
                    'memo' => 'Credit fee income recognised under effective-dated accounting policy',
                ],
            ],
            null,
            (string) $offer->currency,
            [
                'loan_id' => $loan->id,
                'credit_offer_id' => $offer->id,
                'method' => $method,
                'settlement' => $settlement,
                'policy_code' => $policy->code,
                'policy_version' => $policy->version,
                'rebated_fee_minor' => $rebatedFeeMinor,
            ],
        );

        DB::table('credit_fee_recognition_events')->insert([
            'loan_id' => $loan->id,
            'credit_offer_id' => $offer->id,
            'ledger_transaction_id' => $posted->id,
            'reference' => $reference,
            'amount_minor' => $delta,
            'currency' => $offer->currency,
            'recognition_method' => $method,
            'recognised_through' => $asOf->toDateString(),
            'policy_snapshot' => json_encode([
                'id' => $policy->id,
                'code' => $policy->code,
                'version' => $policy->version,
                'rules' => $rules,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $delta;
    }

    public function scan(): array
    {
        $recognised = 0;
        $checked = 0;

        Loan::withoutGlobalScopes()
            ->whereNotNull('credit_offer_id')
            ->whereNotIn('status', ['Reversed', 'Cancelled', 'Rejected'])
            ->orderBy('id')
            ->chunkById(100, function ($loans) use (&$recognised, &$checked) {
                foreach ($loans as $loan) {
                    $recognised += $this->recognise($loan);
                    $checked++;
                }
            });

        return ['checked' => $checked, 'recognised_minor' => $recognised];
    }

    private function account(string $code, string $name, string $type, string $currency): LedgerAccount
    {
        $currency = strtoupper($currency);
        $account = LedgerAccount::query()->where('code', $code)->first();
        if ($account) {
            if (strtoupper((string) $account->currency) !== $currency || ! $account->is_active) {
                throw new InvalidArgumentException("Ledger account {$code} is unavailable for {$currency} postings.");
            }

            return $account;
        }

        return LedgerAccount::create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'currency' => $currency,
            'is_active' => true,
        ]);
    }
}
