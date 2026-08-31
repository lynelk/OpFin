<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ExperiencePlatformService
{
    public function activation(User $user): array
    {
        $profile = DB::table('customer_activation_profiles')->where('user_id', $user->id)->first();
        $kyc = Schema::hasTable('kyc_cases') ? DB::table('kyc_cases')->where('user_id', $user->id)->latest('id')->first() : null;
        $accounts = Schema::hasTable('financial_accounts') ? DB::table('financial_accounts')->where('user_id', $user->id)->where('active', true)->count() : 0;
        $consents = Schema::hasTable('consents') ? DB::table('consents')->where('user_id', $user->id)->where('status', 'granted')->get() : collect();

        $steps = [
            ['code' => 'secure_account', 'essential' => true, 'complete' => (bool) $user->phone_verified_at],
            ['code' => 'verify_identity', 'essential' => true, 'complete' => ($kyc->status ?? null) === 'verified'],
            ['code' => 'build_money_picture', 'essential' => true, 'complete' => $accounts > 0],
            ['code' => 'choose_primary_goal', 'essential' => false, 'complete' => ! empty($profile?->primary_financial_goal)],
            ['code' => 'review_permissions', 'essential' => false, 'complete' => $consents->isNotEmpty()],
        ];

        $essential = collect($steps)->where('essential', true);
        $essentialComplete = $essential->where('complete', true)->count();
        $complete = $essentialComplete === $essential->count();

        if ($complete && ! $profile?->onboarding_completed_at) {
            DB::table('customer_activation_profiles')->updateOrInsert(
                ['user_id' => $user->id],
                ['onboarding_completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]
            );
            $profile = DB::table('customer_activation_profiles')->where('user_id', $user->id)->first();
        }

        return [
            'profile' => $profile,
            'steps' => $steps,
            'essential_complete' => $essentialComplete,
            'essential_total' => $essential->count(),
            'activation_percent' => (int) round(($essentialComplete / max(1, $essential->count())) * 100),
            'activation_complete' => $complete,
        ];
    }

    public function saveActivation(User $user, array $data): array
    {
        $allowedGoals = ['control_spending', 'build_emergency_fund', 'borrow_responsibly', 'protect_family', 'grow_money'];
        if (isset($data['primary_financial_goal']) && ! in_array($data['primary_financial_goal'], $allowedGoals, true)) {
            throw new InvalidArgumentException('Unsupported primary financial goal.');
        }

        $changes = array_intersect_key($data, array_flip(['primary_financial_goal', 'preferred_language', 'notifications_enabled']));
        if ($changes === []) {
            throw new InvalidArgumentException('No supported activation preference supplied.');
        }

        DB::table('customer_activation_profiles')->updateOrInsert(
            ['user_id' => $user->id],
            $changes + ['created_at' => now(), 'updated_at' => now()]
        );

        return $this->activation($user);
    }

    public function moneyAutopilot(User $user): array
    {
        return [
            'rules' => DB::table('money_autopilot_rules')->where('user_id', $user->id)->orderByDesc('id')->get()->map(fn ($row) => $this->decode($row, ['trigger_config', 'action_config'])),
            'recent_executions' => DB::table('money_autopilot_executions')->where('user_id', $user->id)->orderByDesc('id')->limit(20)->get()->map(fn ($row) => $this->decode($row, ['evidence'])),
            'guardrail' => 'Money Autopilot may evaluate and queue user-authorised rules, but external money movement remains provider-gated and auditable.',
        ];
    }

    public function createMoneyAutopilotRule(User $user, array $data): object
    {
        foreach (['name', 'rule_type', 'trigger_config', 'action_config'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidArgumentException($field.' is required.');
            }
        }

        $allowedTypes = ['income_split', 'scheduled_save', 'balance_floor', 'bill_buffer', 'goal_topup'];
        if (! in_array($data['rule_type'], $allowedTypes, true)) {
            throw new InvalidArgumentException('Unsupported Money Autopilot rule type.');
        }

        $id = DB::table('money_autopilot_rules')->insertGetId([
            'user_id' => $user->id,
            'name' => trim((string) $data['name']),
            'rule_type' => $data['rule_type'],
            'status' => 'active',
            'trigger_config' => json_encode($data['trigger_config']),
            'action_config' => json_encode($data['action_config']),
            'max_amount_minor' => isset($data['max_amount_minor']) ? max(0, (int) $data['max_amount_minor']) : null,
            'currency' => $data['currency'] ?? 'UGX',
            'consented_at' => now(),
            'next_evaluation_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->decode(DB::table('money_autopilot_rules')->find($id), ['trigger_config', 'action_config']);
    }

    public function setMoneyAutopilotRuleStatus(User $user, int $id, string $status): object
    {
        if (! in_array($status, ['active', 'paused', 'retired'], true)) {
            throw new InvalidArgumentException('Unsupported rule status.');
        }

        $rule = DB::table('money_autopilot_rules')->where('user_id', $user->id)->find($id);
        if (! $rule) {
            throw new InvalidArgumentException('Money Autopilot rule not found.');
        }

        DB::table('money_autopilot_rules')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);

        return $this->decode(DB::table('money_autopilot_rules')->find($id), ['trigger_config', 'action_config']);
    }

    public function evaluateMoneyAutopilotRules(?int $userId = null): array
    {
        $query = DB::table('money_autopilot_rules')->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('next_evaluation_at')->orWhere('next_evaluation_at', '<=', now());
            });
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $evaluated = 0;
        foreach ($query->limit(500)->get() as $rule) {
            $action = json_decode($rule->action_config, true) ?: [];
            $amount = isset($action['amount_minor']) ? max(0, (int) $action['amount_minor']) : null;
            if ($rule->max_amount_minor !== null && $amount !== null) {
                $amount = min($amount, (int) $rule->max_amount_minor);
            }

            DB::table('money_autopilot_executions')->insert([
                'rule_id' => $rule->id,
                'user_id' => $rule->user_id,
                'status' => 'awaiting_provider_or_trigger_confirmation',
                'action_type' => $action['type'] ?? 'review',
                'amount_minor' => $amount,
                'currency' => $rule->currency,
                'evidence' => json_encode(['rule_type' => $rule->rule_type, 'evaluated_under_user_consent' => (bool) $rule->consented_at]),
                'evaluated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('money_autopilot_rules')->where('id', $rule->id)->update([
                'last_evaluated_at' => now(),
                'next_evaluation_at' => now()->addDay(),
                'updated_at' => now(),
            ]);
            $evaluated++;
        }

        return ['evaluated' => $evaluated];
    }

    public function investmentWorkspace(User $user): array
    {
        $profile = DB::table('investment_suitability_profiles')->where('user_id', $user->id)->first();
        $products = DB::table('investment_products')->where('status', 'active')->orderBy('risk_level')->get()->map(fn ($row) => $this->decode($row, ['suitability_requirements', 'disclosures']));
        $orders = DB::table('investment_orders as o')
            ->join('investment_products as p', 'p.id', '=', 'o.investment_product_id')
            ->where('o.user_id', $user->id)
            ->select('o.*', 'p.name as product_name', 'p.provider_name')
            ->orderByDesc('o.id')->get()
            ->map(fn ($row) => $this->decode($row, ['suitability_snapshot', 'disclosure_snapshot']));

        return [
            'suitability' => $profile ? $this->decode($profile, ['answers']) : null,
            'products' => $products,
            'orders' => $orders,
            'settlement_status' => config('opfin.capabilities.investments.status', 'PLANNED'),
        ];
    }

    public function saveSuitability(User $user, array $data): object
    {
        foreach (['risk_tolerance', 'investment_horizon', 'liquidity_need', 'experience_level'] as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException($field.' is required.');
            }
        }

        $allowedRisk = ['low', 'moderate', 'high'];
        if (! in_array($data['risk_tolerance'], $allowedRisk, true)) {
            throw new InvalidArgumentException('Unsupported risk tolerance.');
        }

        DB::table('investment_suitability_profiles')->updateOrInsert(['user_id' => $user->id], [
            'risk_tolerance' => $data['risk_tolerance'],
            'investment_horizon' => $data['investment_horizon'],
            'liquidity_need' => $data['liquidity_need'],
            'experience_level' => $data['experience_level'],
            'status' => 'assessed',
            'answers' => json_encode($data['answers'] ?? []),
            'assessed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->decode(DB::table('investment_suitability_profiles')->where('user_id', $user->id)->first(), ['answers']);
    }

    public function createInvestmentOrder(User $user, int $productId, array $data): object
    {
        $product = DB::table('investment_products')->where('status', 'active')->find($productId);
        if (! $product) {
            throw new InvalidArgumentException('Investment product is not available.');
        }
        $profile = DB::table('investment_suitability_profiles')->where('user_id', $user->id)->first();
        if (! $profile) {
            throw new InvalidArgumentException('Complete suitability assessment before investing.');
        }
        $amount = max(0, (int) ($data['amount_minor'] ?? 0));
        if ($amount < (int) $product->minimum_investment_minor) {
            throw new InvalidArgumentException('Investment amount is below the product minimum.');
        }
        if (empty($data['idempotency_key']) || empty($data['disclosure_acknowledged'])) {
            throw new InvalidArgumentException('Idempotency key and disclosure acknowledgement are required.');
        }

        $riskRank = ['low' => 1, 'moderate' => 2, 'high' => 3];
        if (($riskRank[$product->risk_level] ?? 99) > ($riskRank[$profile->risk_tolerance] ?? 0)) {
            throw new InvalidArgumentException('Product risk exceeds the current suitability profile.');
        }

        $existing = DB::table('investment_orders')->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            return $this->decode($existing, ['suitability_snapshot', 'disclosure_snapshot']);
        }

        $id = DB::table('investment_orders')->insertGetId([
            'user_id' => $user->id,
            'investment_product_id' => $product->id,
            'idempotency_key' => $data['idempotency_key'],
            'amount_minor' => $amount,
            'currency' => $product->currency,
            'status' => 'pending_provider',
            'suitability_snapshot' => json_encode($profile),
            'disclosure_snapshot' => $product->disclosures,
            'disclosure_acknowledged_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->decode(DB::table('investment_orders')->find($id), ['suitability_snapshot', 'disclosure_snapshot']);
    }

    public function createInvestmentProduct(array $data, string $actor): object
    {
        foreach (['product_code', 'name', 'provider_name', 'product_type', 'risk_level'] as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException($field.' is required.');
            }
        }

        $id = DB::table('investment_products')->insertGetId([
            'product_code' => strtoupper(trim($data['product_code'])),
            'name' => trim($data['name']),
            'provider_name' => trim($data['provider_name']),
            'provider_reference' => $data['provider_reference'] ?? null,
            'product_type' => $data['product_type'],
            'risk_level' => $data['risk_level'],
            'minimum_investment_minor' => max(0, (int) ($data['minimum_investment_minor'] ?? 0)),
            'currency' => $data['currency'] ?? 'UGX',
            'status' => 'draft',
            'suitability_requirements' => json_encode($data['suitability_requirements'] ?? []),
            'disclosures' => json_encode($data['disclosures'] ?? []),
            'created_by' => $actor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->decode(DB::table('investment_products')->find($id), ['suitability_requirements', 'disclosures']);
    }

    public function approveInvestmentProduct(int $id, string $actor): object
    {
        return DB::transaction(function () use ($id, $actor) {
            $product = DB::table('investment_products')->lockForUpdate()->find($id);
            if (! $product) {
                throw new InvalidArgumentException('Investment product not found.');
            }
            if ($product->created_by === $actor) {
                throw new InvalidArgumentException('Investment product approval requires maker-checker separation.');
            }
            DB::table('investment_products')->where('id', $id)->update([
                'status' => 'active',
                'approved_by' => $actor,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->decode(DB::table('investment_products')->find($id), ['suitability_requirements', 'disclosures']);
        });
    }

    public function employerDashboard(User $user): array
    {
        $membership = DB::table('employer_memberships')->where('user_id', $user->id)->where('employment_status', 'active')->first();
        if (! $membership) {
            return ['employer' => null, 'membership' => null, 'programs' => [], 'employees' => []];
        }
        $employer = DB::table('employers')->find($membership->employer_id);
        $programs = DB::table('employer_benefit_programs')->where('employer_id', $membership->employer_id)->whereIn('status', ['active', 'pilot'])->get()->map(fn ($row) => $this->decode($row, ['eligibility_rules', 'configuration']));
        $employees = in_array($membership->membership_role, ['admin', 'hr'], true)
            ? DB::table('employer_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.employer_id', $membership->employer_id)->select('m.id', 'm.membership_role', 'm.employee_reference', 'm.employment_status', 'm.employment_type', 'm.verified_monthly_income_minor', 'm.verified_at', 'u.name')->orderBy('u.name')->get()
            : collect();

        return [
            'employer' => $employer,
            'membership' => $membership,
            'programs' => $programs,
            'employees' => $employees,
        ];
    }

    public function createEmployerProgram(User $user, array $data): object
    {
        $membership = DB::table('employer_memberships')->where('user_id', $user->id)->whereIn('membership_role', ['admin', 'hr'])->where('employment_status', 'active')->first();
        if (! $membership) {
            throw new InvalidArgumentException('Employer admin membership is required.');
        }
        foreach (['name', 'benefit_type'] as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException($field.' is required.');
            }
        }

        $id = DB::table('employer_benefit_programs')->insertGetId([
            'employer_id' => $membership->employer_id,
            'name' => $data['name'],
            'benefit_type' => $data['benefit_type'],
            'status' => 'draft',
            'eligibility_rules' => json_encode($data['eligibility_rules'] ?? []),
            'configuration' => json_encode($data['configuration'] ?? []),
            'created_by' => 'USER:'.$user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->decode(DB::table('employer_benefit_programs')->find($id), ['eligibility_rules', 'configuration']);
    }

    private function decode(object $row, array $jsonFields): object
    {
        foreach ($jsonFields as $field) {
            if (property_exists($row, $field) && is_string($row->{$field})) {
                $row->{$field} = json_decode($row->{$field}, true) ?: [];
            }
        }

        return $row;
    }
}
