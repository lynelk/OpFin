<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class FinancialHealthEnrichmentService
{
    public function __construct(
        private readonly FinancialWellbeingService $wellbeing,
    ) {}

    public function preview(User $user): array
    {
        $asOf = now();
        $compass = $this->wellbeing->compass($user, 'UGX');
        $position = $compass['position'] ?? [];

        $monthStart = Carbon::now()->subDays(30)->startOfDay();
        $entries = DB::table('financial_entries')
            ->where('user_id', $user->id)
            ->where('currency', 'UGX')
            ->where('occurred_at', '>=', $monthStart)
            ->get();

        $income30 = (int) $entries->where('direction', 'income')->sum('amount_minor');
        $expense30 = (int) $entries->where('direction', 'expense')
            ->reject(fn ($row) => in_array($row->category, ['Savings', 'Transfers'], true))
            ->sum('amount_minor');

        $available = $position['available_money_minor'] ?? null;
        $averageDailyExpense = $expense30 > 0 ? $expense30 / 30 : null;
        $coverageDays = ($available !== null && $averageDailyExpense && $averageDailyExpense > 0)
            ? (int) min(3650, floor($available / $averageDailyExpense))
            : null;

        $debtService30 = Schema::hasTable('credit_repayment_schedule_items')
            ? (int) DB::table('credit_repayment_schedule_items as item')
                ->join('loans', 'loans.id', '=', 'item.loan_id')
                ->where('loans.user_id', $user->id)
                ->where('item.total_outstanding_minor', '>', 0)
                ->whereBetween('item.due_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->sum('item.total_outstanding_minor')
            : 0;

        $insuranceProtection = Schema::hasTable('protection_policies')
            ? DB::table('protection_policies')
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'issued'])
                ->exists()
            : null;

        $incomeStability = $this->incomeStability($user);
        $savingsDirection = $this->savingsDirection($user);
        $repaymentStress = $income30 > 0 && $debtService30 > (int) round($income30 * 0.5);

        [$status, $reasons] = $this->classify([
            'income_stability' => $incomeStability,
            'essential_expense_coverage_days' => $coverageDays,
            'emergency_savings_minor' => (int) ($position['current_savings_minor'] ?? 0),
            'monthly_income_minor' => $income30,
            'total_debt_minor' => (int) ($position['debt_obligations_minor'] ?? 0),
            'scheduled_debt_service_minor' => $debtService30,
            'repayment_stress' => $repaymentStress,
            'insurance_protection' => $insuranceProtection,
            'savings_direction' => $savingsDirection,
        ]);

        return [
            'inputs' => [
                'income_stability' => $incomeStability,
                'essential_expense_coverage_days' => $coverageDays,
                'emergency_savings_minor' => (int) ($position['current_savings_minor'] ?? 0),
                'monthly_income_minor' => $income30,
                'total_debt_minor' => (int) ($position['debt_obligations_minor'] ?? 0),
                'scheduled_debt_service_minor' => $debtService30,
                'repayment_stress' => $repaymentStress,
                'insurance_protection' => $insuranceProtection,
                'savings_direction' => $savingsDirection,
            ],
            'status' => $status,
            'status_reasons' => $reasons,
            'provenance' => [
                'cash_flow_window_days' => 30,
                'available_money_confidence' => $position['available_money_confidence'] ?? null,
                'source_tables' => [
                    'financial_accounts',
                    'financial_entries',
                    'credit_repayment_schedule_items',
                    'savings_goals',
                    'protection_policies_when_available',
                ],
            ],
            'classification_version' => 'financial-health-enrichment-v1',
            'credit_decision_eligible' => false,
            'notice' => 'System enrichment reduces repeated questions where OpFin already has recorded financial-life evidence. Missing external data remains missing and is never invented.',
        ];
    }

    public function record(User $user, ?int $programmeId = null): array
    {
        $preview = $this->preview($user);
        $enrolmentId = null;

        if ($programmeId) {
            $profile = DB::table('inclusive_finance_profiles')->where('user_id', $user->id)->first();
            if (! $profile || ! $profile->programme_measurement_consent) {
                throw new InvalidArgumentException('Programme measurement consent is required before an enriched snapshot can be linked to a programme.');
            }

            $enrolment = DB::table('inclusive_finance_enrolments')
                ->where('programme_id', $programmeId)
                ->where('user_id', $user->id)
                ->where('status', 'enrolled')
                ->first();
            if (! $enrolment) {
                throw new InvalidArgumentException('Active programme enrolment is required before an enriched snapshot can be linked to a programme.');
            }
            $enrolmentId = $enrolment->id;
        }

        $inputs = $preview['inputs'];
        $id = DB::table('financial_health_snapshots')->insertGetId([
            'user_id' => $user->id,
            'programme_id' => $programmeId,
            'enrolment_id' => $enrolmentId,
            'measurement_stage' => $programmeId ? 'check_in' : 'check_in',
            'income_stability' => $inputs['income_stability'],
            'essential_expense_coverage_days' => $inputs['essential_expense_coverage_days'],
            'emergency_savings_minor' => $inputs['emergency_savings_minor'],
            'monthly_income_minor' => $inputs['monthly_income_minor'],
            'total_debt_minor' => $inputs['total_debt_minor'],
            'scheduled_debt_service_minor' => $inputs['scheduled_debt_service_minor'],
            'repayment_stress' => $inputs['repayment_stress'],
            'insurance_protection' => $inputs['insurance_protection'],
            'shock_in_last_90_days' => null,
            'recovered_from_shock' => null,
            'savings_direction' => $inputs['savings_direction'],
            'financial_health_status' => $preview['status'],
            'status_reasons' => json_encode($preview['status_reasons']),
            'provenance' => json_encode($preview['provenance']),
            'credit_decision_eligible' => false,
            'observed_at' => now(),
            'source_type' => 'system_enriched',
            'automated_inputs' => json_encode($inputs),
            'enrichment_version' => $preview['classification_version'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'snapshot_id' => $id,
            'programme_id' => $programmeId,
            'status' => $preview['status'],
            'status_reasons' => $preview['status_reasons'],
            'source_type' => 'system_enriched',
            'credit_decision_eligible' => false,
        ];
    }

    private function incomeStability(User $user): string
    {
        $monthly = [];
        for ($offset = 0; $offset < 3; $offset++) {
            $start = now()->subMonths($offset)->startOfMonth();
            $end = now()->subMonths($offset)->endOfMonth();
            $monthly[] = (int) DB::table('financial_entries')
                ->where('user_id', $user->id)
                ->where('currency', 'UGX')
                ->where('direction', 'income')
                ->whereBetween('occurred_at', [$start, $end])
                ->sum('amount_minor');
        }

        $nonZero = array_values(array_filter($monthly, fn ($value) => $value > 0));
        if ($nonZero === []) {
            return 'none';
        }
        if (count($nonZero) < 2) {
            return 'variable';
        }

        $average = array_sum($nonZero) / count($nonZero);
        if ($average <= 0) {
            return 'none';
        }
        $maxDeviation = max(array_map(fn ($value) => abs($value - $average) / $average, $nonZero));

        return $maxDeviation <= 0.2 ? 'stable' : 'variable';
    }

    private function savingsDirection(User $user): string
    {
        $current = (int) DB::table('financial_entries')
            ->where('user_id', $user->id)
            ->where('direction', 'expense')
            ->where('category', 'Savings')
            ->whereBetween('occurred_at', [now()->subDays(30), now()])
            ->sum('amount_minor');

        $previous = (int) DB::table('financial_entries')
            ->where('user_id', $user->id)
            ->where('direction', 'expense')
            ->where('category', 'Savings')
            ->whereBetween('occurred_at', [now()->subDays(60), now()->subDays(31)])
            ->sum('amount_minor');

        if ($current === 0 && $previous === 0) {
            return 'none';
        }
        if ($previous === 0) {
            return 'increasing';
        }
        $change = ($current - $previous) / $previous;

        if ($change > 0.1) {
            return 'increasing';
        }
        if ($change < -0.1) {
            return 'decreasing';
        }

        return 'stable';
    }

    private function classify(array $data): array
    {
        $reasons = [];
        $coverage = $data['essential_expense_coverage_days'] ?? null;
        $emergencySavings = (int) ($data['emergency_savings_minor'] ?? 0);
        $monthlyIncome = (int) ($data['monthly_income_minor'] ?? 0);
        $debtService = (int) ($data['scheduled_debt_service_minor'] ?? 0);

        if (($data['repayment_stress'] ?? false) === true) {
            $reasons[] = 'Recorded debt service is high relative to recent recorded income.';
        }
        if (($data['income_stability'] ?? null) === 'none') {
            $reasons[] = 'No recent recorded income was available for enrichment.';
        }
        if ($monthlyIncome > 0 && $debtService > $monthlyIncome) {
            $reasons[] = 'Scheduled debt service exceeds recent recorded monthly income.';
        }

        if ($reasons !== []) {
            return ['struggling', $reasons];
        }

        if (
            $coverage !== null
            && $coverage >= 90
            && $emergencySavings > 0
            && ($data['savings_direction'] ?? null) === 'increasing'
            && ($data['insurance_protection'] ?? false) === true
        ) {
            return ['progressing', [
                'Recorded resources cover at least 90 days of recent essential spending.',
                'Recorded emergency savings are positive.',
                'Recorded savings contributions are increasing.',
                'Active protection is recorded.',
            ]];
        }

        if ($coverage !== null && $coverage >= 30 && $emergencySavings > 0) {
            return ['resilient', [
                'Recorded resources cover at least 30 days of recent essential spending.',
                'Recorded emergency savings are positive.',
            ]];
        }

        return ['stabilising', [
            'No severe system-observed stress trigger was found.',
            'Recorded resilience evidence does not yet meet the transparent resilient/progressing thresholds.',
        ]];
    }
}
