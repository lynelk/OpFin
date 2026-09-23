<?php

namespace App\Services;

use App\Models\FinancialAccountingPeriod;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class FinancialAccountingPeriodService
{
    public function assertPostingAllowed(CarbonInterface $postingAt): void
    {
        if (! Schema::hasTable('financial_accounting_periods')) {
            return;
        }

        $date = $postingAt->toDateString();
        $closed = FinancialAccountingPeriod::query()
            ->where('status', FinancialAccountingPeriod::STATUS_CLOSED)
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->first();

        if ($closed) {
            throw new InvalidArgumentException(
                "Financial posting date {$date} belongs to closed accounting period ".
                $closed->period_start->toDateString().' to '.$closed->period_end->toDateString().
                '. Post an append-only adjustment in an open period instead.'
            );
        }
    }

    public function close(
        CarbonInterface $start,
        CarbonInterface $end,
        User $actor,
        string $reason,
    ): FinancialAccountingPeriod {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        if ($start->gt($end)) {
            throw new InvalidArgumentException('Accounting-period start cannot be after its end.');
        }
        if ($end->copy()->startOfDay()->greaterThanOrEqualTo(now()->startOfDay())) {
            throw new InvalidArgumentException('An accounting period can be closed only after its end date has fully elapsed.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Accounting-period close requires a documented reason.');
        }

        return DB::transaction(function () use ($startDate, $endDate, $actor, $reason) {
            $overlap = FinancialAccountingPeriod::query()
                ->where('status', FinancialAccountingPeriod::STATUS_CLOSED)
                ->whereDate('period_start', '<=', $endDate)
                ->whereDate('period_end', '>=', $startDate)
                ->lockForUpdate()
                ->first();
            if ($overlap) {
                throw new InvalidArgumentException('The requested accounting period overlaps an already closed period.');
            }

            $integrityRun = DB::table('financial_integrity_runs')
                ->where('scope', 'platform')
                ->whereNotNull('completed_at')
                ->orderByDesc('completed_at')
                ->lockForUpdate()
                ->first();

            if (! $integrityRun || $integrityRun->status !== 'balanced') {
                throw new InvalidArgumentException('Accounting-period close requires a completed balanced platform financial-integrity run.');
            }

            $openSevere = DB::table('financial_integrity_alerts')
                ->where('status', 'open')
                ->whereIn('severity', ['critical', 'high'])
                ->count();
            if ($openSevere > 0) {
                throw new InvalidArgumentException('Accounting-period close is blocked while critical or high financial-integrity alerts remain open.');
            }

            $periodEnd = Carbon::parse($endDate)->endOfDay();
            if (Carbon::parse($integrityRun->completed_at)->lt($periodEnd)) {
                throw new InvalidArgumentException('Accounting-period close requires integrity evidence generated after the end of the period.');
            }

            return FinancialAccountingPeriod::create([
                'period_start' => $startDate,
                'period_end' => $endDate,
                'status' => FinancialAccountingPeriod::STATUS_CLOSED,
                'closed_by' => $actor->id,
                'financial_integrity_run_id' => $integrityRun->id,
                'integrity_evidence_hash' => $integrityRun->evidence_hash,
                'close_reason' => trim($reason),
                'closed_at' => now(),
            ]);
        });
    }
}
