<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class V5P0PlatformService
{
    public function security(User $user): array
    {
        $control = DB::table('security_controls')->where('user_id', $user->id)->first();
        if (! $control) {
            DB::table('security_controls')->insert(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            $control = DB::table('security_controls')->where('user_id', $user->id)->first();
        }

        return [
            'controls' => $control,
            'events' => DB::table('security_events')->where('user_id', $user->id)->orderByDesc('occurred_at')->limit(30)->get(),
        ];
    }

    public function updateSecurity(User $user, array $data, string $actor, ?string $ip): array
    {
        $changes = array_intersect_key($data, array_flip(['transactions_frozen', 'login_alerts', 'payment_alerts']));
        if ($changes === []) {
            throw new InvalidArgumentException('No supported security control supplied.');
        }
        $changes += ['changed_by' => $actor, 'changed_at' => now(), 'updated_at' => now()];
        DB::table('security_controls')->updateOrInsert(['user_id' => $user->id], $changes + ['created_at' => now()]);
        DB::table('security_events')->insert([
            'user_id' => $user->id,
            'event_type' => 'security_controls_changed',
            'severity' => ($changes['transactions_frozen'] ?? false) ? 'high' : 'info',
            'source' => 'security_centre',
            'ip_address' => $ip,
            'metadata' => json_encode($changes),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->security($user);
    }

    public function creditBuilder(User $user): array
    {
        $plan = DB::table('credit_builder_plans')->where('user_id', $user->id)->where('status', 'active')->latest('id')->first();
        $outstanding = 0;
        $overdue = 0;
        if (Schema::hasTable('credit_repayment_schedule_items')) {
            $base = DB::table('credit_repayment_schedule_items as s')->join('loans', 'loans.id', '=', 's.loan_id')->where('loans.user_id', $user->id);
            $outstanding = (int) (clone $base)->sum('s.total_outstanding_minor');
            $overdue = (int) (clone $base)->whereDate('s.due_date', '<', now()->toDateString())->where('s.total_outstanding_minor', '>', 0)->count();
        }

        return [
            'plan' => $plan,
            'factors' => ['outstanding_debt_minor' => $outstanding, 'overdue_instalments' => $overdue, 'on_time_signal' => $overdue === 0 ? 'positive' : 'attention'],
            'explanation' => 'Credit Builder uses confirmed OpFin repayment data and user-approved goals. It does not fabricate a bureau score.',
        ];
    }

    public function saveCreditBuilder(User $user, array $data): array
    {
        $target = isset($data['target_score']) ? (int) $data['target_score'] : null;
        if ($target !== null && ($target < 0 || $target > 100)) {
            throw new InvalidArgumentException('target_score must be between 0 and 100.');
        }
        DB::table('credit_builder_plans')->where('user_id', $user->id)->where('status', 'active')->update(['status' => 'superseded', 'updated_at' => now()]);
        DB::table('credit_builder_plans')->insert([
            'user_id' => $user->id,
            'institution_id' => $user->institution_id ?? null,
            'goal' => $data['goal'] ?? null,
            'baseline_score' => $data['baseline_score'] ?? null,
            'target_score' => $target,
            'status' => 'active',
            'actions' => json_encode($data['actions'] ?? []),
            'review_due_at' => $data['review_due_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->creditBuilder($user);
    }

    public function hardship(User $user): array
    {
        return DB::table('hardship_cases')->where('user_id', $user->id)->orderByDesc('id')->get()->map(fn ($row) => $this->hardshipPayload($row))->all();
    }

    public function openHardship(User $user, array $data, string $actor): array
    {
        foreach (['reason', 'monthly_income_minor', 'essential_expenses_minor', 'debt_commitments_minor'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidArgumentException($field.' is required.');
            }
        }
        $id = DB::table('hardship_cases')->insertGetId([
            'user_id' => $user->id,
            'institution_id' => $user->institution_id ?? null,
            'reason' => trim((string) $data['reason']),
            'status' => 'submitted',
            'monthly_income_minor' => max(0, (int) $data['monthly_income_minor']),
            'essential_expenses_minor' => max(0, (int) $data['essential_expenses_minor']),
            'debt_commitments_minor' => max(0, (int) $data['debt_commitments_minor']),
            'requested_relief' => json_encode($data['requested_relief'] ?? []),
            'requested_by' => $actor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->hardshipPayload(DB::table('hardship_cases')->find($id));
    }

    public function approveHardship(int $id, array $relief, string $actor): array
    {
        return DB::transaction(function () use ($id, $relief, $actor) {
            $case = DB::table('hardship_cases')->lockForUpdate()->find($id);
            if (! $case) {
                throw new InvalidArgumentException('Hardship case not found.');
            }
            if ($case->requested_by === $actor) {
                throw new InvalidArgumentException('Hardship approval requires maker-checker separation.');
            }
            if ($case->status !== 'submitted') {
                throw new InvalidArgumentException('Only submitted hardship cases can be approved.');
            }
            DB::table('hardship_cases')->where('id', $id)->update(['status' => 'approved', 'approved_relief' => json_encode($relief), 'approved_by' => $actor, 'approved_at' => now(), 'updated_at' => now()]);

            return $this->hardshipPayload(DB::table('hardship_cases')->find($id));
        });
    }

    public function passport(User $user): array
    {
        $content = [
            'user_id' => $user->id,
            'generated_at' => now()->toIso8601String(),
            'financial_position' => [
                'recorded_accounts' => Schema::hasTable('financial_accounts') ? DB::table('financial_accounts')->where('user_id', $user->id)->where('active', true)->count() : 0,
                'recorded_balance_minor' => Schema::hasTable('financial_accounts') ? (int) DB::table('financial_accounts')->where('user_id', $user->id)->where('active', true)->sum('balance_minor') : 0,
                'outstanding_debt_minor' => $this->outstandingDebt($user->id),
            ],
            'consents' => Schema::hasTable('consents') ? DB::table('consents')->where('user_id', $user->id)->get() : collect(),
            'kyc' => Schema::hasTable('kyc_cases') ? DB::table('kyc_cases')->where('user_id', $user->id)->latest('id')->first() : null,
        ];
        $provenance = ['balances' => 'user_recorded_or_imported', 'debt' => 'opfin_repayment_schedule', 'identity' => 'opfin_kyc', 'consent' => 'opfin_consent_registry'];
        $hash = hash('sha256', json_encode([$content, $provenance], JSON_UNESCAPED_SLASHES));
        DB::table('financial_passport_snapshots')->updateOrInsert(['content_hash' => $hash], [
            'user_id' => $user->id,
            'version' => 1,
            'content' => json_encode($content),
            'provenance' => json_encode($provenance),
            'confidence' => 'mixed',
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['content' => $content, 'provenance' => $provenance, 'confidence' => 'mixed', 'content_hash' => $hash];
    }

    public function reconciliation(User $user): array
    {
        if (! Schema::hasTable('mobile_money_transactions')) {
            return ['total' => 0, 'matched' => 0, 'open' => 0, 'mismatch' => 0, 'items' => []];
        }
        $base = DB::table('mobile_money_transactions')->where('user_id', $user->id);

        return [
            'total' => (clone $base)->count(),
            'matched' => (clone $base)->where('reconciliation_status', 'matched')->count(),
            'open' => (clone $base)->whereIn('reconciliation_status', ['pending', 'open'])->count(),
            'mismatch' => (clone $base)->where('reconciliation_status', 'mismatch')->count(),
            'items' => (clone $base)->orderByDesc('id')->limit(50)->get(),
        ];
    }

    public function createProduct(array $data, string $actor): object
    {
        foreach (['product_code', 'name', 'definition'] as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException($field.' is required.');
            }
        }
        $code = strtoupper(trim((string) $data['product_code']));
        $version = ((int) (DB::table('product_definitions')->where('product_code', $code)->max('version') ?? 0)) + 1;
        $id = DB::table('product_definitions')->insertGetId(['product_code' => $code, 'version' => $version, 'name' => $data['name'], 'status' => 'draft', 'definition' => json_encode($data['definition']), 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('product_definitions')->find($id);
    }

    public function productTransition(int $id, string $target, string $actor): object
    {
        return DB::transaction(function () use ($id, $target, $actor) {
            $row = DB::table('product_definitions')->lockForUpdate()->find($id);
            if (! $row) {
                throw new InvalidArgumentException('Product definition not found.');
            }
            $allowed = ['draft' => ['submitted'], 'submitted' => ['approved'], 'approved' => ['active'], 'active' => ['retired']];
            if (! in_array($target, $allowed[$row->status] ?? [], true)) {
                throw new InvalidArgumentException('Invalid product lifecycle transition.');
            }
            $updates = ['status' => $target, 'updated_at' => now()];
            if ($target === 'submitted') {
                $updates += ['submitted_by' => $actor, 'submitted_at' => now()];
            }
            if ($target === 'approved') {
                if ($row->submitted_by === $actor) {
                    throw new InvalidArgumentException('Product approval requires maker-checker separation.');
                }
                $updates += ['approved_by' => $actor, 'approved_at' => now()];
            }
            if ($target === 'active' && ! $row->approved_by) {
                throw new InvalidArgumentException('Product must be independently approved before activation.');
            }
            DB::table('product_definitions')->where('id', $id)->update($updates);

            return DB::table('product_definitions')->find($id);
        });
    }

    public function createRule(array $data, string $actor): object
    {
        foreach (['rule_code', 'name', 'conditions', 'actions'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidArgumentException($field.' is required.');
            }
        }
        $code = strtoupper(trim((string) $data['rule_code']));
        $version = ((int) (DB::table('decision_rules')->where('rule_code', $code)->max('version') ?? 0)) + 1;
        $id = DB::table('decision_rules')->insertGetId(['rule_code' => $code, 'version' => $version, 'name' => $data['name'], 'priority' => (int) ($data['priority'] ?? 100), 'status' => 'draft', 'conditions' => json_encode($data['conditions']), 'actions' => json_encode($data['actions']), 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('decision_rules')->find($id);
    }

    public function approveRule(int $id, string $actor): object
    {
        return DB::transaction(function () use ($id, $actor) {
            $rule = DB::table('decision_rules')->lockForUpdate()->find($id);
            if (! $rule) {
                throw new InvalidArgumentException('Rule not found.');
            }
            if ($rule->created_by === $actor) {
                throw new InvalidArgumentException('Rule approval requires maker-checker separation.');
            }
            DB::table('decision_rules')->where('id', $id)->update(['status' => 'active', 'approved_by' => $actor, 'approved_at' => now(), 'updated_at' => now()]);

            return DB::table('decision_rules')->find($id);
        });
    }

    public function evaluateRules(array $context): array
    {
        $matched = [];
        foreach (DB::table('decision_rules')->where('status', 'active')->orderBy('priority')->get() as $rule) {
            $conditions = json_decode($rule->conditions, true) ?: [];
            if ($this->conditionsMatch($conditions, $context)) {
                $matched[] = ['rule_code' => $rule->rule_code, 'version' => $rule->version, 'actions' => json_decode($rule->actions, true) ?: []];
            }
        }

        return $matched;
    }

    public function createWorkflow(array $data, string $actor): object
    {
        foreach (['workflow_code', 'name', 'states', 'transitions'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidArgumentException($field.' is required.');
            }
        }
        $code = strtoupper(trim((string) $data['workflow_code']));
        $version = ((int) (DB::table('workflow_definitions')->where('workflow_code', $code)->max('version') ?? 0)) + 1;
        $id = DB::table('workflow_definitions')->insertGetId(['workflow_code' => $code, 'version' => $version, 'name' => $data['name'], 'status' => 'draft', 'states' => json_encode($data['states']), 'transitions' => json_encode($data['transitions']), 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('workflow_definitions')->find($id);
    }

    public function approveWorkflow(int $id, string $actor): object
    {
        return DB::transaction(function () use ($id, $actor) {
            $workflow = DB::table('workflow_definitions')->lockForUpdate()->find($id);
            if (! $workflow) {
                throw new InvalidArgumentException('Workflow not found.');
            }
            if ($workflow->created_by === $actor) {
                throw new InvalidArgumentException('Workflow approval requires maker-checker separation.');
            }
            DB::table('workflow_definitions')->where('id', $id)->update(['status' => 'active', 'approved_by' => $actor, 'approved_at' => now(), 'updated_at' => now()]);

            return DB::table('workflow_definitions')->find($id);
        });
    }

    public function startWorkflow(int $definitionId, ?User $user, string $subjectType, string $subjectReference, array $context): object
    {
        $workflow = DB::table('workflow_definitions')->where('id', $definitionId)->where('status', 'active')->first();
        if (! $workflow) {
            throw new InvalidArgumentException('Active workflow not found.');
        }
        $states = json_decode($workflow->states, true) ?: [];
        $initial = $states[0] ?? null;
        if (! $initial) {
            throw new InvalidArgumentException('Workflow has no states.');
        }
        $id = DB::table('workflow_runs')->insertGetId(['workflow_definition_id' => $definitionId, 'user_id' => $user?->id, 'subject_type' => $subjectType, 'subject_reference' => $subjectReference, 'current_state' => $initial, 'status' => 'running', 'context' => json_encode($context), 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('workflow_runs')->find($id);
    }

    public function transitionWorkflow(int $runId, string $toState, string $actor, array $context = []): object
    {
        return DB::transaction(function () use ($runId, $toState, $actor, $context) {
            $run = DB::table('workflow_runs')->lockForUpdate()->find($runId);
            if (! $run || $run->status !== 'running') {
                throw new InvalidArgumentException('Running workflow not found.');
            }
            $workflow = DB::table('workflow_definitions')->find($run->workflow_definition_id);
            $transitions = json_decode($workflow->transitions, true) ?: [];
            $allowed = collect($transitions)->contains(fn ($transition) => ($transition['from'] ?? null) === $run->current_state && ($transition['to'] ?? null) === $toState);
            if (! $allowed) {
                throw new InvalidArgumentException('Workflow transition is not allowed.');
            }
            $states = json_decode($workflow->states, true) ?: [];
            $finished = $toState === end($states);
            DB::table('workflow_runs')->where('id', $runId)->update(['current_state' => $toState, 'status' => $finished ? 'completed' : 'running', 'context' => json_encode(array_merge(json_decode($run->context, true) ?: [], $context)), 'finished_at' => $finished ? now() : null, 'updated_at' => now()]);
            DB::table('workflow_transition_events')->insert(['workflow_run_id' => $runId, 'from_state' => $run->current_state, 'to_state' => $toState, 'actor' => $actor, 'context' => json_encode($context), 'transitioned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('workflow_runs')->find($runId);
        });
    }

    private function outstandingDebt(int $userId): int
    {
        if (! Schema::hasTable('credit_repayment_schedule_items')) {
            return 0;
        }

        return (int) DB::table('credit_repayment_schedule_items as s')->join('loans', 'loans.id', '=', 's.loan_id')->where('loans.user_id', $userId)->sum('s.total_outstanding_minor');
    }

    private function hardshipPayload(object $case): array
    {
        return [
            'id' => $case->id,
            'status' => $case->status,
            'reason' => $case->reason,
            'monthly_income_minor' => (int) $case->monthly_income_minor,
            'essential_expenses_minor' => (int) $case->essential_expenses_minor,
            'debt_commitments_minor' => (int) $case->debt_commitments_minor,
            'disposable_income_minor' => (int) $case->monthly_income_minor - (int) $case->essential_expenses_minor - (int) $case->debt_commitments_minor,
            'requested_relief' => json_decode($case->requested_relief ?? '[]', true),
            'approved_relief' => json_decode($case->approved_relief ?? '[]', true),
            'approved_by' => $case->approved_by,
            'approved_at' => $case->approved_at,
        ];
    }

    private function conditionsMatch(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            $actual = data_get($context, $condition['field'] ?? '');
            $expected = $condition['value'] ?? null;
            $ok = match ($condition['operator'] ?? 'eq') {
                'eq' => $actual == $expected,
                'neq' => $actual != $expected,
                'gt' => $actual > $expected,
                'gte' => $actual >= $expected,
                'lt' => $actual < $expected,
                'lte' => $actual <= $expected,
                'in' => in_array($actual, (array) $expected, true),
                default => false,
            };
            if (! $ok) {
                return false;
            }
        }

        return true;
    }
}
