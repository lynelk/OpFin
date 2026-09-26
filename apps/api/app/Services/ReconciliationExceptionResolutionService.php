<?php

namespace App\Services;

use App\Models\MobileMoneyTransaction;
use App\Models\ReconciliationItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReconciliationExceptionResolutionService
{
    public function __construct(
        private readonly FinancialPolicyService $policies,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function requestWriteOff(
        ReconciliationItem $item,
        User $actor,
        string $reason,
        string $evidenceHash,
        ?string $evidenceReference = null,
        array $evidence = [],
    ): object {
        if (in_array($item->status, [ReconciliationItem::STATUS_MATCHED, ReconciliationItem::STATUS_WRITTEN_OFF], true)) {
            throw new InvalidArgumentException('Matched or already written-off reconciliation items cannot enter the write-off workflow.');
        }

        return $this->policies->requestOverride(
            'reconciliation.write_off',
            ReconciliationItem::class,
            (int) $item->id,
            $actor,
            $reason,
            array_merge($evidence, [
                'evidence_hash' => strtolower($evidenceHash),
                'evidence_reference' => $evidenceReference,
                'reconciliation_run_id' => $item->reconciliation_run_id,
                'mobile_money_transaction_id' => $item->mobile_money_transaction_id,
                'exception_type' => $item->exception_type,
            ]),
            now()->addHours(24),
        );
    }

    public function applyWriteOff(
        ReconciliationItem $item,
        int $overrideId,
        User $actor,
    ): ReconciliationItem {
        return DB::transaction(function () use ($item, $overrideId, $actor) {
            $lockedItem = ReconciliationItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if (in_array($lockedItem->status, [ReconciliationItem::STATUS_MATCHED, ReconciliationItem::STATUS_WRITTEN_OFF], true)) {
                throw new InvalidArgumentException('This reconciliation item cannot be written off from its current state.');
            }

            $override = DB::table('financial_control_overrides')->where('id', $overrideId)->lockForUpdate()->first();
            if (! $override
                || $override->control_code !== 'reconciliation.write_off'
                || $override->subject_type !== ReconciliationItem::class
                || (int) $override->subject_id !== (int) $lockedItem->id
                || $override->status !== 'approved') {
                throw new InvalidArgumentException('A current approved maker-checker write-off override for this reconciliation item is required.');
            }
            if ($override->expires_at && now()->greaterThanOrEqualTo($override->expires_at)) {
                throw new InvalidArgumentException('The reconciliation write-off approval has expired.');
            }

            if ($lockedItem->mobile_money_transaction_id) {
                $money = MobileMoneyTransaction::query()
                    ->whereKey($lockedItem->mobile_money_transaction_id)
                    ->lockForUpdate()
                    ->first();

                if ($money && $money->statement_reconciliation_status === MobileMoneyTransaction::STATEMENT_MATCHED) {
                    throw new InvalidArgumentException('A provider-statement-matched money movement cannot be written off.');
                }

                if ($money) {
                    $money->update([
                        'statement_reconciliation_status' => MobileMoneyTransaction::STATEMENT_EXCEPTION,
                        'statement_reconciled_at' => null,
                        'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_EXCEPTION,
                    ]);
                }
            }

            $evidence = (array) json_decode((string) ($override->evidence ?? '[]'), true);
            $lockedItem->update([
                'status' => ReconciliationItem::STATUS_WRITTEN_OFF,
                'notes' => trim(implode("\n\n", array_filter([
                    $lockedItem->notes,
                    'Controlled write-off: '.$override->reason,
                    isset($evidence['evidence_reference']) && $evidence['evidence_reference']
                        ? 'Evidence reference: '.$evidence['evidence_reference']
                        : null,
                ]))),
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);

            DB::table('financial_control_overrides')->where('id', $overrideId)->update([
                'status' => 'applied',
                'updated_at' => now(),
            ]);

            $this->refreshRunSummary((int) $lockedItem->reconciliation_run_id);

            $this->auditLogger->record('reconciliation.item.written_off', $actor, $lockedItem, [
                'financial_control_override_id' => $overrideId,
                'requested_by' => $override->requested_by,
                'approved_by' => $override->approved_by,
                'evidence_hash' => $evidence['evidence_hash'] ?? null,
                'provider_statement_remains_exception' => true,
            ]);

            return $lockedItem->fresh();
        });
    }
    private function refreshRunSummary(int $runId): void
    {
        $items = DB::table('reconciliation_items')->where('reconciliation_run_id', $runId)->get();
        $providerCount = DB::table('provider_statement_records')->where('reconciliation_run_id', $runId)->count();
        $existing = DB::table('reconciliation_runs')->where('id', $runId)->value('summary');
        $summary = is_string($existing) ? (json_decode($existing, true) ?: []) : (array) ($existing ?? []);

        $exceptionTypes = $items
            ->where('status', ReconciliationItem::STATUS_EXCEPTION)
            ->filter(fn ($item) => $item->exception_type)
            ->groupBy('exception_type')
            ->map(fn ($group) => $group->count())
            ->all();

        DB::table('reconciliation_runs')->where('id', $runId)->update([
            'summary' => json_encode(array_merge($summary, [
                'provider_record_count' => $providerCount,
                'matched_count' => $items->where('status', ReconciliationItem::STATUS_MATCHED)->count(),
                'exception_count' => $items->where('status', ReconciliationItem::STATUS_EXCEPTION)->count(),
                'pending_provider_match_count' => $items->where('status', ReconciliationItem::STATUS_REQUIRES_PROVIDER_MATCH)->count(),
                'written_off_count' => $items->where('status', ReconciliationItem::STATUS_WRITTEN_OFF)->count(),
                'exception_types' => $exceptionTypes,
            ]), JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }
}
