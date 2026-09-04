<?php

namespace App\Services;

use App\Models\MobileMoneyTransaction;
use App\Models\ProviderStatementRecord;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentReconciliationService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function createRun(string $provider, string $businessDate, User $actor): ReconciliationRun
    {
        $provider = strtolower(trim($provider));
        $date = CarbonImmutable::parse($businessDate)->startOfDay();

        $openExists = ReconciliationRun::query()
            ->where('provider', $provider)
            ->whereDate('business_date', $date->toDateString())
            ->where('status', ReconciliationRun::STATUS_OPEN)
            ->exists();

        if ($openExists) {
            throw new InvalidArgumentException('An open reconciliation run already exists for this provider and business date.');
        }

        return DB::transaction(function () use ($provider, $date, $actor) {
            $run = ReconciliationRun::create([
                'provider' => $provider,
                'business_date' => $date->toDateString(),
                'status' => ReconciliationRun::STATUS_OPEN,
                'created_by' => $actor->id,
                'started_at' => now(),
                'summary' => [
                    'source' => 'opfin_system_transactions',
                    'business_date' => $date->toDateString(),
                ],
            ]);

            $transactions = MobileMoneyTransaction::query()
                ->where('provider', $provider)
                ->whereBetween('created_at', [$date->startOfDay(), $date->endOfDay()])
                ->where('reconciliation_status', '!=', MobileMoneyTransaction::RECONCILIATION_MATCHED)
                ->orderBy('id')
                ->get();

            foreach ($transactions as $transaction) {
                ReconciliationItem::create([
                    'reconciliation_run_id' => $run->id,
                    'mobile_money_transaction_id' => $transaction->id,
                    'provider_reference' => $transaction->provider_reference,
                    'internal_reference' => $transaction->internal_reference,
                    'direction' => $transaction->direction,
                    'currency' => $transaction->currency,
                    'system_status' => $transaction->status,
                    'system_amount_minor' => $transaction->amount_minor,
                    'status' => ReconciliationItem::STATUS_REQUIRES_PROVIDER_MATCH,
                ]);
            }

            $run->update([
                'summary' => [
                    ...($run->summary ?? []),
                    'system_record_count' => $transactions->count(),
                ],
            ]);

            $this->auditLogger->record('reconciliation.run.created', $actor, $run, [
                'provider' => $provider,
                'business_date' => $date->toDateString(),
                'system_record_count' => $transactions->count(),
            ]);

            return $run->fresh('items');
        });
    }

    public function ingestProviderRecords(ReconciliationRun $run, array $records, User $actor): array
    {
        if ($run->status !== ReconciliationRun::STATUS_OPEN) {
            throw new InvalidArgumentException('Provider records can only be ingested into an open reconciliation run.');
        }

        $processed = [];
        foreach ($records as $record) {
            $processed[] = DB::transaction(function () use ($run, $record, $actor) {
                $normalized = $this->normalizedRecord($record);
                $hash = hash('sha256', json_encode($this->canonicalize($normalized), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                $providerRecord = ProviderStatementRecord::query()->firstOrCreate(
                    [
                        'reconciliation_run_id' => $run->id,
                        'record_hash' => $hash,
                    ],
                    [
                        ...$normalized,
                        'raw_payload' => $record,
                    ],
                );

                if (! $providerRecord->wasRecentlyCreated) {
                    return $providerRecord;
                }

                $duplicates = ProviderStatementRecord::query()
                    ->where('reconciliation_run_id', $run->id)
                    ->where('provider_reference', $providerRecord->provider_reference)
                    ->whereKeyNot($providerRecord->id)
                    ->get();

                if ($providerRecord->provider_reference && $duplicates->isNotEmpty()) {
                    $this->markDuplicateProviderReferences($run, $providerRecord, $duplicates);
                    $this->auditLogger->record('reconciliation.provider_record.duplicate_reference', $actor, $providerRecord, [
                        'provider_reference' => $providerRecord->provider_reference,
                    ]);

                    return $providerRecord;
                }

                $matches = $this->matchingSystemTransactions($run, $providerRecord);
                if ($matches->count() === 0) {
                    ReconciliationItem::create([
                        'reconciliation_run_id' => $run->id,
                        'provider_statement_record_id' => $providerRecord->id,
                        'provider_reference' => $providerRecord->provider_reference,
                        'internal_reference' => $providerRecord->internal_reference,
                        'direction' => $providerRecord->direction,
                        'currency' => $providerRecord->currency,
                        'provider_status' => $providerRecord->provider_status,
                        'provider_amount_minor' => $providerRecord->amount_minor,
                        'system_amount_minor' => 0,
                        'status' => ReconciliationItem::STATUS_EXCEPTION,
                        'exception_type' => ReconciliationItem::EXCEPTION_MISSING_OPFIN_RECORD,
                        'notes' => 'Provider record has no matching OpFin payment record.',
                    ]);

                    return $providerRecord;
                }

                if ($matches->count() > 1) {
                    foreach ($matches as $systemTransaction) {
                        $this->upsertMatchedItem(
                            $run,
                            $providerRecord,
                            $systemTransaction,
                            ReconciliationItem::STATUS_EXCEPTION,
                            ReconciliationItem::EXCEPTION_DUPLICATE_SYSTEM_REFERENCE,
                            'Provider record matches more than one OpFin payment record.',
                        );
                    }

                    return $providerRecord;
                }

                $systemTransaction = $matches->first();
                [$status, $exceptionType, $notes] = $this->classification($systemTransaction, $providerRecord);
                $this->upsertMatchedItem($run, $providerRecord, $systemTransaction, $status, $exceptionType, $notes);

                if ($status === ReconciliationItem::STATUS_MATCHED) {
                    $systemTransaction->update([
                        'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED,
                    ]);
                } else {
                    $systemTransaction->update([
                        'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_EXCEPTION,
                    ]);
                }

                $this->auditLogger->record('reconciliation.provider_record.ingested', $actor, $providerRecord, [
                    'mobile_money_transaction_id' => $systemTransaction->id,
                    'status' => $status,
                    'exception_type' => $exceptionType,
                ]);

                return $providerRecord;
            });
        }

        $this->refreshSummary($run);

        return $processed;
    }

    public function completeRun(ReconciliationRun $run, User $actor): ReconciliationRun
    {
        if ($run->status !== ReconciliationRun::STATUS_OPEN) {
            throw new InvalidArgumentException('Only an open reconciliation run can be completed.');
        }

        DB::transaction(function () use ($run, $actor) {
            ReconciliationItem::query()
                ->where('reconciliation_run_id', $run->id)
                ->where('status', ReconciliationItem::STATUS_REQUIRES_PROVIDER_MATCH)
                ->update([
                    'status' => ReconciliationItem::STATUS_EXCEPTION,
                    'exception_type' => ReconciliationItem::EXCEPTION_MISSING_PROVIDER_RECORD,
                    'notes' => 'No provider statement record matched this OpFin payment before run completion.',
                ]);

            $this->refreshSummary($run);
            $run->update([
                'status' => ReconciliationRun::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $this->auditLogger->record('reconciliation.run.completed', $actor, $run, [
                'summary' => $run->fresh()->summary,
            ]);
        });

        return $run->fresh(['items', 'providerRecords']);
    }

    private function normalizedRecord(array $record): array
    {
        return [
            'provider_reference' => $this->nullableTrim($record['provider_reference'] ?? null),
            'internal_reference' => $this->nullableTrim($record['internal_reference'] ?? null),
            'amount_minor' => (int) $record['amount_minor'],
            'currency' => strtoupper(trim((string) $record['currency'])),
            'direction' => strtolower(trim((string) $record['direction'])),
            'provider_status' => strtolower(trim((string) $record['provider_status'])),
            'occurred_at' => $record['occurred_at'] ?? null,
        ];
    }

    private function matchingSystemTransactions(ReconciliationRun $run, ProviderStatementRecord $record)
    {
        $date = CarbonImmutable::parse($run->business_date);

        return MobileMoneyTransaction::query()
            ->where('provider', $run->provider)
            ->whereBetween('created_at', [$date->startOfDay(), $date->endOfDay()])
            ->where(function ($query) use ($record) {
                if ($record->provider_reference) {
                    $query->where('provider_reference', $record->provider_reference);
                }
                if ($record->internal_reference) {
                    if ($record->provider_reference) {
                        $query->orWhere('internal_reference', $record->internal_reference);
                    } else {
                        $query->where('internal_reference', $record->internal_reference);
                    }
                }
            })
            ->get();
    }

    private function classification(
        MobileMoneyTransaction $system,
        ProviderStatementRecord $provider,
    ): array {
        if ((int) $system->amount_minor !== (int) $provider->amount_minor) {
            return [ReconciliationItem::STATUS_EXCEPTION, ReconciliationItem::EXCEPTION_AMOUNT_MISMATCH, 'System and provider amounts differ.'];
        }
        if (strtoupper($system->currency) !== strtoupper($provider->currency)) {
            return [ReconciliationItem::STATUS_EXCEPTION, ReconciliationItem::EXCEPTION_CURRENCY_MISMATCH, 'System and provider currencies differ.'];
        }
        if (strtolower($system->direction) !== strtolower($provider->direction)) {
            return [ReconciliationItem::STATUS_EXCEPTION, ReconciliationItem::EXCEPTION_DIRECTION_MISMATCH, 'System and provider payment directions differ.'];
        }
        if ($this->statusGroup($system->status) !== $this->statusGroup($provider->provider_status)) {
            return [ReconciliationItem::STATUS_EXCEPTION, ReconciliationItem::EXCEPTION_STATUS_MISMATCH, 'System and provider payment statuses differ.'];
        }

        return [ReconciliationItem::STATUS_MATCHED, null, 'System and provider payment evidence matched.'];
    }

    private function upsertMatchedItem(
        ReconciliationRun $run,
        ProviderStatementRecord $providerRecord,
        MobileMoneyTransaction $systemTransaction,
        string $status,
        ?string $exceptionType,
        string $notes,
    ): ReconciliationItem {
        return ReconciliationItem::query()->updateOrCreate(
            [
                'reconciliation_run_id' => $run->id,
                'mobile_money_transaction_id' => $systemTransaction->id,
            ],
            [
                'provider_statement_record_id' => $providerRecord->id,
                'provider_reference' => $providerRecord->provider_reference ?: $systemTransaction->provider_reference,
                'internal_reference' => $systemTransaction->internal_reference,
                'direction' => $systemTransaction->direction,
                'currency' => $systemTransaction->currency,
                'system_status' => $systemTransaction->status,
                'provider_status' => $providerRecord->provider_status,
                'system_amount_minor' => $systemTransaction->amount_minor,
                'provider_amount_minor' => $providerRecord->amount_minor,
                'status' => $status,
                'exception_type' => $exceptionType,
                'notes' => $notes,
            ],
        );
    }

    private function markDuplicateProviderReferences(
        ReconciliationRun $run,
        ProviderStatementRecord $current,
        $duplicates,
    ): void {
        $records = $duplicates->push($current);
        foreach ($records as $record) {
            $matches = $this->matchingSystemTransactions($run, $record);
            if ($matches->isEmpty()) {
                ReconciliationItem::query()->updateOrCreate(
                    [
                        'reconciliation_run_id' => $run->id,
                        'provider_statement_record_id' => $record->id,
                    ],
                    [
                        'provider_reference' => $record->provider_reference,
                        'internal_reference' => $record->internal_reference,
                        'direction' => $record->direction,
                        'currency' => $record->currency,
                        'provider_status' => $record->provider_status,
                        'provider_amount_minor' => $record->amount_minor,
                        'system_amount_minor' => 0,
                        'status' => ReconciliationItem::STATUS_EXCEPTION,
                        'exception_type' => ReconciliationItem::EXCEPTION_DUPLICATE_PROVIDER_REFERENCE,
                        'notes' => 'Provider statement contains a duplicate provider reference.',
                    ],
                );

                continue;
            }

            foreach ($matches as $systemTransaction) {
                $this->upsertMatchedItem(
                    $run,
                    $record,
                    $systemTransaction,
                    ReconciliationItem::STATUS_EXCEPTION,
                    ReconciliationItem::EXCEPTION_DUPLICATE_PROVIDER_REFERENCE,
                    'Provider statement contains a duplicate provider reference.',
                );
                $systemTransaction->update([
                    'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_EXCEPTION,
                ]);
            }
        }
    }

    private function refreshSummary(ReconciliationRun $run): void
    {
        $items = ReconciliationItem::query()->where('reconciliation_run_id', $run->id)->get();
        $providerCount = ProviderStatementRecord::query()->where('reconciliation_run_id', $run->id)->count();

        $run->update([
            'summary' => [
                ...($run->summary ?? []),
                'provider_record_count' => $providerCount,
                'item_count' => $items->count(),
                'matched_count' => $items->where('status', ReconciliationItem::STATUS_MATCHED)->count(),
                'exception_count' => $items->where('status', ReconciliationItem::STATUS_EXCEPTION)->count(),
                'pending_provider_match_count' => $items->where('status', ReconciliationItem::STATUS_REQUIRES_PROVIDER_MATCH)->count(),
                'written_off_count' => $items->where('status', ReconciliationItem::STATUS_WRITTEN_OFF)->count(),
                'exception_types' => $items
                    ->where('status', ReconciliationItem::STATUS_EXCEPTION)
                    ->filter(fn (ReconciliationItem $item) => $item->exception_type)
                    ->groupBy('exception_type')
                    ->map->count()
                    ->all(),
            ],
        ]);
    }

    private function statusGroup(string $status): string
    {
        return match (strtolower(trim($status))) {
            'successful', 'succeeded', 'completed', 'success' => 'successful',
            'failed', 'rejected', 'cancelled', 'canceled' => 'failed',
            'reversed', 'refunded' => 'reversed',
            default => 'pending',
        };
    }

    private function canonicalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }
        ksort($value);

        return $value;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
