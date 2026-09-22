<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\InclusiveImpactService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class InclusiveImpactController extends Controller
{
    public function __construct(
        private readonly InclusiveImpactService $service,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function financialHealth(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Financial-health check-ins loaded.',
            $this->service->financialHealth($request->user()),
        );
    }

    public function recordFinancialHealth(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
            'measurement_stage' => ['nullable', Rule::in(InclusiveImpactService::MEASUREMENT_STAGES)],
            'income_stability' => ['nullable', Rule::in(['stable', 'variable', 'seasonal', 'none', 'prefer_not_to_say'])],
            'essential_expense_coverage_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'emergency_savings_minor' => ['nullable', 'integer', 'min:0'],
            'monthly_income_minor' => ['nullable', 'integer', 'min:0'],
            'total_debt_minor' => ['nullable', 'integer', 'min:0'],
            'scheduled_debt_service_minor' => ['nullable', 'integer', 'min:0'],
            'repayment_stress' => ['nullable', 'boolean'],
            'insurance_protection' => ['nullable', 'boolean'],
            'shock_in_last_90_days' => ['nullable', 'boolean'],
            'recovered_from_shock' => ['nullable', 'boolean'],
            'savings_direction' => ['nullable', Rule::in(['increasing', 'stable', 'decreasing', 'none', 'prefer_not_to_say'])],
            'observed_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        return $this->audited(
            'inclusive_impact.financial_health.recorded',
            $request,
            fn () => $this->service->recordFinancialHealth($request->user(), $validated),
            ['programme_id' => $validated['programme_id'] ?? null],
            201,
        );
    }

    public function recordLivelihood(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
            'measurement_stage' => ['required', Rule::in(InclusiveImpactService::MEASUREMENT_STAGES)],
            'employment_status' => ['nullable', Rule::in(['salaried', 'self_employed', 'informal_worker', 'student', 'not_currently_employed', 'other', 'prefer_not_to_say'])],
            'business_activity' => ['nullable', 'string', 'max:160'],
            'monthly_income_minor' => ['nullable', 'integer', 'min:0'],
            'monthly_revenue_minor' => ['nullable', 'integer', 'min:0'],
            'enterprise_operating' => ['nullable', 'boolean'],
            'productive_assets_minor' => ['nullable', 'integer', 'min:0'],
            'workers_total' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'jobs_created' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'jobs_retained' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'income_reliability' => ['nullable', Rule::in(['reliable', 'mostly_reliable', 'irregular', 'none', 'prefer_not_to_say'])],
            'weekly_work_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
            'work_satisfaction' => ['nullable', 'integer', 'min:1', 'max:5'],
            'work_dignity' => ['nullable', 'integer', 'min:1', 'max:5'],
            'sense_of_purpose' => ['nullable', 'integer', 'min:1', 'max:5'],
            'observed_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        return $this->audited(
            'inclusive_impact.livelihood.recorded',
            $request,
            fn () => $this->service->recordLivelihood($request->user(), $validated),
            ['programme_id' => $validated['programme_id'] ?? null],
            201,
        );
    }

    public function recordEmpowerment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['required', 'integer', 'exists:inclusive_finance_programmes,id'],
            'measurement_stage' => ['required', Rule::in(InclusiveImpactService::MEASUREMENT_STAGES)],
            'controls_income' => ['nullable', 'boolean'],
            'controls_savings' => ['nullable', 'boolean'],
            'financial_decision_role' => ['nullable', Rule::in(['self', 'joint', 'someone_else', 'varies', 'prefer_not_to_say'])],
            'controls_productive_assets' => ['nullable', 'boolean'],
            'can_use_financial_services_independently' => ['nullable', 'boolean'],
            'has_personal_device_or_account_access' => ['nullable', 'boolean'],
            'financial_confidence' => ['nullable', 'integer', 'min:1', 'max:5'],
            'group_participation' => ['nullable', 'boolean'],
            'observed_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        return $this->audited(
            'inclusive_impact.empowerment.recorded',
            $request,
            fn () => $this->service->recordEmpowerment($request->user(), $validated),
            ['programme_id' => $validated['programme_id']],
            201,
        );
    }

    public function communityFinance(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Community-finance evidence loaded.',
            $this->service->communityFinance($request->user()),
        );
    }

    public function recordCommunityFinance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
            'group_reference' => ['nullable', 'string', 'max:160'],
            'membership_status' => ['nullable', Rule::in(['active', 'inactive', 'exited', 'prefer_not_to_say'])],
            'membership_since' => ['nullable', 'date', 'before_or_equal:today'],
            'savings_balance_minor' => ['nullable', 'integer', 'min:0'],
            'contribution_streak' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'completed_group_loans' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'repayment_history' => ['nullable', Rule::in(['no_history', 'on_time', 'mixed', 'late', 'prefer_not_to_say'])],
            'leadership_role' => ['nullable', 'string', 'max:120'],
            'guarantee_capacity_minor' => ['nullable', 'integer', 'min:0'],
            'channel' => ['nullable', Rule::in(['app', 'web', 'ussd', 'whatsapp', 'assisted'])],
            'observed_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        return $this->audited(
            'inclusive_impact.community_finance.recorded',
            $request,
            fn () => $this->service->recordCommunityFinance($request->user(), $validated),
            ['programme_id' => $validated['programme_id'] ?? null],
            201,
        );
    }

    public function programmeFramework(Request $request, int $programme): JsonResponse
    {
        return ApiResponse::success(
            'Programme impact framework loaded.',
            $this->service->programmeFramework($programme),
        );
    }

    public function indicatorRegister(): JsonResponse
    {
        return ApiResponse::success('Impact indicator register loaded.', $this->service->indicatorRegister());
    }

    public function createIndicator(Request $request): JsonResponse
    {
        $validated = $this->indicatorValidation($request);

        return $this->audited(
            'inclusive_impact.indicator.created',
            $request,
            fn () => $this->service->createIndicator($validated, $request->user()),
            ['indicator_code' => strtoupper($validated['code'])],
            201,
        );
    }

    public function updateIndicator(Request $request, int $indicator): JsonResponse
    {
        $validated = $this->indicatorValidation($request, true);

        return $this->audited(
            'inclusive_impact.indicator.updated',
            $request,
            fn () => $this->service->updateIndicator($indicator, $validated, $request->user()),
            ['indicator_id' => $indicator],
        );
    }

    public function adminProgrammeFramework(int $programme): JsonResponse
    {
        return ApiResponse::success(
            'Programme impact framework loaded.',
            $this->service->programmeFramework($programme),
        );
    }

    public function updateProgrammeTheory(Request $request, int $programme): JsonResponse
    {
        $validated = $request->validate([
            'problem_statement' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'inputs' => ['sometimes', 'array'],
            'interventions' => ['sometimes', 'array'],
            'outputs' => ['sometimes', 'array'],
            'outcomes' => ['sometimes', 'array'],
            'impact' => ['sometimes', 'array'],
            'assumptions' => ['sometimes', 'array'],
            'risks' => ['sometimes', 'array'],
            'evidence_sources' => ['sometimes', 'array'],
            'version' => ['sometimes', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'retired'])],
        ]);

        return $this->audited(
            'inclusive_impact.theory_of_change.updated',
            $request,
            fn () => $this->service->upsertProgrammeTheory($programme, $validated, $request->user()),
            ['programme_id' => $programme],
        );
    }

    public function assignIndicator(Request $request, int $programme): JsonResponse
    {
        $validated = $request->validate([
            'indicator_definition_id' => ['required', 'integer', 'exists:impact_indicator_definitions,id'],
            'target_numeric' => ['nullable', 'numeric'],
            'target_text' => ['nullable', 'string', 'max:500'],
            'reporting_frequency' => ['nullable', 'string', 'max:80'],
            'baseline_required' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        return $this->audited(
            'inclusive_impact.programme_indicator.assigned',
            $request,
            fn () => $this->service->assignIndicator($programme, $validated, $request->user()),
            ['programme_id' => $programme, 'indicator_definition_id' => $validated['indicator_definition_id']],
            201,
        );
    }

    public function recordObservation(Request $request, int $programme): JsonResponse
    {
        $validated = $request->validate([
            'indicator_definition_id' => ['required', 'integer', 'exists:impact_indicator_definitions,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'measurement_stage' => ['required', Rule::in(InclusiveImpactService::MEASUREMENT_STAGES)],
            'numeric_value' => ['nullable', 'numeric'],
            'text_value' => ['nullable', 'string', 'max:4000'],
            'boolean_value' => ['nullable', 'boolean'],
            'unit' => ['nullable', 'string', 'max:80'],
            'source_type' => ['required', Rule::in(['system', 'customer', 'partner', 'survey', 'provider', 'administrative'])],
            'source_reference' => ['nullable', 'string', 'max:200'],
            'provenance' => ['nullable', 'array'],
            'verification_status' => ['nullable', Rule::in(['self_reported', 'verified', 'rejected', 'not_applicable'])],
            'observed_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        return $this->audited(
            'inclusive_impact.observation.recorded',
            $request,
            fn () => $this->service->recordObservation($programme, $validated, $request->user()),
            [
                'programme_id' => $programme,
                'indicator_definition_id' => $validated['indicator_definition_id'],
                'subject_user_id' => $validated['user_id'] ?? null,
            ],
            201,
        );
    }

    public function programmeOutcomes(int $programme): JsonResponse
    {
        return ApiResponse::success(
            'Programme outcome evidence loaded.',
            $this->service->programmeOutcomes($programme),
        );
    }

    public function grantPartnerAccess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['required', 'integer', 'exists:inclusive_finance_programmes,id'],
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'access_level' => ['required', Rule::in(InclusiveImpactService::PARTNER_ACCESS_LEVELS)],
            'status' => ['nullable', Rule::in(['active', 'revoked'])],
        ]);

        return $this->audited(
            'inclusive_impact.partner_access.updated',
            $request,
            fn () => $this->service->grantPartnerAccess($validated, $request->user()),
            [
                'programme_id' => $validated['programme_id'],
                'partner_id' => $validated['partner_id'],
                'subject_user_id' => $validated['user_id'],
                'access_level' => $validated['access_level'],
            ],
            201,
        );
    }

    public function partnerProgrammes(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Partner programme register loaded.',
            $this->service->partnerProgrammes($request->user()),
        );
    }

    public function partnerImpact(Request $request, int $programme): JsonResponse
    {
        return $this->audited(
            'inclusive_impact.partner_impact.viewed',
            $request,
            fn () => $this->service->partnerImpact($request->user(), $programme),
            ['programme_id' => $programme],
        );
    }

    private function indicatorValidation(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'code' => [$required, 'string', 'max:100'],
            'name' => [$required, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:4000'],
            'outcome_domain' => [$required, Rule::in(InclusiveImpactService::OUTCOME_DOMAINS)],
            'value_type' => [$required, Rule::in(InclusiveImpactService::VALUE_TYPES)],
            'unit' => ['nullable', 'string', 'max:80'],
            'calculation_methodology' => ['nullable', 'string', 'max:4000'],
            'collection_method' => ['nullable', 'string', 'max:120'],
            'source_type' => ['nullable', 'string', 'max:120'],
            'verification_required' => ['nullable', 'boolean'],
            'frequency' => ['nullable', 'string', 'max:80'],
            'baseline_required' => ['nullable', 'boolean'],
            'privacy_classification' => ['nullable', Rule::in(['programme_measurement', 'aggregate_only', 'operational'])],
            'framework' => ['nullable', 'string', 'max:120'],
            'framework_version' => ['nullable', 'string', 'max:40'],
            'disaggregation_dimensions' => ['nullable', 'array'],
            'disaggregation_dimensions.*' => ['string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'retired'])],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);
    }

    private function audited(
        string $event,
        Request $request,
        callable $callback,
        array $metadata = [],
        int $status = 200,
    ): JsonResponse {
        try {
            $result = $callback();
            $this->auditLogger->record($event, $request->user(), null, $metadata, $request);

            return ApiResponse::success('Inclusive-impact operation completed.', $result, $status);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }
}
