<?php

namespace App\Services;

use App\Models\MobileMoneyTransaction;
use App\Models\SavingsGoal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialWellbeingService
{
    public const CATEGORIES = [
        'Food', 'Transport', 'Rent', 'Utilities', 'School Fees', 'Health', 'Airtime/Data',
        'Business Stock', 'Family Support', 'Loan Repayment', 'Savings', 'Insurance',
        'Entertainment', 'Transfers', 'Other',
    ];

    public function compass(User $user, string $currency = 'UGX', ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now();
        $currency = strtoupper($currency);
        $monthStart = $asOf->startOfMonth();
        $monthEnd = $asOf->endOfMonth();
        $horizon = $asOf->addDays(30)->endOfDay();

        $accounts = $this->scope(DB::table('financial_accounts'), $user)->where('active', true)->where('currency', $currency)->get();
        $availableMinor = $accounts->isEmpty() ? null : (int) $accounts->sum('balance_minor');
        $calendar = $this->calendar($user, $asOf, $horizon, $currency);
        $committedMinor = (int) collect($calendar)->where('direction', 'expense')->whereIn('certainty', ['confirmed', 'scheduled'])->sum('amount_minor');
        $safeToSpendMinor = $availableMinor === null ? null : max(0, $availableMinor - $committedMinor);
        $debtMinor = $this->debtOutstandingMinor($user, $currency);
        $savingsMinor = $currency === 'UGX' ? $this->savingsBalanceMinor($user) : 0;
        $nextIncome = collect($calendar)->where('direction', 'income')->whereIn('certainty', ['confirmed', 'scheduled'])->sortBy('scheduled_for')->first();
        $cashFlow = $this->cashFlow($user, $monthStart, $monthEnd, $currency);
        $budgets = $this->budgets($user, $monthStart, $monthEnd, $currency);
        $goals = $this->goals($user);

        return [
            'as_of' => $asOf->toIso8601String(),
            'currency' => $currency,
            'position' => [
                'available_money_minor' => $availableMinor,
                'available_money_confidence' => $this->balanceConfidence($accounts),
                'committed_money_minor' => $committedMinor,
                'safe_to_spend_minor' => $safeToSpendMinor,
                'debt_obligations_minor' => $debtMinor,
                'current_savings_minor' => $savingsMinor,
                'upcoming_obligations_minor' => $committedMinor,
                'next_income_event' => $nextIncome,
                'safe_to_spend_explanation' => $availableMinor === null
                    ? 'Safe-to-spend is unavailable until a current balance source is recorded. OpFin does not invent external-account cash.'
                    : 'Safe-to-spend equals recorded available money less confirmed and scheduled obligations in the next 30 days.',
            ],
            'cash_flow' => $cashFlow,
            'budgets' => $budgets,
            'calendar' => array_values(array_slice($calendar, 0, 20)),
            'goals' => $goals,
            'activity' => $this->activity($user, $currency),
            'next_best_action' => $this->nextBestAction($availableMinor, $safeToSpendMinor, $committedMinor, $budgets, $calendar, $goals),
        ];
    }

    public function cashFlow(User $user, CarbonImmutable $from, CarbonImmutable $to, string $currency = 'UGX'): array
    {
        $entries = $this->scope(DB::table('financial_entries'), $user)
            ->where('currency', strtoupper($currency))->whereBetween('occurred_at', [$from, $to])->orderByDesc('occurred_at')->get();
        $income = (int) $entries->where('direction', 'income')->sum('amount_minor');
        $expenses = (int) $entries->where('direction', 'expense')->sum('amount_minor');
        $byCategory = $entries->where('direction', 'expense')->groupBy('category')->map(fn (Collection $rows, string $category) => [
            'category' => $category,
            'amount_minor' => (int) $rows->sum('amount_minor'),
        ])->values()->all();

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(), 'currency' => strtoupper($currency),
            'income_minor' => $income, 'expense_minor' => $expenses, 'net_minor' => $income - $expenses,
            'expense_by_category' => $byCategory, 'scope' => 'user_recorded_or_imported_entries',
            'entries' => $entries->map(fn ($entry) => $this->entryPayload($entry))->values()->all(),
        ];
    }

    public function budgets(User $user, CarbonImmutable $from, CarbonImmutable $to, string $currency = 'UGX'): array
    {
        $currency = strtoupper($currency);
        $budgets = $this->scope(DB::table('financial_budgets'), $user)
            ->where('active', true)->where('currency', $currency)->whereDate('effective_from', '<=', $to->toDateString())
            ->where(function (Builder $query) use ($from) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from->toDateString());
            })->orderBy('category')->get();

        return $budgets->map(function ($budget) use ($user, $from, $to, $currency) {
            $actual = (int) $this->scope(DB::table('financial_entries'), $user)
                ->where('direction', 'expense')->where('currency', $currency)->where('category', $budget->category)
                ->whereBetween('occurred_at', [$from, $to])->sum('amount_minor');
            $limit = (int) $budget->monthly_limit_minor;
            $percent = $limit > 0 ? round(($actual / $limit) * 100, 1) : 0.0;

            return [
                'id' => $budget->id, 'category' => $budget->category, 'monthly_limit_minor' => $limit,
                'actual_minor' => $actual, 'remaining_minor' => max(0, $limit - $actual),
                'utilization_percent' => $percent, 'alert_threshold_percent' => (int) $budget->alert_threshold_percent,
                'status' => $actual > $limit ? 'over_budget' : ($percent >= (int) $budget->alert_threshold_percent ? 'attention' : 'on_track'),
                'currency' => $currency,
            ];
        })->values()->all();
    }

    public function calendar(User $user, CarbonImmutable $from, CarbonImmutable $to, string $currency = 'UGX'): array
    {
        $currency = strtoupper($currency);
        $manual = $this->scope(DB::table('financial_calendar_events'), $user)
            ->where('status', 'upcoming')->where('currency', $currency)
            ->where(function (Builder $query) use ($to) {
                $query->whereDate('scheduled_for', '<=', $to->toDateString())->orWhereNotNull('recurrence');
            })->get();

        $events = [];
        foreach ($manual as $event) {
            foreach ($this->projectEvent($event, $from, $to) as $projected) {
                $events[] = $projected;
            }
        }

        if ($currency === 'UGX' && Schema::hasTable('credit_repayment_schedule_items')) {
            $loanEvents = DB::table('credit_repayment_schedule_items as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')->where('loans.user_id', $user->id)
                ->where('schedule.total_outstanding_minor', '>', 0)->whereBetween('schedule.due_date', [$from->toDateString(), $to->toDateString()])
                ->orderBy('schedule.due_date')->get();
            foreach ($loanEvents as $event) {
                $events[] = [
                    'id' => 'loan-'.$event->id, 'title' => 'Loan instalment', 'event_type' => 'loan', 'direction' => 'expense',
                    'amount_minor' => (int) $event->total_outstanding_minor, 'currency' => 'UGX',
                    'scheduled_for' => CarbonImmutable::parse($event->due_date)->startOfDay()->toIso8601String(),
                    'certainty' => 'confirmed', 'status' => 'upcoming', 'category' => 'Loan Repayment', 'source' => 'loan_schedule',
                    'source_reference' => (string) $event->loan_id, 'recurrence' => null, 'derived' => true,
                ];
            }
        }

        usort($events, fn (array $a, array $b) => strcmp($a['scheduled_for'], $b['scheduled_for']));

        return $events;
    }

    public function suggestCategory(?string $description): string
    {
        $description = strtolower(trim((string) $description));
        if ($description === '') {
            return 'Other';
        }
        $rules = [
            'Food' => ['food', 'restaurant', 'lunch', 'dinner', 'grocery', 'supermarket'],
            'Transport' => ['fuel', 'taxi', 'uber', 'boda', 'transport', 'bus'], 'Rent' => ['rent', 'landlord'],
            'Utilities' => ['water', 'electricity', 'umeme', 'utility'], 'School Fees' => ['school', 'tuition', 'fees'],
            'Health' => ['hospital', 'clinic', 'pharmacy', 'medical', 'health'], 'Airtime/Data' => ['airtime', 'data bundle', 'internet', 'telecom'],
            'Business Stock' => ['stock', 'inventory', 'supplier'], 'Family Support' => ['family', 'support'],
            'Loan Repayment' => ['loan', 'repayment'], 'Savings' => ['save', 'savings'], 'Insurance' => ['insurance', 'premium'],
            'Entertainment' => ['bar', 'cinema', 'entertainment'], 'Transfers' => ['transfer', 'send money', 'remittance'],
        ];
        foreach ($rules as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($description, $keyword)) {
                    return $category;
                }
            }
        }

        return 'Other';
    }

    private function debtOutstandingMinor(User $user, string $currency): int
    {
        if ($currency !== 'UGX') {
            return 0;
        }

        $production = 0;
        if (Schema::hasTable('credit_repayment_schedule_items')) {
            $production = (int) DB::table('credit_repayment_schedule_items as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $user->id)
                ->sum('schedule.total_outstanding_minor');
        }

        $legacy = 0;
        if (Schema::hasTable('loan_schedules')) {
            $legacyValue = (float) DB::table('loan_schedules as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $user->id)
                ->whereNull('loans.credit_offer_id')
                ->sum('schedule.total_outstanding');
            $legacy = (int) round($legacyValue);
        }

        return $production + $legacy;
    }

    private function savingsBalanceMinor(User $user): int
    {
        return SavingsGoal::query()->where('user_id', $user->id)
            ->whereIn('status', [SavingsGoal::STATUS_ACTIVE, SavingsGoal::STATUS_PAUSED, SavingsGoal::STATUS_COMPLETED])
            ->get()->sum(fn (SavingsGoal $goal) => $goal->confirmedBalanceMinor());
    }

    private function goals(User $user): array
    {
        return SavingsGoal::query()->where('user_id', $user->id)
            ->whereIn('status', [SavingsGoal::STATUS_ACTIVE, SavingsGoal::STATUS_PAUSED])
            ->orderByRaw('target_date is null, target_date asc')->limit(3)->get()
            ->map(fn (SavingsGoal $goal) => [
                'reference' => $goal->goal_reference, 'name' => $goal->name,
                'target_amount_minor' => (int) $goal->target_amount_minor, 'balance_minor' => $goal->confirmedBalanceMinor(),
                'target_date' => $goal->target_date?->toDateString(), 'status' => $goal->status,
            ])->values()->all();
    }

    private function activity(User $user, string $currency): array
    {
        $entries = $this->scope(DB::table('financial_entries'), $user)->where('currency', $currency)->orderByDesc('occurred_at')->limit(10)->get()
            ->map(fn ($entry) => [
                'type' => 'cash_flow', 'reference' => (string) $entry->id, 'title' => $entry->description ?: $entry->category,
                'direction' => $entry->direction, 'amount_minor' => (int) $entry->amount_minor, 'currency' => $entry->currency,
                'status' => 'recorded', 'occurred_at' => CarbonImmutable::parse($entry->occurred_at)->toIso8601String(),
            ]);
        $payments = MobileMoneyTransaction::query()->where('user_id', $user->id)->where('currency', $currency)->orderByDesc('created_at')->limit(10)->get()
            ->map(fn (MobileMoneyTransaction $payment) => [
                'type' => 'payment', 'reference' => $payment->internal_reference,
                'title' => $payment->direction === MobileMoneyTransaction::DIRECTION_COLLECTION ? 'Money collected' : 'Money sent',
                'direction' => $payment->direction === MobileMoneyTransaction::DIRECTION_COLLECTION ? 'expense' : 'income',
                'amount_minor' => (int) $payment->amount_minor, 'currency' => $payment->currency, 'status' => $payment->status,
                'occurred_at' => $payment->created_at?->toIso8601String(),
            ]);

        return $entries->concat($payments)->sortByDesc('occurred_at')->take(12)->values()->all();
    }

    private function nextBestAction(?int $availableMinor, ?int $safeToSpendMinor, int $committedMinor, array $budgets, array $calendar, array $goals): array
    {
        if ($availableMinor === null) {
            return ['code' => 'record_balance', 'title' => 'Add your current balance', 'text' => 'Record a current cash, mobile-money or bank balance so OpFin can calculate safe-to-spend without guessing.', 'href' => '/money', 'action' => 'Add balance'];
        }
        if ($committedMinor > $availableMinor) {
            return ['code' => 'cashflow_shortfall', 'title' => 'Review an upcoming shortfall', 'text' => 'Confirmed and scheduled obligations in the next 30 days are greater than your recorded available money.', 'href' => '/calendar', 'action' => 'Review calendar'];
        }
        $overBudget = collect($budgets)->firstWhere('status', 'over_budget');
        if ($overBudget) {
            return ['code' => 'budget_overrun', 'title' => 'Review '.$overBudget['category'].' spending', 'text' => 'This category is above its monthly budget. Review recent entries before making new commitments.', 'href' => '/money', 'action' => 'Review budget'];
        }
        $loanDue = collect($calendar)->first(fn (array $event) => $event['event_type'] === 'loan');
        if ($loanDue) {
            return ['code' => 'loan_due', 'title' => 'Prepare for your next loan payment', 'text' => 'A confirmed loan instalment is coming up. Keeping it funded protects your repayment record.', 'href' => '/loans/account', 'action' => 'View loan'];
        }
        if ($goals !== []) {
            return ['code' => 'build_goal', 'title' => 'Keep building '.$goals[0]['name'], 'text' => 'Your recorded safe-to-spend is '.($safeToSpendMinor ?? 0).' minor units after upcoming commitments.', 'href' => '/save', 'action' => 'View goal'];
        }

        return ['code' => 'review_position', 'title' => 'Your next 30 days are funded', 'text' => 'Review your budget and calendar before adding another recurring commitment.', 'href' => '/money', 'action' => 'Review money plan'];
    }

    private function projectEvent($event, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $scheduled = CarbonImmutable::parse($event->scheduled_for);
        $recurrence = $event->recurrence;
        $dates = [];
        if (! in_array($recurrence, ['weekly', 'monthly'], true)) {
            if ($scheduled->betweenIncluded($from, $to)) {
                $dates[] = $scheduled;
            }
        } else {
            while ($scheduled->lt($from)) {
                $scheduled = $recurrence === 'weekly' ? $scheduled->addWeek() : $scheduled->addMonthNoOverflow();
            }
            while ($scheduled->lte($to)) {
                $dates[] = $scheduled;
                $scheduled = $recurrence === 'weekly' ? $scheduled->addWeek() : $scheduled->addMonthNoOverflow();
            }
        }

        return array_map(fn (CarbonImmutable $date) => [
            'id' => (string) $event->id, 'title' => $event->title, 'event_type' => $event->event_type,
            'direction' => $event->direction, 'amount_minor' => (int) $event->amount_minor, 'currency' => $event->currency,
            'scheduled_for' => $date->toIso8601String(), 'certainty' => $event->certainty, 'status' => $event->status,
            'category' => $event->category, 'source' => $event->source, 'source_reference' => $event->source_reference,
            'recurrence' => $event->recurrence, 'derived' => false,
        ], $dates);
    }

    private function balanceConfidence(Collection $accounts): ?string
    {
        if ($accounts->isEmpty()) {
            return null;
        }
        $values = $accounts->pluck('confidence')->unique()->values();
        if ($values->count() === 1) {
            return $values->first() === 'connected_verified' ? 'verified' : $values->first();
        }

        return 'mixed';
    }

    private function entryPayload($entry): array
    {
        return [
            'id' => $entry->id, 'direction' => $entry->direction, 'amount_minor' => (int) $entry->amount_minor,
            'currency' => $entry->currency, 'category' => $entry->category, 'description' => $entry->description,
            'source' => $entry->source, 'source_reference' => $entry->source_reference,
            'occurred_at' => CarbonImmutable::parse($entry->occurred_at)->toIso8601String(),
            'category_overridden' => (bool) $entry->category_overridden,
        ];
    }

    private function scope(Builder $query, User $user): Builder
    {
        $query->where('user_id', $user->id);

        return $user->institution_id === null ? $query->whereNull('institution_id') : $query->where('institution_id', $user->institution_id);
    }
}
