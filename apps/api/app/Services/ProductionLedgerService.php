<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ProductionLedgerService
{
    public function __construct(private readonly FinancialAccountingPeriodService $accountingPeriods) {}

    /**
     * @param  array<int, array{account_id:int,direction:string,amount_minor:int,memo?:string}>  $entries
     */
    public function post(
        string $reference,
        string $eventType,
        Model $source,
        array $entries,
        ?User $actor = null,
        string $currency = 'UGX',
        array $metadata = []
    ): LedgerTransaction {
        $reference = trim($reference);
        $eventType = trim($eventType);
        $currency = strtoupper(trim($currency));

        if ($reference === '' || $eventType === '') {
            throw new InvalidArgumentException('Ledger reference and event type are required.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Ledger currency must be a three-letter uppercase currency code.');
        }

        $this->assertBalanced($entries);
        $this->assertAccounts($entries, $currency);
        $this->assertCreditRiskOwner($eventType, $source, $entries, $metadata, $currency);
        $this->accountingPeriods->assertPostingAllowed(now());

        return DB::transaction(function () use ($reference, $eventType, $source, $entries, $actor, $currency, $metadata) {
            // Account row locks serialise competing partner-held-funds withdrawals.
            // Immutable GL lines must not create a negative custodial liability.
            $affectedAccounts = LedgerAccount::query()->whereIn('id', array_column($entries, 'account_id'))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($affectedAccounts as $account) {
                if (! str_starts_with((string) $account->code, 'liability.partner_funds_held.institution_')) {
                    continue;
                }
                $balance = (int) DB::table('ledger_entries')
                    ->where('ledger_account_id', $account->id)
                    ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE -amount_minor END), 0) AS amount")
                    ->value('amount');
                $movement = 0;
                foreach ($entries as $entry) {
                    if ((int) $entry['account_id'] === (int) $account->id) {
                        $movement += $entry['direction'] === 'credit'
                            ? (int) $entry['amount_minor'] : - (int) $entry['amount_minor'];
                    }
                }
                if ($balance + $movement < 0) {
                    throw new InvalidArgumentException('Partner fiduciary funds cannot be overdrawn.');
                }
            }
            $transaction = LedgerTransaction::create([
                'reference' => $reference,
                'event_type' => $eventType,
                'currency' => $currency,
                'source_type' => $source::class,
                'source_id' => $source->getKey(),
                'posted_by' => $actor?->id,
                'posted_at' => now(),
                'metadata' => $metadata,
            ]);

            foreach ($entries as $entry) {
                LedgerEntry::create([
                    'ledger_transaction_id' => $transaction->id,
                    'ledger_account_id' => $entry['account_id'],
                    'direction' => $entry['direction'],
                    'amount_minor' => $entry['amount_minor'],
                    'currency' => $currency,
                    'memo' => $entry['memo'] ?? null,
                ]);
            }

            return $transaction->load('entries');
        });
    }

    /**
     * Final GL safety net: upstream fee, interest and repayment callers cannot
     * circumvent the approved credit-risk owner by posting a product-level asset.
     */
    private function assertCreditRiskOwner(string $eventType, Model $source, array $entries, array $metadata, string $currency): void
    {
        if (! Schema::hasTable('loan_ecl_ownerships')) {
            return;
        }
        $loanId = isset($metadata['loan_id']) ? (int) $metadata['loan_id']
            : ($source instanceof Loan ? (int) $source->id : null);
        if (! $loanId) {
            return;
        }
        $ownership = DB::table('loan_ecl_ownerships')->where('loan_id', $loanId)->first();
        if (! $ownership) {
            return;
        }
        if ($ownership->status !== 'approved') {
            throw new InvalidArgumentException('Unapproved contractual credit ownership cannot be posted in the finance ledger.');
        }
        if ($ownership->owner_type === 'opfin' && (int) $ownership->opfin_share_bps === 10000) {
            return;
        }
        if ($ownership->owner_type !== 'partner' || (int) $ownership->opfin_share_bps !== 0) {
            throw new InvalidArgumentException('Shared and uncertified risk exposure cannot be journalled as a platform loan asset.');
        }
        if (! in_array($eventType, [
            'loan.disbursement', 'loan.disbursement.reversal',
            'loan.repayment', 'loan.repayment.reversal',
        ], true) || ($metadata['ownership_type'] ?? null) !== 'partner') {
            throw new InvalidArgumentException('Partner-loan GL may only post verified custodial cash and partner payable movements.');
        }
        if (count($entries) !== 2) {
            throw new InvalidArgumentException('Agency cash postings must contain exactly two balanced custody entries.');
        }
        $accounts = LedgerAccount::query()->whereIn('id', array_column($entries, 'account_id'))->get()->keyBy('id');
        $heldCode = 'liability.partner_funds_held.institution_'.$ownership->institution_id;
        $heldEntry = null;
        $cashEntry = null;
        foreach ($entries as $entry) {
            $account = $accounts->get($entry['account_id']);
            if ($account && $account->code === $heldCode && $account->currency === $currency) {
                $heldEntry = $entry;
            } elseif ($account && str_starts_with((string) $account->code, 'cash.')
                && $account->currency === $currency && $account->type === 'asset') {
                $cashEntry = $entry;
            } else {
                throw new InvalidArgumentException('Agency journal contains a platform receivable, income or unauthorised account.');
            }
        }
        if (! $heldEntry || ! $cashEntry
            || (int) $heldEntry['amount_minor'] !== (int) $cashEntry['amount_minor']) {
            throw new InvalidArgumentException('Agency journal must reconcile custody cash and liability.');
        }
        $disbursement = in_array($eventType, ['loan.disbursement', 'loan.repayment.reversal'], true);
        // Original disbursement: debit held funds, credit cash.
        // Original collection: debit cash, credit held funds; each reversal is its exact inverse.
        if ($disbursement
            ? ($heldEntry['direction'] !== 'debit' || $cashEntry['direction'] !== 'credit')
            : ($heldEntry['direction'] !== 'credit' || $cashEntry['direction'] !== 'debit')) {
            throw new InvalidArgumentException('Agency debit/credit directions do not match the cash event.');
        }
    }

    /**
     * @param  array<int, array{direction:string,amount_minor:int}>  $entries
     */
    public function assertBalanced(array $entries): void
    {
        if (count($entries) < 2) {
            throw new InvalidArgumentException('A ledger transaction must contain at least two entries.');
        }

        $debits = 0;
        $credits = 0;

        foreach ($entries as $entry) {
            $amountMinor = $entry['amount_minor'] ?? null;
            if (! is_int($amountMinor) || $amountMinor <= 0) {
                throw new InvalidArgumentException('Ledger entry amounts must be positive integer minor units.');
            }

            $direction = $entry['direction'] ?? null;
            if ($direction === LedgerEntry::DIRECTION_DEBIT) {
                $debits = $this->safeAdd($debits, $amountMinor);

                continue;
            }

            if ($direction === LedgerEntry::DIRECTION_CREDIT) {
                $credits = $this->safeAdd($credits, $amountMinor);

                continue;
            }

            throw new InvalidArgumentException('Ledger entry direction must be debit or credit.');
        }

        if ($debits !== $credits) {
            throw new InvalidArgumentException("Ledger transaction is not balanced. debits={$debits}, credits={$credits}.");
        }
    }

    /**
     * @param  array<int, array{account_id:int}>  $entries
     */
    private function assertAccounts(array $entries, string $currency): void
    {
        $accountIds = [];
        foreach ($entries as $entry) {
            $accountId = $entry['account_id'] ?? null;
            if (! is_int($accountId) || $accountId <= 0) {
                throw new InvalidArgumentException('Every ledger entry requires a positive integer account_id.');
            }
            $accountIds[] = $accountId;
        }

        $accounts = LedgerAccount::query()->whereIn('id', array_values(array_unique($accountIds)))->get()->keyBy('id');
        foreach (array_unique($accountIds) as $accountId) {
            $account = $accounts->get($accountId);
            if (! $account) {
                throw new InvalidArgumentException("Ledger account {$accountId} does not exist.");
            }
            if (! $account->is_active) {
                throw new InvalidArgumentException("Ledger account {$account->code} is inactive and cannot accept new postings.");
            }
            if (strtoupper((string) $account->currency) !== $currency) {
                throw new InvalidArgumentException("Ledger account {$account->code} currency does not match transaction currency {$currency}.");
            }
        }
    }

    private function safeAdd(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new InvalidArgumentException('Ledger arithmetic overflow detected.');
        }

        return $left + $right;
    }
}
