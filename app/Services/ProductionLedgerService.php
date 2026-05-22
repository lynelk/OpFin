<?php

namespace App\Services;

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
        $this->assertBalanced($entries);

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
            if (($entry['amount_minor'] ?? 0) <= 0) {
                throw new InvalidArgumentException('Ledger entry amounts must be positive integer minor units.');
            }

            if ($entry['direction'] === LedgerEntry::DIRECTION_DEBIT) {
                $debits += $entry['amount_minor'];
                continue;
            }

            if ($entry['direction'] === LedgerEntry::DIRECTION_CREDIT) {
                $credits += $entry['amount_minor'];
                continue;
            }

            throw new InvalidArgumentException('Ledger entry direction must be debit or credit.');
        }

        if ($debits !== $credits) {
            throw new InvalidArgumentException('Ledger transaction is not balanced.');
        }
    }
}
