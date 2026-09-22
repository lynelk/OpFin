<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InclusiveImpactService
{
    public const MINIMUM_COHORT_SIZE = 5;

    public const OUTCOME_DOMAINS = [
        'access_inclusion',
        'financial_health_resilience',
        'livelihood_enterprise',
        'dignified_work',
        'agency_empowerment',
        'market_systems',
        'climate_resilience',
    ];

    public const VALUE_TYPES = [
        'integer',
        'decimal',
        'boolean',
        'categorical',
        'currency_minor',
        'text',
    ];

    public const MEASUREMENT_STAGES = [
        'baseline',
        '30_day',
        '90_day',
        '6_month',
        '12_month',
        '24_month',
        'exit',
        'post_programme',
        'check_in',
    ];

    public const PARTNER_ACCESS_LEVELS = [
        'read_only',
        'auditor',
        'mel_officer',
        'programme_admin',
    ];

    public function __construct(
        private readonly InclusiveFinanceService $inclusiveFinance,
    ) {}

    public function indicatorRegister(): array
    {
        $indicators = DB::table('impact_indicator_definitions')
            ->orderBy('outcome_domain')
            ->orderBy('code')
            ->get()
            ->map(fn ($indicator) => $this->indicatorPayload($indicator))
            ->values()
            ->all();

        return [
            'indicators' => $indicators,
            'outcome_domains' => self::OUTCOME_DOMAINS,
            'value_types' => self::VALUE_TYPES,
            'measurement_stages' => self::MEASUREMENT_STAGES,
            'governance' => [
                'credit_decision_eligible' => false,
                'principle' => 'Impact indicators are programme measurement artefacts. They do not become underwriting inputs by being configured here.',
            ],
        ];
    }

    public function createIndicator(array $data, User $actor): array
    {
        $code = strtoupper(trim($data['code']));
        if (DB::table('impact_indicator_definitions')->whereRaw('UPPER(code) = ?', [$code])->exists()) {
            throw new InvalidArgumentException('An impact indicator with this code already exists.');
        }

        $id = DB::table('impact_indicator_definitions')->insertGetId([
            'code' => $code,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'outcome_domain' => $data['outcome_domain'],
            'value_type' => $data['value_type'],
            'unit' => $data['unit'] ?? null,
            'calculation_methodology' => $data['calculation_methodology'] ?? null,
            'collection_method' => $data['collection_method'] ?? 'system_or_authorised_capture',
            'source_type' => $data['source_type'] ?? 'mixed',
            'verification_required' => (bool) ($data['verification_required'] ?? false),
            'frequency' => $data['frequency'] ?? null,
            'baseline_required' => (bool) ($data['baseline_required'] ?? false),
            'privacy_classification' => $data['privacy_classification'] ?? 'programme_measurement',
            'credit_decision_eligible' => false,
            'framework' => $data['framework'] ?? 'opfin_impact',
            'framework_version' => $data['framework_version'] ?? '1.0',
            'disaggregation_dimensions' => isset($data['disaggregation_dimensions'])
                ? json_encode(array_values($data['disaggregation_dimensions']))
                : null,
            'status' => $data['status'] ?? 'active',
            'valid_from' => $data['valid_from'] ?? null,
            'valid_to' => $data['valid_to'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->indicatorPayload(
            DB::table('impact_indicator_definitions')->where('id', $id)->first()
        );
    }

    public function updateIndicator(int $indicatorId, array $data, User $actor): array
    {
        $indicator = DB::table('impact_indicator_definitions')->where('id', $indicatorId)->first();
        if (! $indicator) {
            throw new InvalidArgumentException('Impact indicator not found.');
        }

        if (isset($data['code'])) {
            $code = strtoupper(trim($data['code']));
            $duplicate = DB::table('impact_indicator_definitions')
                ->where('id', '!=', $indicatorId)
                ->whereRaw('UPPER(code) = ?', [$code])
                ->exists();
            if ($duplicate) {
                throw new InvalidArgumentException('An impact indicator with this code already exists.');
            }
            $data['code'] = $code;
        }

        $allowed = [
            'code',
            'name',
            'description',
            'outcome_domain',
            'value_type',
            'unit',
            'calculation_methodology',
            'collection_method',
            'source_type',
            'verification_required',
            'frequency',
            'baseline_required',
            'privacy_classification',
            'framework',
            'framework_version',
            'status',
            'valid_from',
            'valid_to',
        ];

        $update = collect($data)->only($allowed)->all();
        if (array_key_exists('disaggregation_dimensions', $data)) {
            $update['disaggregation_dimensions'] = $data['disaggregation_dimensions'] === null
                ? null
                : json_encode(array_values($data['disaggregation_dimensions']));
        }
        $update['credit_decision_eligible'] = false;
        $update['updated_by'] = $actor->id;
        $update['updated_at'] = now();

        DB::table('impact_indicator_definitions')->where('id', $indicatorId)->update($update);

        return $this->indicatorPayload(
            DB::table('impact_indicator_definitions')->where('id', $indicatorId)->first()
        );
    }

    public function programmeFramework(int $programmeId): array
    {
        $programme = DB::table('inclusive_finance_programmes')->where('id', $programmeId)->first();
        if (! $programme) {
            throw new InvalidArgumentException('Inclusive-finance programme not found.');
        }

        $theory = DB::table('programme_theories_of_change')->where('programme_id', $programmeId)->first();
        $indicators = DB::table('inclusive_finance_programme_indicators as assignment')
            ->join('impact_indicator_definitions as indicator', 'indicator.id', '=', 'assignment.indicator_definition_id')
            ->where('assignment.programme_id', $programmeId)
            ->orderBy('indicator.outcome_domain')
            ->orderBy('indicator.code')
            ->select([
                'assignment.id as assignment_id',
                'assignment.target_numeric',
                'assignment.target_text',
                'assignment.reporting_frequency',
                'assignment.baseline_required as assignment_baseline_required',
                'assignment.metadata as assignment_metadata',
                'indicator.*',
            ])
            ->get()
            ->map(function ($row) {
                $payload = $this->indicatorPayload($row);
                $payload['assignment'] = [
                    'id' => (int) $row->assignment_id,
                    'target_numeric' => $row->target_numeric === null ? null : (float) $row->target_numeric,
                    'target_text' => $row->target_text,
                    'reporting_frequency' => $row->reporting_frequency,
                    'baseline_required' => $row->assignment_baseline_required === null
                        ? (bool) $row->baseline_required
                        : (bool) $row->assignment_baseline_required,
                    'metadata' => $this->json($row->assignment_metadata),
                ];

                return $payload;
            })
            ->values()
            ->all();

        return [
            'programme' => [
                'id' => (int) $programme->id,
                'code' => $programme->code,
                'name' => $programme->name,
                'status' => $programme->status,
                'starts_at' => $programme->starts_at,
                'ends_at' => $programme->ends_at,
            ],
            'theory_of_change' => $theory ? [
                'problem_statement' => $theory->problem_statement,
                'inputs' => $this->json($theory->inputs),
                'interventions' => $this->json($theory->interventions),
                'outputs' => $this->json($theory->outputs),
                'outcomes' => $this->json($theory->outcomes),
                'impact' => $this->json($theory->impact),
                'assumptions' => $this->json($theory->assumptions),
                'risks' => $this->json($theory->risks),
                'evidence_sources' => $this->json($theory->evidence_sources),
                'version' => $theory->version,
                'status' => $theory->status,
            ] : null,
            'indicators' => $indicators,
            'measurement_boundary' => 'Programme frameworks and indicators organise evidence. They do not alter OpFin credit eligibility, pricing or limits.',
        ];
    }

    public function upsertProgrammeTheory(int $programmeId, array $data, User $actor): array
    {
        if (! DB::table('inclusive_finance_programmes')->where('id', $programmeId)->exists()) {
            throw new InvalidArgumentException('Inclusive-finance programme not found.');
        }

        $existing = DB::table('programme_theories_of_change')->where('programme_id', $programmeId)->first();
        DB::table('programme_theories_of_change')->updateOrInsert(
            ['programme_id' => $programmeId],
            [
                'problem_statement' => $data['problem_statement'] ?? $existing?->problem_statement,
                'inputs' => array_key_exists('inputs', $data) ? json_encode($data['inputs']) : $existing?->inputs,
                'interventions' => array_key_exists('interventions', $data) ? json_encode($data['interventions']) : $existing?->interventions,
                'outputs' => array_key_exists('outputs', $data) ? json_encode($data['outputs']) : $existing?->outputs,
                'outcomes' => array_key_exists('outcomes', $data) ? json_encode($data['outcomes']) : $existing?->outcomes,
                'impact' => array_key_exists('impact', $data) ? json_encode($data['impact']) : $existing?->impact,
                'assumptions' => array_key_exists('assumptions', $data) ? json_encode($data['assumptions']) : $existing?->assumptions,
                'risks' => array_key_exists('risks', $data) ? json_encode($data['risks']) : $existing?->risks,
                'evidence_sources' => array_key_exists('evidence_sources', $data) ? json_encode($data['evidence_sources']) : $existing?->evidence_sources,
                'version' => $data['version'] ?? $existing?->version ?? '1.0',
                'status' => $data['status'] ?? $existing?->status ?? 'draft',
                'updated_by' => $actor->id,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ],
        );

        return $this->programmeFramework($programmeId);
    }

    public function assignIndicator(int $programmeId, array $data, User $actor): array
    {
        if (! DB::table('inclusive_finance_programmes')->where('id', $programmeId)->exists()) {
            throw new InvalidArgumentException('Inclusive-finance programme not found.');
        }
        if (! DB::table('impact_indicator_definitions')->where('id', $data['indicator_definition_id'])->exists()) {
            throw new InvalidArgumentException('Impact indicator not found.');
        }

        $existing = DB::table('inclusive_finance_programme_indicators')
            ->where('programme_id', $programmeId)
            ->where('indicator_definition_id', $data['indicator_definition_id'])
            ->first();

        DB::table('inclusive_finance_programme_indicators')->updateOrInsert(
            [
                'programme_id' => $programmeId,
                'indicator_definition_id' => $data['indicator_definition_id'],
            ],
            [
                'target_numeric' => $data['target_numeric'] ?? $existing?->target_numeric,
                'target_text' => $data['target_text'] ?? $existing?->target_text,
                'reporting_frequency' => $data['reporting_frequency'] ?? $existing?->reporting_frequency,
                'baseline_required' => array_key_exists('baseline_required', $data)
                    ? (bool) $data['baseline_required']
                    : $existing?->baseline_required,
                'metadata' => array_key_exists('metadata', $data)
                    ? json_encode($data['metadata'])
                    : $existing?->metadata,
                'configured_by' => $actor->id,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ],
        );

        return $this->programmeFramework($programmeId);
    }

    public function financialHealth(User $user): array
    {
        $history = DB::table('financial_health_snapshots')
            ->where('user_id', $user->id)
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(fn ($row) => $this->financialHealthPayload($row))
            ->values()
            ->all();

        return [
            'latest' => $history[0] ?? null,
            'history' => $history,
            'is_credit_score' => false,
            'credit_decision_eligible' => false,
            'classification_version' => 'financial-health-v1',
            'principle' => 'Financial-health check-ins help customers and authorised programmes understand resilience. They do not change credit eligibility, pricing or limits.',
        ];
    }

    public function recordFinancialHealth(User $user, array $data): array
    {
        $enrolment = null;
        if (! empty($data['programme_id'])) {
            $enrolment = $this->assertProgrammeMeasurementParticipation($user, (int) $data['programme_id']);
        }

        [$status, $reasons] = $this->classifyFinancialHealth($data);

        $id = DB::table('financial_health_snapshots')->insertGetId([
            'user_id' => $user->id,
            'programme_id' => $data['programme_id'] ?? null,
            'enrolment_id' => $enrolment?->id,
            'measurement_stage' => $data['measurement_stage'] ?? 'check_in',
            'income_stability' => $data['income_stability'] ?? null,
            'essential_expense_coverage_days' => $data['essential_expense_coverage_days'] ?? null,
            'emergency_savings_minor' => $data['emergency_savings_minor'] ?? null,
            'monthly_income_minor' => $data['monthly_income_minor'] ?? null,
            'total_debt_minor' => $data['total_debt_minor'] ?? null,
            'scheduled_debt_service_minor' => $data['scheduled_debt_service_minor'] ?? null,
            'repayment_stress' => $data['repayment_stress'] ?? null,
            'insurance_protection' => $data['insurance_protection'] ?? null,
            'shock_in_last_90_days' => $data['shock_in_last_90_days'] ?? null,
            'recovered_from_shock' => $data['recovered_from_shock'] ?? null,
            'savings_direction' => $data['savings_direction'] ?? null,
            'financial_health_status' => $status,
            'status_reasons' => json_encode($reasons),
            'provenance' => isset($data['provenance']) ? json_encode($data['provenance']) : json_encode(['source' => 'customer_check_in']),
            'credit_decision_eligible' => false,
            'observed_at' => $data['observed_at'] ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'snapshot' => $this->financialHealthPayload(
                DB::table('financial_health_snapshots')->where('id', $id)->first()
            ),
            'is_credit_score' => false,
            'credit_decision_eligible' => false,
        ];
    }

    public function recordLivelihood(User $user, array $data): array
    {
        $enrolment = null;
        if (! empty($data['programme_id'])) {
            $enrolment = $this->assertProgrammeMeasurementParticipation($user, (int) $data['programme_id']);
        }

        $id = DB::table('livelihood_outcome_snapshots')->insertGetId([
            'user_id' => $user->id,
            'programme_id' => $data['programme_id'] ?? null,
            'enrolment_id' => $enrolment?->id,
            'measurement_stage' => $data['measurement_stage'],
            'employment_status' => $data['employment_status'] ?? null,
            'business_activity' => $data['business_activity'] ?? null,
            'monthly_income_minor' => $data['monthly_income_minor'] ?? null,
            'monthly_revenue_minor' => $data['monthly_revenue_minor'] ?? null,
            'enterprise_operating' => $data['enterprise_operating'] ?? null,
            'productive_assets_minor' => $data['productive_assets_minor'] ?? null,
            'workers_total' => $data['workers_total'] ?? null,
            'jobs_created' => $data['jobs_created'] ?? null,
            'jobs_retained' => $data['jobs_retained'] ?? null,
            'income_reliability' => $data['income_reliability'] ?? null,
            'weekly_work_hours' => $data['weekly_work_hours'] ?? null,
            'work_satisfaction' => $data['work_satisfaction'] ?? null,
            'work_dignity' => $data['work_dignity'] ?? null,
            'sense_of_purpose' => $data['sense_of_purpose'] ?? null,
            'provenance' => isset($data['provenance']) ? json_encode($data['provenance']) : json_encode(['source' => 'customer_check_in']),
            'credit_decision_eligible' => false,
            'observed_at' => $data['observed_at'] ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'recorded' => true,
            'credit_decision_eligible' => false,
        ];
    }

    public function recordEmpowerment(User $user, array $data): array
    {
        $enrolment = $this->assertProgrammeMeasurementParticipation($user, (int) $data['programme_id']);

        $id = DB::table('empowerment_outcome_snapshots')->insertGetId([
            'user_id' => $user->id,
            'programme_id' => $data['programme_id'],
            'enrolment_id' => $enrolment->id,
            'measurement_stage' => $data['measurement_stage'],
            'controls_income' => $data['controls_income'] ?? null,
            'controls_savings' => $data['controls_savings'] ?? null,
            'financial_decision_role' => $data['financial_decision_role'] ?? null,
            'controls_productive_assets' => $data['controls_productive_assets'] ?? null,
            'can_use_financial_services_independently' => $data['can_use_financial_services_independently'] ?? null,
            'has_personal_device_or_account_access' => $data['has_personal_device_or_account_access'] ?? null,
            'financial_confidence' => $data['financial_confidence'] ?? null,
            'group_participation' => $data['group_participation'] ?? null,
            'provenance' => isset($data['provenance']) ? json_encode($data['provenance']) : json_encode(['source' => 'customer_check_in']),
            'credit_decision_eligible' => false,
            'observed_at' => $data['observed_at'] ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'recorded' => true,
            'credit_decision_eligible' => false,
            'measurement_only' => true,
        ];
    }

    public function communityFinance(User $user): array
    {
        return [
            'evidence' => DB::table('community_finance_evidence')
                ->where('user_id', $user->id)
                ->orderByDesc('observed_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'programme_id' => $row->programme_id ? (int) $row->programme_id : null,
                    'financial_space_id' => $row->financial_space_id ? (int) $row->financial_space_id : null,
                    'source_type' => $row->source_type,
                    'group_reference' => $row->group_reference,
                    'membership_status' => $row->membership_status,
                    'membership_since' => $row->membership_since,
                    'savings_balance_minor' => $row->savings_balance_minor === null ? null : (int) $row->savings_balance_minor,
                    'contribution_streak' => $row->contribution_streak === null ? null : (int) $row->contribution_streak,
                    'completed_group_loans' => $row->completed_group_loans === null ? null : (int) $row->completed_group_loans,
                    'repayment_history' => $row->repayment_history,
                    'leadership_role' => $row->leadership_role,
                    'guarantee_capacity_minor' => $row->guarantee_capacity_minor === null ? null : (int) $row->guarantee_capacity_minor,
                    'verification_status' => $row->verification_status,
                    'credit_decision_eligible' => false,
                    'observed_at' => $row->observed_at,
                ])
                ->values()
                ->all(),
            'credit_decision_eligible' => false,
            'governance' => 'Community-finance evidence supports portable reputation and programme learning. Any future underwriting use must enter the separate governed alternative-data pathway with explicit consent, provenance and approved policy.',
        ];
    }

    public function recordCommunityFinance(User $user, array $data): array
    {
        $enrolment = null;
        if (! empty($data['programme_id'])) {
            $enrolment = $this->assertProgrammeMeasurementParticipation($user, (int) $data['programme_id']);
        }

        $id = DB::table('community_finance_evidence')->insertGetId([
            'user_id' => $user->id,
            'financial_space_id' => $data['financial_space_id'] ?? null,
            'programme_id' => $data['programme_id'] ?? null,
            'enrolment_id' => $enrolment?->id,
            'source_type' => 'self_reported',
            'group_reference' => $data['group_reference'] ?? null,
            'provider_name' => null,
            'provider_reference' => null,
            'membership_status' => $data['membership_status'] ?? null,
            'membership_since' => $data['membership_since'] ?? null,
            'savings_balance_minor' => $data['savings_balance_minor'] ?? null,
            'contribution_streak' => $data['contribution_streak'] ?? null,
            'completed_group_loans' => $data['completed_group_loans'] ?? null,
            'repayment_history' => $data['repayment_history'] ?? null,
            'leadership_role' => $data['leadership_role'] ?? null,
            'guarantee_capacity_minor' => $data['guarantee_capacity_minor'] ?? null,
            'verification_status' => 'self_reported',
            'provenance' => json_encode(['source' => 'customer', 'channel' => $data['channel'] ?? 'app']),
            'credit_decision_eligible' => false,
            'observed_at' => $data['observed_at'] ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'recorded' => true,
            'verification_status' => 'self_reported',
            'credit_decision_eligible' => false,
        ];
    }

    public function recordObservation(int $programmeId, array $data, User $actor): array
    {
        $assignment = DB::table('inclusive_finance_programme_indicators')
            ->where('programme_id', $programmeId)
            ->where('indicator_definition_id', $data['indicator_definition_id'])
            ->first();
        if (! $assignment) {
            throw new InvalidArgumentException('The indicator is not assigned to this programme.');
        }

        $enrolment = null;
        if (! empty($data['user_id'])) {
            $subject = User::withoutGlobalScopes()->find($data['user_id']);
            if (! $subject) {
                throw new InvalidArgumentException('Observation subject not found.');
            }
            $enrolment = $this->assertProgrammeMeasurementParticipation($subject, $programmeId);
        }

        if (
            ! array_key_exists('numeric_value', $data)
            && ! array_key_exists('text_value', $data)
            && ! array_key_exists('boolean_value', $data)
        ) {
            throw new InvalidArgumentException('An observation value is required.');
        }

        $id = DB::table('programme_outcome_observations')->insertGetId([
            'programme_id' => $programmeId,
            'enrolment_id' => $enrolment?->id,
            'user_id' => $data['user_id'] ?? null,
            'indicator_definition_id' => $data['indicator_definition_id'],
            'measurement_stage' => $data['measurement_stage'],
            'numeric_value' => $data['numeric_value'] ?? null,
            'text_value' => $data['text_value'] ?? null,
            'boolean_value' => $data['boolean_value'] ?? null,
            'unit' => $data['unit'] ?? null,
            'source_type' => $data['source_type'],
            'source_reference' => $data['source_reference'] ?? null,
            'provenance' => isset($data['provenance']) ? json_encode($data['provenance']) : null,
            'verification_status' => $data['verification_status'] ?? 'self_reported',
            'credit_decision_eligible' => false,
            'observed_at' => $data['observed_at'] ?? now(),
            'collected_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'recorded' => true,
            'credit_decision_eligible' => false,
        ];
    }

    public function programmeOutcomes(int $programmeId): array
    {
        $framework = $this->programmeFramework($programmeId);
        $summaries = [];

        foreach ($framework['indicators'] as $indicator) {
            $rows = DB::table('programme_outcome_observations')
                ->where('programme_id', $programmeId)
                ->where('indicator_definition_id', $indicator['id'])
                ->orderBy('observed_at')
                ->get();

            $participantRows = $rows->filter(fn ($row) => $row->user_id !== null);
            $participantCount = $participantRows->pluck('user_id')->unique()->count();
            $institutionalRows = $rows->filter(fn ($row) => $row->user_id === null);
            $suppressed = $participantCount > 0 && $participantCount < self::MINIMUM_COHORT_SIZE;

            $summary = [
                'indicator' => $indicator,
                'participant_count' => $suppressed ? null : $participantCount,
                'participant_observation_count' => $suppressed ? null : $participantRows->count(),
                'institutional_observation_count' => $institutionalRows->count(),
                'observation_count' => $suppressed ? $institutionalRows->count() : $rows->count(),
                'suppressed' => $suppressed,
                'latest_observed_at' => $suppressed
                    ? $institutionalRows->max('observed_at')
                    : $rows->max('observed_at'),
            ];

            if (! $suppressed && $participantRows->isNotEmpty()) {
                $numeric = $participantRows->whereNotNull('numeric_value')->pluck('numeric_value')->map(fn ($value) => (float) $value);
                if ($numeric->isNotEmpty()) {
                    $summary['average_numeric'] = round($numeric->avg(), 2);
                }

                $booleanRows = $participantRows->whereNotNull('boolean_value');
                if ($booleanRows->isNotEmpty()) {
                    $trueCount = $booleanRows->where('boolean_value', true)->count();
                    $falseCount = $booleanRows->where('boolean_value', false)->count();
                    if (
                        ($trueCount === 0 || $trueCount >= self::MINIMUM_COHORT_SIZE)
                        && ($falseCount === 0 || $falseCount >= self::MINIMUM_COHORT_SIZE)
                    ) {
                        $summary['boolean_counts'] = ['true' => $trueCount, 'false' => $falseCount];
                    } else {
                        $summary['boolean_distribution_suppressed'] = true;
                    }
                }

                $textRows = $participantRows->whereNotNull('text_value');
                if ($textRows->isNotEmpty()) {
                    $distribution = $textRows->groupBy('text_value')->map->count();
                    if ($distribution->every(fn ($count) => $count >= self::MINIMUM_COHORT_SIZE)) {
                        $summary['category_counts'] = $distribution->all();
                    } else {
                        $summary['category_distribution_suppressed'] = true;
                    }
                }
            }

            if ($institutionalRows->isNotEmpty()) {
                $institutionalNumeric = $institutionalRows->whereNotNull('numeric_value')->pluck('numeric_value')->map(fn ($value) => (float) $value);
                if ($institutionalNumeric->isNotEmpty()) {
                    $summary['institutional_latest_numeric'] = (float) $institutionalNumeric->last();
                }
            }

            $summaries[] = $summary;
        }

        $coverageCounts = [
            'financial_health_people' => $this->distinctProgrammePeople('financial_health_snapshots', $programmeId),
            'livelihood_people' => $this->distinctProgrammePeople('livelihood_outcome_snapshots', $programmeId),
            'empowerment_people' => $this->distinctProgrammePeople('empowerment_outcome_snapshots', $programmeId),
            'community_finance_people' => $this->distinctProgrammePeople('community_finance_evidence', $programmeId),
        ];

        $snapshotCoverage = [];
        $snapshotCoverageSuppressed = [];
        foreach ($coverageCounts as $key => $count) {
            $suppressed = $count > 0 && $count < self::MINIMUM_COHORT_SIZE;
            $snapshotCoverage[$key] = $suppressed ? null : $count;
            $snapshotCoverageSuppressed[$key] = $suppressed;
        }

        return [
            'programme' => $framework['programme'],
            'theory_of_change' => $framework['theory_of_change'],
            'indicator_summaries' => $summaries,
            'snapshot_coverage' => $snapshotCoverage,
            'snapshot_coverage_suppressed' => $snapshotCoverageSuppressed,
            'privacy' => [
                'minimum_cohort_size' => self::MINIMUM_COHORT_SIZE,
                'individual_records_exposed' => false,
                'small_participant_indicator_cohorts_suppressed' => true,
                'small_snapshot_coverage_counts_suppressed' => true,
            ],
            'causality_notice' => 'Programme observations describe measured change and coverage. They must not be described as programme-caused unless the evaluation design supports causal attribution.',
        ];
    }

    public function grantPartnerAccess(array $data, User $actor): array
    {
        $programme = DB::table('inclusive_finance_programmes')->where('id', $data['programme_id'])->first();
        if (! $programme) {
            throw new InvalidArgumentException('Inclusive-finance programme not found.');
        }
        if (! $programme->partner_id || (int) $programme->partner_id !== (int) $data['partner_id']) {
            throw new InvalidArgumentException('Partner access can only be granted to the partner explicitly configured on the programme.');
        }

        $user = User::withoutGlobalScopes()->find($data['user_id']);
        if (! $user || $user->role !== User::ROLE_PROGRAMME_PARTNER) {
            throw new InvalidArgumentException('The target user must be provisioned with the programme-partner role first.');
        }

        $existing = DB::table('programme_partner_access')
            ->where('programme_id', $data['programme_id'])
            ->where('user_id', $data['user_id'])
            ->first();

        DB::table('programme_partner_access')->updateOrInsert(
            [
                'programme_id' => $data['programme_id'],
                'user_id' => $data['user_id'],
            ],
            [
                'partner_id' => $data['partner_id'],
                'access_level' => $data['access_level'] ?? 'read_only',
                'can_view_individual_records' => false,
                'status' => $data['status'] ?? 'active',
                'granted_by' => $actor->id,
                'granted_at' => $existing?->granted_at ?? now(),
                'revoked_at' => ($data['status'] ?? 'active') === 'revoked' ? now() : null,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ],
        );

        return [
            'programme_id' => (int) $data['programme_id'],
            'partner_id' => (int) $data['partner_id'],
            'user_id' => (int) $data['user_id'],
            'access_level' => $data['access_level'] ?? 'read_only',
            'status' => $data['status'] ?? 'active',
            'can_view_individual_records' => false,
        ];
    }

    public function partnerProgrammes(User $user): array
    {
        $programmes = DB::table('programme_partner_access as access')
            ->join('inclusive_finance_programmes as programme', 'programme.id', '=', 'access.programme_id')
            ->where('access.user_id', $user->id)
            ->where('access.status', 'active')
            ->select([
                'programme.id',
                'programme.code',
                'programme.name',
                'programme.status',
                'programme.starts_at',
                'programme.ends_at',
                'access.access_level',
                'access.partner_id',
                'access.can_view_individual_records',
            ])
            ->orderBy('programme.name')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'status' => $row->status,
                'starts_at' => $row->starts_at,
                'ends_at' => $row->ends_at,
                'access_level' => $row->access_level,
                'partner_id' => (int) $row->partner_id,
                'can_view_individual_records' => false,
            ])
            ->values()
            ->all();

        return ['programmes' => $programmes];
    }

    public function partnerImpact(User $user, int $programmeId): array
    {
        $this->assertPartnerProgrammeAccess($user, $programmeId);

        return [
            'delivery' => $this->inclusiveFinance->impactSummary($programmeId),
            'outcomes' => $this->programmeOutcomes($programmeId),
            'access_boundary' => [
                'programme_scoped' => true,
                'individual_records_exposed' => false,
            ],
        ];
    }

    private function assertProgrammeMeasurementParticipation(User $user, int $programmeId): object
    {
        $profile = DB::table('inclusive_finance_profiles')->where('user_id', $user->id)->first();
        if (! $profile || ! $profile->programme_measurement_consent) {
            throw new InvalidArgumentException('Programme measurement consent is required before programme outcome data can be recorded.');
        }

        $enrolment = DB::table('inclusive_finance_enrolments')
            ->where('programme_id', $programmeId)
            ->where('user_id', $user->id)
            ->where('status', 'enrolled')
            ->first();

        if (! $enrolment) {
            throw new InvalidArgumentException('An active programme enrolment is required before programme outcome data can be recorded.');
        }

        return $enrolment;
    }

    private function assertPartnerProgrammeAccess(User $user, int $programmeId): object
    {
        $access = DB::table('programme_partner_access')
            ->where('programme_id', $programmeId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $access) {
            throw new InvalidArgumentException('Programme access is not available for this partner account.');
        }

        return $access;
    }

    private function classifyFinancialHealth(array $data): array
    {
        $reasons = [];
        $coverage = $data['essential_expense_coverage_days'] ?? null;
        $emergencySavings = (int) ($data['emergency_savings_minor'] ?? 0);
        $monthlyIncome = (int) ($data['monthly_income_minor'] ?? 0);
        $debtService = (int) ($data['scheduled_debt_service_minor'] ?? 0);
        $stress = (bool) ($data['repayment_stress'] ?? false);
        $incomeStability = $data['income_stability'] ?? null;

        if ($stress) {
            $reasons[] = 'Customer reported repayment stress.';
        }
        if ($incomeStability === 'none') {
            $reasons[] = 'No current income was reported.';
        }
        if ($monthlyIncome > 0 && $debtService > $monthlyIncome) {
            $reasons[] = 'Scheduled debt service exceeds reported monthly income.';
        }

        if ($reasons !== []) {
            return ['struggling', $reasons];
        }

        if (
            $coverage !== null
            && (int) $coverage >= 90
            && $emergencySavings > 0
            && ($data['savings_direction'] ?? null) === 'increasing'
            && (bool) ($data['insurance_protection'] ?? false)
        ) {
            return [
                'progressing',
                [
                    'At least 90 days of essential-expense coverage reported.',
                    'Emergency savings reported.',
                    'Savings reported as increasing.',
                    'Insurance protection reported.',
                ],
            ];
        }

        if ($coverage !== null && (int) $coverage >= 30 && $emergencySavings > 0) {
            return [
                'resilient',
                [
                    'At least 30 days of essential-expense coverage reported.',
                    'Emergency savings reported.',
                    'No repayment-stress trigger recorded in this check-in.',
                ],
            ];
        }

        return [
            'stabilising',
            [
                'No severe repayment-stress trigger was recorded.',
                'The current resilience indicators do not yet meet the transparent resilient/progressing thresholds.',
            ],
        ];
    }

    private function financialHealthPayload(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'programme_id' => $row->programme_id ? (int) $row->programme_id : null,
            'measurement_stage' => $row->measurement_stage,
            'income_stability' => $row->income_stability,
            'essential_expense_coverage_days' => $row->essential_expense_coverage_days === null ? null : (int) $row->essential_expense_coverage_days,
            'emergency_savings_minor' => $row->emergency_savings_minor === null ? null : (int) $row->emergency_savings_minor,
            'monthly_income_minor' => $row->monthly_income_minor === null ? null : (int) $row->monthly_income_minor,
            'total_debt_minor' => $row->total_debt_minor === null ? null : (int) $row->total_debt_minor,
            'scheduled_debt_service_minor' => $row->scheduled_debt_service_minor === null ? null : (int) $row->scheduled_debt_service_minor,
            'repayment_stress' => $row->repayment_stress === null ? null : (bool) $row->repayment_stress,
            'insurance_protection' => $row->insurance_protection === null ? null : (bool) $row->insurance_protection,
            'shock_in_last_90_days' => $row->shock_in_last_90_days === null ? null : (bool) $row->shock_in_last_90_days,
            'recovered_from_shock' => $row->recovered_from_shock === null ? null : (bool) $row->recovered_from_shock,
            'savings_direction' => $row->savings_direction,
            'financial_health_status' => $row->financial_health_status,
            'status_reasons' => $this->json($row->status_reasons),
            'credit_decision_eligible' => false,
            'observed_at' => $row->observed_at,
        ];
    }

    private function indicatorPayload(object $indicator): array
    {
        return [
            'id' => (int) $indicator->id,
            'code' => $indicator->code,
            'name' => $indicator->name,
            'description' => $indicator->description,
            'outcome_domain' => $indicator->outcome_domain,
            'value_type' => $indicator->value_type,
            'unit' => $indicator->unit,
            'calculation_methodology' => $indicator->calculation_methodology,
            'collection_method' => $indicator->collection_method,
            'source_type' => $indicator->source_type,
            'verification_required' => (bool) $indicator->verification_required,
            'frequency' => $indicator->frequency,
            'baseline_required' => (bool) $indicator->baseline_required,
            'privacy_classification' => $indicator->privacy_classification,
            'credit_decision_eligible' => false,
            'framework' => $indicator->framework,
            'framework_version' => $indicator->framework_version,
            'disaggregation_dimensions' => $this->json($indicator->disaggregation_dimensions),
            'status' => $indicator->status,
            'valid_from' => $indicator->valid_from,
            'valid_to' => $indicator->valid_to,
        ];
    }

    private function distinctProgrammePeople(string $table, int $programmeId): int
    {
        return DB::table($table)
            ->where('programme_id', $programmeId)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');
    }

    private function json(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
