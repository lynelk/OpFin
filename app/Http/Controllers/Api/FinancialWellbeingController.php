<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FinancialWellbeingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinancialWellbeingController extends Controller
{
    public function __construct(
        private readonly FinancialWellbeingService $service,
        private readonly AuditLogger $auditLogger
    ) {}

    public function compass(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        return response()->json([
            'data' => $this->service->compass(
                $this->user($request),
                strtoupper($validated['currency'] ?? 'UGX')
            ),
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => [
                'categories' => FinancialWellbeingService::CATEGORIES,
            ],
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $accounts = $this->scope(DB::table('financial_accounts'), $this->user($request))
            ->orderByDesc('active')
            ->orderBy('display_name')
            ->get()
            ->map(fn ($account) => $this->accountPayload($account))
            ->values();

        return response()->json(['data' => ['accounts' => $accounts]]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'account_type' => ['required', Rule::in(['cash', 'mobile_money', 'bank', 'other'])],
            'balance_minor' => ['required', 'integer'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'observed_at' => ['nullable', 'date'],
        ]);
        $user = $this->user($request);
        $id = DB::table('financial_accounts')->insertGetId([
            'user_id' => $user->id,
            'institution_id' => $user->institution_id,
            'display_name' => $validated['display_name'],
            'account_type' => $validated['account_type'],
            'balance_minor' => $validated['balance_minor'],
            'currency' => strtoupper($validated['currency'] ?? 'UGX'),
            'confidence' => 'user_reported',
            'observed_at' => $validated['observed_at'] ?? now(),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('financial.account.recorded', $user, null, ['financial_account_id' => $id], $request);

        return response()->json([
            'data' => $this->accountPayload($this->account($user, $id)),
        ], 201);
    }

    public function updateAccount(Request $request, int $account): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:120'],
            'account_type' => ['sometimes', Rule::in(['cash', 'mobile_money', 'bank', 'other'])],
            'balance_minor' => ['sometimes', 'integer'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'observed_at' => ['sometimes', 'nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $user = $this->user($request);
        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }
        $validated['confidence'] = 'user_reported';
        $validated['updated_at'] = now();
        $this->account($user, $account);
        $this->scope(DB::table('financial_accounts'), $user)->where('id', $account)->update($validated);
        $this->auditLogger->record('financial.account.updated', $user, null, ['financial_account_id' => $account], $request);

        return response()->json([
            'data' => $this->accountPayload($this->account($user, $account)),
        ]);
    }

    public function destroyAccount(Request $request, int $account): JsonResponse
    {
        $user = $this->user($request);
        $this->account($user, $account);
        $this->scope(DB::table('financial_accounts'), $user)->where('id', $account)->delete();
        $this->auditLogger->record('financial.account.deleted', $user, null, ['financial_account_id' => $account], $request);

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function budgets(Request $request): JsonResponse
    {
        $currency = strtoupper((string) $request->query('currency', 'UGX'));
        $month = CarbonImmutable::parse((string) $request->query('month', now()->format('Y-m').'-01'));

        return response()->json([
            'data' => [
                'budgets' => $this->service->budgets(
                    $this->user($request),
                    $month->startOfMonth(),
                    $month->endOfMonth(),
                    $currency
                ),
            ],
        ]);
    }

    public function storeBudget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(FinancialWellbeingService::CATEGORIES)],
            'monthly_limit_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'alert_threshold_percent' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $user = $this->user($request);
        $id = DB::table('financial_budgets')->insertGetId([
            'user_id' => $user->id,
            'institution_id' => $user->institution_id,
            'category' => $validated['category'],
            'monthly_limit_minor' => $validated['monthly_limit_minor'],
            'currency' => strtoupper($validated['currency'] ?? 'UGX'),
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            'alert_threshold_percent' => $validated['alert_threshold_percent'] ?? 80,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('financial.budget.created', $user, null, ['financial_budget_id' => $id], $request);

        return response()->json(['data' => $this->budgetPayload($this->budget($user, $id))], 201);
    }

    public function updateBudget(Request $request, int $budget): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['sometimes', Rule::in(FinancialWellbeingService::CATEGORIES)],
            'monthly_limit_minor' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'effective_from' => ['sometimes', 'date'],
            'effective_to' => ['sometimes', 'nullable', 'date'],
            'alert_threshold_percent' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $user = $this->user($request);
        $this->budget($user, $budget);
        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }
        $validated['updated_at'] = now();
        $this->scope(DB::table('financial_budgets'), $user)->where('id', $budget)->update($validated);
        $this->auditLogger->record('financial.budget.updated', $user, null, ['financial_budget_id' => $budget], $request);

        return response()->json(['data' => $this->budgetPayload($this->budget($user, $budget))]);
    }

    public function destroyBudget(Request $request, int $budget): JsonResponse
    {
        $user = $this->user($request);
        $this->budget($user, $budget);
        $this->scope(DB::table('financial_budgets'), $user)->where('id', $budget)->delete();
        $this->auditLogger->record('financial.budget.deleted', $user, null, ['financial_budget_id' => $budget], $request);

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function cashFlow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);
        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])->startOfDay()
            : CarbonImmutable::now()->startOfMonth();
        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])->endOfDay()
            : CarbonImmutable::now()->endOfMonth();

        return response()->json([
            'data' => $this->service->cashFlow(
                $this->user($request),
                $from,
                $to,
                strtoupper($validated['currency'] ?? 'UGX')
            ),
        ]);
    }

    public function storeEntry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['income', 'expense'])],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'category' => ['nullable', Rule::in(FinancialWellbeingService::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['required', 'date'],
        ]);
        $user = $this->user($request);
        $category = $validated['category']
            ?? ($validated['direction'] === 'income' ? 'Other' : $this->service->suggestCategory($validated['description'] ?? null));
        $id = DB::table('financial_entries')->insertGetId([
            'user_id' => $user->id,
            'institution_id' => $user->institution_id,
            'direction' => $validated['direction'],
            'amount_minor' => $validated['amount_minor'],
            'currency' => strtoupper($validated['currency'] ?? 'UGX'),
            'category' => $category,
            'description' => $validated['description'] ?? null,
            'source' => 'manual',
            'occurred_at' => $validated['occurred_at'],
            'category_overridden' => isset($validated['category']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('financial.cash_flow.recorded', $user, null, ['financial_entry_id' => $id], $request);

        return response()->json(['data' => $this->entryPayload($this->entry($user, $id))], 201);
    }

    public function updateEntry(Request $request, int $entry): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['sometimes', Rule::in(FinancialWellbeingService::CATEGORIES)],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'occurred_at' => ['sometimes', 'date'],
        ]);
        $user = $this->user($request);
        $this->entry($user, $entry);
        if (isset($validated['category'])) {
            $validated['category_overridden'] = true;
        }
        $validated['updated_at'] = now();
        $this->scope(DB::table('financial_entries'), $user)->where('id', $entry)->update($validated);
        $this->auditLogger->record('financial.cash_flow.updated', $user, null, ['financial_entry_id' => $entry], $request);

        return response()->json(['data' => $this->entryPayload($this->entry($user, $entry))]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);
        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])->startOfDay()
            : CarbonImmutable::now()->startOfDay();
        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])->endOfDay()
            : $from->addDays(90)->endOfDay();

        return response()->json([
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'events' => $this->service->calendar(
                    $this->user($request),
                    $from,
                    $to,
                    strtoupper($validated['currency'] ?? 'UGX')
                ),
                'certainty_legend' => [
                    'confirmed' => 'Known obligation or income backed by system data.',
                    'scheduled' => 'User or provider scheduled event.',
                    'estimated' => 'User estimate; not guaranteed cash.',
                    'predicted' => 'Model-derived forecast; not guaranteed cash.',
                ],
            ],
        ]);
    }

    public function storeCalendarEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'event_type' => ['required', Rule::in(['income', 'bill', 'loan', 'savings', 'insurance', 'investment', 'subscription', 'other'])],
            'direction' => ['required', Rule::in(['income', 'expense'])],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'scheduled_for' => ['required', 'date'],
            'certainty' => ['sometimes', Rule::in(['scheduled', 'estimated'])],
            'recurrence' => ['nullable', Rule::in(['weekly', 'monthly'])],
            'category' => ['nullable', Rule::in(FinancialWellbeingService::CATEGORIES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $user = $this->user($request);
        $id = DB::table('financial_calendar_events')->insertGetId([
            'user_id' => $user->id,
            'institution_id' => $user->institution_id,
            'title' => $validated['title'],
            'event_type' => $validated['event_type'],
            'direction' => $validated['direction'],
            'amount_minor' => $validated['amount_minor'],
            'currency' => strtoupper($validated['currency'] ?? 'UGX'),
            'scheduled_for' => $validated['scheduled_for'],
            'certainty' => $validated['certainty'] ?? 'scheduled',
            'status' => 'upcoming',
            'recurrence' => $validated['recurrence'] ?? null,
            'category' => $validated['category'] ?? null,
            'source' => 'manual',
            'notes' => $validated['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('financial.calendar.created', $user, null, ['financial_calendar_event_id' => $id], $request);

        return response()->json(['data' => $this->calendarEventPayload($this->calendarEvent($user, $id))], 201);
    }

    public function updateCalendarEvent(Request $request, int $event): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'event_type' => ['sometimes', Rule::in(['income', 'bill', 'loan', 'savings', 'insurance', 'investment', 'subscription', 'other'])],
            'direction' => ['sometimes', Rule::in(['income', 'expense'])],
            'amount_minor' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'scheduled_for' => ['sometimes', 'date'],
            'certainty' => ['sometimes', Rule::in(['scheduled', 'estimated'])],
            'status' => ['sometimes', Rule::in(['upcoming', 'completed', 'cancelled'])],
            'recurrence' => ['sometimes', 'nullable', Rule::in(['weekly', 'monthly'])],
            'category' => ['sometimes', 'nullable', Rule::in(FinancialWellbeingService::CATEGORIES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
        $user = $this->user($request);
        $this->calendarEvent($user, $event);
        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }
        $validated['updated_at'] = now();
        $this->scope(DB::table('financial_calendar_events'), $user)->where('id', $event)->update($validated);
        $this->auditLogger->record('financial.calendar.updated', $user, null, ['financial_calendar_event_id' => $event], $request);

        return response()->json(['data' => $this->calendarEventPayload($this->calendarEvent($user, $event))]);
    }

    public function destroyCalendarEvent(Request $request, int $event): JsonResponse
    {
        $user = $this->user($request);
        $this->calendarEvent($user, $event);
        $this->scope(DB::table('financial_calendar_events'), $user)->where('id', $event)->delete();
        $this->auditLogger->record('financial.calendar.deleted', $user, null, ['financial_calendar_event_id' => $event], $request);

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function account(User $user, int $id): object
    {
        return $this->scope(DB::table('financial_accounts'), $user)->where('id', $id)->firstOrFail();
    }

    private function budget(User $user, int $id): object
    {
        return $this->scope(DB::table('financial_budgets'), $user)->where('id', $id)->firstOrFail();
    }

    private function entry(User $user, int $id): object
    {
        return $this->scope(DB::table('financial_entries'), $user)->where('id', $id)->firstOrFail();
    }

    private function calendarEvent(User $user, int $id): object
    {
        return $this->scope(DB::table('financial_calendar_events'), $user)->where('id', $id)->firstOrFail();
    }

    private function accountPayload(object $account): array
    {
        return [
            'id' => $account->id,
            'display_name' => $account->display_name,
            'account_type' => $account->account_type,
            'balance_minor' => (int) $account->balance_minor,
            'currency' => $account->currency,
            'confidence' => $account->confidence,
            'observed_at' => $account->observed_at,
            'active' => (bool) $account->active,
        ];
    }

    private function budgetPayload(object $budget): array
    {
        return [
            'id' => $budget->id,
            'category' => $budget->category,
            'monthly_limit_minor' => (int) $budget->monthly_limit_minor,
            'currency' => $budget->currency,
            'effective_from' => $budget->effective_from,
            'effective_to' => $budget->effective_to,
            'alert_threshold_percent' => (int) $budget->alert_threshold_percent,
            'active' => (bool) $budget->active,
        ];
    }

    private function entryPayload(object $entry): array
    {
        return [
            'id' => $entry->id,
            'direction' => $entry->direction,
            'amount_minor' => (int) $entry->amount_minor,
            'currency' => $entry->currency,
            'category' => $entry->category,
            'description' => $entry->description,
            'source' => $entry->source,
            'occurred_at' => $entry->occurred_at,
            'category_overridden' => (bool) $entry->category_overridden,
        ];
    }

    private function calendarEventPayload(object $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'event_type' => $event->event_type,
            'direction' => $event->direction,
            'amount_minor' => (int) $event->amount_minor,
            'currency' => $event->currency,
            'scheduled_for' => $event->scheduled_for,
            'certainty' => $event->certainty,
            'status' => $event->status,
            'recurrence' => $event->recurrence,
            'category' => $event->category,
            'notes' => $event->notes,
        ];
    }

    private function scope(Builder $query, User $user): Builder
    {
        $query->where('user_id', $user->id);

        return $user->institution_id === null
            ? $query->whereNull('institution_id')
            : $query->where('institution_id', $user->institution_id);
    }
}
