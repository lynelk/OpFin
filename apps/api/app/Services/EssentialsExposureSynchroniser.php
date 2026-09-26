<?php

namespace App\Services;

use App\Domain\Essentials\AccountingRules as Rules;
use App\Models\CreditProfile;
use App\Models\EssentialsAdvance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** One portfolio calculation; production schedules take precedence over legacy copies. */
class EssentialsExposureSynchroniser
{
    public function synchronise(int $userId): array
    {
        $today = now()->toDateString();
        $debt = 0;
        $due = 0;
        $next = null;
        $productionLoanIds = [];
        if (Schema::hasTable('credit_repayment_schedule_items')) {
            $rows = DB::table('credit_repayment_schedule_items as schedule')->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $userId)->whereNull('loans.deleted_at')
                ->get(['schedule.loan_id', 'schedule.total_outstanding_minor', 'schedule.due_date']);
            foreach ($rows as $row) {
                $productionLoanIds[] = (int) $row->loan_id;
                $amount = Rules::amount((int) $row->total_outstanding_minor);
                $debt = Rules::add($debt, $amount);
                if ($amount > 0) {
                    if ($row->due_date <= $today) { $due = Rules::add($due, $amount); }
                    $next = $next === null || $row->due_date < $next ? $row->due_date : $next;
                }
            }
        }
        if (Schema::hasTable('loan_schedules')) {
            $legacy = DB::table('loan_schedules as schedule')->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $userId)->whereNull('loans.deleted_at')
                ->whereNotIn('schedule.loan_id', array_values(array_unique($productionLoanIds)))
                ->where('schedule.total_outstanding', '>', 0)->get(['schedule.total_outstanding', 'schedule.due_date']);
            foreach ($legacy as $row) {
                $text = (string) $row->total_outstanding;
                if (! preg_match('/^(\d+)(?:\.(\d+))?$/D', $text, $match)
                    || strlen($match[1]) > 16 || (int) $match[1] > 9007199254740990) {
                    throw new RuntimeException('An unsupported legacy financial value requires reconciliation.');
                }
                // This exposure gate rounds a positive legacy fraction up. It
                // does not alter the historical accounting or customer amount.
                $amount = (int) $match[1] + (trim($match[2] ?? '', '0') !== '' ? 1 : 0);
                $debt = Rules::add($debt, $amount);
                if ($row->due_date <= $today) { $due = Rules::add($due, $amount); }
                $next = $next === null || $row->due_date < $next ? $row->due_date : $next;
            }
        }
        $advances = [];
        foreach (EssentialsAdvance::where('user_id', $userId)->get() as $advance) {
            $rows = [];
            if (in_array($advance->status, Rules::ACTIVE_DEBT, true)) {
                $rows = DB::table('essentials_repayment_schedule_items')->where('advance_id', $advance->id)->get()
                    ->map(static function ($item): array {
                        $row = (array) $item;
                        foreach (['id', 'principal_outstanding_minor', 'interest_outstanding_minor', 'fees_outstanding_minor', 'total_outstanding_minor',
                            'principal_original_minor', 'interest_original_minor', 'fees_original_minor'] as $field) {
                            $row[$field] = (int) $row[$field];
                        }
                        return $row;
                    })->all();
            }
            $advances[] = ['status' => $advance->status, 'principal_minor' => $advance->principal_minor,
                'outstanding_minor' => $advance->outstanding_minor, 'schedule' => $rows];
        }
        $essentials = Rules::exposure($advances, $today);
        $debt = Rules::add($debt, $essentials['debt_minor']);
        $due = Rules::add($due, $essentials['due_minor']);
        $next = $essentials['next_due_date'] !== null && ($next === null || $essentials['next_due_date'] < $next)
            ? $essentials['next_due_date'] : $next;
        $exposure = Rules::add($debt, $essentials['reserved_minor']);
        $profile = CreditProfile::where('user_id', $userId)->lockForUpdate()->first();
        if ($profile) {
            // Reconciliation can cap headroom, never independently grant credit
            // or override a zero limit established by the existing risk policy.
            $ready = $profile->status === CreditProfile::STATUS_READY && $profile->expires_at?->isFuture();
            $profile->update(['current_exposure_minor' => $exposure, 'total_outstanding_minor' => $debt,
                'amount_due_minor' => $due, 'next_due_date' => $next,
                'available_to_borrow_minor' => $ready
                    ? min(max(0, $profile->available_to_borrow_minor), max(0, $profile->credit_limit_minor - $exposure)) : 0]);
        }
        return ['exposure_minor' => $exposure, 'total_outstanding_minor' => $debt, 'amount_due_minor' => $due,
            'next_due_date' => $next, 'essentials_reserved_minor' => $essentials['reserved_minor'], 'essentials_debt_minor' => $essentials['debt_minor']];
    }
}
