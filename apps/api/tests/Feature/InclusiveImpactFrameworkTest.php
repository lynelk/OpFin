<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InclusiveImpactFrameworkTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_health_check_in_is_transparent_and_never_credit_eligible(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $this->postJson('/api/inclusive-finance/impact/financial-health', [
            'measurement_stage' => 'check_in',
            'income_stability' => 'stable',
            'essential_expense_coverage_days' => 45,
            'emergency_savings_minor' => 15000000,
            'repayment_stress' => false,
            'insurance_protection' => false,
            'savings_direction' => 'stable',
        ])->assertCreated()
            ->assertJsonPath('data.credit_decision_eligible', false)
            ->assertJsonPath('data.is_credit_score', false)
            ->assertJsonPath('data.snapshot.financial_health_status', 'resilient')
            ->assertJsonPath('data.snapshot.credit_decision_eligible', false);

        $this->getJson('/api/inclusive-finance/impact/financial-health')
            ->assertOk()
            ->assertJsonPath('data.is_credit_score', false)
            ->assertJsonPath('data.latest.financial_health_status', 'resilient');

        $this->assertDatabaseHas('financial_health_snapshots', [
            'user_id' => $customer->id,
            'credit_decision_eligible' => false,
        ]);
    }

    public function test_programme_outcomes_require_active_measurement_consent_and_enrolment(): void
    {
        $programmeId = $this->createProgramme();
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $this->postJson('/api/inclusive-finance/impact/financial-health', [
            'programme_id' => $programmeId,
            'measurement_stage' => 'baseline',
            'income_stability' => 'variable',
        ])->assertStatus(422);

        $this->patchJson('/api/inclusive-finance/profile', [
            'programme_measurement_consent' => true,
        ])->assertOk();

        $this->postJson('/api/inclusive-finance/impact/financial-health', [
            'programme_id' => $programmeId,
            'measurement_stage' => 'baseline',
            'income_stability' => 'variable',
        ])->assertStatus(422);

        $this->postJson('/api/inclusive-finance/programmes/'.$programmeId.'/enrol', [])
            ->assertCreated();

        $this->postJson('/api/inclusive-finance/impact/financial-health', [
            'programme_id' => $programmeId,
            'measurement_stage' => 'baseline',
            'income_stability' => 'variable',
            'essential_expense_coverage_days' => 14,
        ])->assertCreated()
            ->assertJsonPath('data.credit_decision_eligible', false);
    }

    public function test_empowerment_measurement_stays_inside_programme_consent_boundary(): void
    {
        $programmeId = $this->createProgramme();
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $this->postJson('/api/inclusive-finance/impact/empowerment', [
            'programme_id' => $programmeId,
            'measurement_stage' => 'baseline',
            'controls_income' => true,
        ])->assertStatus(422);

        $this->patchJson('/api/inclusive-finance/profile', [
            'programme_measurement_consent' => true,
        ])->assertOk();
        $this->postJson('/api/inclusive-finance/programmes/'.$programmeId.'/enrol', [])
            ->assertCreated();

        $this->postJson('/api/inclusive-finance/impact/empowerment', [
            'programme_id' => $programmeId,
            'measurement_stage' => 'baseline',
            'controls_income' => true,
            'controls_savings' => true,
            'financial_decision_role' => 'joint',
            'financial_confidence' => 4,
        ])->assertCreated()
            ->assertJsonPath('data.credit_decision_eligible', false)
            ->assertJsonPath('data.measurement_only', true);
    }

    public function test_community_finance_evidence_does_not_bypass_alternative_data_governance(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $this->postJson('/api/inclusive-finance/impact/community-finance', [
            'group_reference' => 'VSLA-SELF-001',
            'membership_status' => 'active',
            'savings_balance_minor' => 25000000,
            'contribution_streak' => 12,
            'completed_group_loans' => 2,
            'repayment_history' => 'on_time',
        ])->assertCreated()
            ->assertJsonPath('data.verification_status', 'self_reported')
            ->assertJsonPath('data.credit_decision_eligible', false);

        $this->assertDatabaseHas('community_finance_evidence', [
            'user_id' => $customer->id,
            'source_type' => 'self_reported',
            'verification_status' => 'self_reported',
            'credit_decision_eligible' => false,
        ]);
    }

    public function test_indicator_registry_theory_of_change_and_privacy_suppression_are_governed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $programmeId = $this->createProgrammeAsCurrentAdmin();

        $indicatorId = $this->postJson('/api/admin/inclusive-finance/indicators', [
            'code' => 'OF-FH-001',
            'name' => 'Emergency expense coverage',
            'outcome_domain' => 'financial_health_resilience',
            'value_type' => 'integer',
            'unit' => 'days',
            'baseline_required' => true,
            'disaggregation_dimensions' => ['gender', 'age_cohort'],
        ])->assertCreated()
            ->assertJsonPath('data.credit_decision_eligible', false)
            ->json('data.id');

        $this->putJson('/api/admin/inclusive-finance/programmes/'.$programmeId.'/theory-of-change', [
            'problem_statement' => 'Underserved customers lack appropriate financial resilience tools.',
            'inputs' => ['Responsible finance', 'Financial capability'],
            'interventions' => ['Capability guidance'],
            'outputs' => ['Customers complete financial-health check-ins'],
            'outcomes' => ['Improved financial resilience'],
            'impact' => ['More stable livelihoods'],
            'status' => 'active',
        ])->assertOk()
            ->assertJsonPath('data.theory_of_change.status', 'active')
            ->assertJsonPath('data.measurement_boundary', 'Programme frameworks and indicators organise evidence. They do not alter OpFin credit eligibility, pricing or limits.');

        $this->postJson('/api/admin/inclusive-finance/programmes/'.$programmeId.'/indicators', [
            'indicator_definition_id' => $indicatorId,
            'reporting_frequency' => '90_day',
            'baseline_required' => true,
        ])->assertCreated();

        $customer = User::factory()->create();
        Sanctum::actingAs($customer);
        $this->patchJson('/api/inclusive-finance/profile', [
            'programme_measurement_consent' => true,
            'measurement_attributes' => ['gender' => 'female'],
        ])->assertOk();
        $this->postJson('/api/inclusive-finance/programmes/'.$programmeId.'/enrol', [])
            ->assertCreated();

        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/inclusive-finance/programmes/'.$programmeId.'/observations', [
            'indicator_definition_id' => $indicatorId,
            'user_id' => $customer->id,
            'measurement_stage' => 'baseline',
            'numeric_value' => 14,
            'unit' => 'days',
            'source_type' => 'customer',
            'verification_status' => 'self_reported',
        ])->assertCreated()
            ->assertJsonPath('data.credit_decision_eligible', false);

        $this->getJson('/api/admin/inclusive-finance/programmes/'.$programmeId.'/outcomes')
            ->assertOk()
            ->assertJsonPath('data.indicator_summaries.0.participant_count', 1)
            ->assertJsonPath('data.indicator_summaries.0.suppressed', true)
            ->assertJsonPath('data.privacy.minimum_cohort_size', 5)
            ->assertJsonPath('data.privacy.individual_records_exposed', false);
    }

    public function test_institutional_observation_can_be_recorded_without_claiming_individual_participant_outcomes(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $programmeId = $this->createProgrammeAsCurrentAdmin();
        $indicatorId = $this->postJson('/api/admin/inclusive-finance/indicators', [
            'code' => 'OF-MS-001',
            'name' => 'Participating financial institutions',
            'outcome_domain' => 'market_systems',
            'value_type' => 'integer',
            'unit' => 'institutions',
            'privacy_classification' => 'aggregate_only',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/admin/inclusive-finance/programmes/'.$programmeId.'/indicators', [
            'indicator_definition_id' => $indicatorId,
        ])->assertCreated();

        $this->postJson('/api/admin/inclusive-finance/programmes/'.$programmeId.'/observations', [
            'indicator_definition_id' => $indicatorId,
            'measurement_stage' => '90_day',
            'numeric_value' => 12,
            'unit' => 'institutions',
            'source_type' => 'administrative',
            'verification_status' => 'verified',
        ])->assertCreated();

        $this->getJson('/api/admin/inclusive-finance/programmes/'.$programmeId.'/outcomes')
            ->assertOk()
            ->assertJsonPath('data.indicator_summaries.0.participant_count', 0)
            ->assertJsonPath('data.indicator_summaries.0.suppressed', false)
            ->assertJsonPath('data.indicator_summaries.0.institutional_latest_numeric', 12);
    }

    public function test_programme_partner_role_has_no_access_without_an_explicit_programme_grant(): void
    {
        $partnerUser = User::factory()->create(['role' => User::ROLE_PROGRAMME_PARTNER]);
        Sanctum::actingAs($partnerUser);

        $this->getJson('/api/partner/inclusive-finance/programmes')
            ->assertOk()
            ->assertJsonPath('data.programmes', []);

        $programmeId = $this->createProgramme();

        Sanctum::actingAs($partnerUser);
        $this->getJson('/api/partner/inclusive-finance/programmes/'.$programmeId.'/impact')
            ->assertStatus(422);

        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($customer);
        $this->getJson('/api/partner/inclusive-finance/programmes')
            ->assertStatus(403);
    }

    private function createProgramme(): int
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        return $this->createProgrammeAsCurrentAdmin();
    }

    private function createProgrammeAsCurrentAdmin(): int
    {
        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        return $this->postJson('/api/admin/inclusive-finance/programmes', [
            'code' => 'IMPACT-'.$suffix,
            'name' => 'Impact Test '.$suffix,
            'status' => 'active',
        ])->assertCreated()->json('data.id');
    }
}
