<?php

namespace App\Services;

use App\Models\ReconciliationItem;
use Illuminate\Support\Facades\DB;

/** Read-only integrity check. Does not create balancing journal entries. */
final class ReconciliationIntegrityGate
{
    public function checkRun(int $runId): array
    {
        $items = ReconciliationItem::query()->where('reconciliation_run_id', $runId);
        $total = (clone $items)->count();
        $unresolved = (clone $items)->where('status', '!=', ReconciliationItem::STATUS_MATCHED)->count();
        $amountMismatch = (clone $items)->where('status', ReconciliationItem::STATUS_MATCHED)
            ->whereColumn('system_amount_minor', '!=', 'provider_amount_minor')->count();
        $duplicateProviderReferences = DB::table('reconciliation_items')
            ->where('reconciliation_run_id', $runId)
            ->whereNotNull('provider_reference')
            ->select('provider_reference')->groupBy('provider_reference')
            ->havingRaw('COUNT(*) > 1')->get()->count();
        return [
            'run_id' => $runId,
            'passed' => $total > 0 && $unresolved === 0 && $amountMismatch === 0 && $duplicateProviderReferences === 0,
            'total_items' => $total,
            'unresolved_items' => $unresolved,
            'matched_amount_mismatches' => $amountMismatch,
            'duplicate_provider_references' => $duplicateProviderReferences,
        ];
    }
}
