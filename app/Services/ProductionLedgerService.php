<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionLedgerService
{
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

        return DB::transaction(function () use ($reference, $eventType, $source, $entries, $actor, $currency, $metadata) {
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
