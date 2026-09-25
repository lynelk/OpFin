<?php

namespace App\Services\ClubAccounting;

use App\Models\ClubBook;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ClubSchedules
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function generateDueCalls(int $limit = 100): array
    {
        if (! Schema::hasTable('club_contribution_plans')) {
            return ['created' => 0, 'retired' => 0];
        }
        $limit = max(1, min(500, $limit));
        $today = now()->toDateString();
        $runReference = (string) Str::uuid();
        $plans = DB::table('club_contribution_plans')->where('status', 'active')->where('next_due_date', '<=', $today)
            ->orderBy('next_due_date')->orderBy('id')->limit($limit)->get(['id', 'book_id']);
        $created = 0;
        $retired = 0;
        foreach ($plans as $candidate) {
            $result = DB::transaction(function () use ($candidate, $today, $runReference): array {
                $book = ClubBook::query()->lockForUpdate()->find($candidate->book_id);
                if (! $book || $book->status !== 'active') {
                    return [0, 0];
                }
                $plan = DB::table('club_contribution_plans')->where('id', $candidate->id)->lockForUpdate()->first();
                if (! $plan || $plan->status !== 'active' || $plan->next_due_date > $today) {
                    return [0, 0];
                }
                $active = DB::table('financial_space_memberships as membership')->join('users as person', 'person.id', '=', 'membership.user_id')
                    ->where('membership.financial_space_id', $book->financial_space_id)->where('membership.user_id', $plan->member_user_id)
                    ->where('membership.status', 'active')->whereNull('membership.deleted_at')->whereNull('person.deleted_at')->exists();
                if (! $active || ($plan->end_date && $plan->next_due_date > $plan->end_date)) {
                    DB::table('club_contribution_plans')->where('id', $plan->id)->update(['status' => 'closed', 'updated_at' => now()]);
                    $this->audit->record('club.accounting.contribution_plan_closed', null, $book, [
                        'plan_id' => (int) $plan->id, 'run_reference' => $runReference, 'trigger' => 'scheduled',
                        'reason' => $active ? 'end_date_reached' : 'membership_inactive',
                    ]);

                    return [0, 1];
                }
                $due = CarbonImmutable::parse($plan->next_due_date);
                $reference = (string) Str::uuid();
                $inserted = DB::table('club_capital_calls')->insertOrIgnore([
                    'reference' => $reference, 'book_id' => $book->id, 'member_user_id' => $plan->member_user_id,
                    'plan_id' => $plan->id, 'due_date' => $due->toDateString(), 'amount_minor' => $plan->amount_minor,
                    'paid_minor' => 0, 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
                ]);
                $month = $due->startOfMonth()->addMonth();
                $next = $month->day(min((int) $plan->due_day, $month->daysInMonth));
                DB::table('club_contribution_plans')->where('id', $plan->id)
                    ->update(['next_due_date' => $next->toDateString(), 'updated_at' => now()]);
                $this->audit->record('club.accounting.contribution_plan_advanced', null, $book, [
                    'plan_id' => (int) $plan->id, 'run_reference' => $runReference, 'trigger' => 'scheduled',
                    'due_date' => $due->toDateString(), 'next_due_date' => $next->toDateString(),
                    'call_created' => $inserted === 1, 'call_reference' => $inserted === 1 ? $reference : null,
                ]);

                return [(int) $inserted, 0];
            }, 3);
            $created += $result[0];
            $retired += $result[1];
        }

        return ['created' => $created, 'retired' => $retired, 'creates_credit_or_receivable' => false, 'run_reference' => $runReference];
    }
}
