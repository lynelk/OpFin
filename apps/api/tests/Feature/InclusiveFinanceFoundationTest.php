<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InclusiveFinanceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_measurement_attributes_require_explicit_programme_consent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/inclusive-finance/profile', [
            'measurement_attributes' => ['gender' => 'female'],
        ])->assertStatus(422);

        $this->patchJson('/api/inclusive-finance/profile', [
            'programme_measurement_consent' => true,
            'measurement_attributes' => [
                'gender' => 'female',
                'disability_status' => 'uses_assistive_technology',
            ],
        ])->assertOk()
            ->assertJsonPath('data.programme_measurement_consent', true)
            ->assertJsonPath('data.decisioning_use_allowed', false)
            ->assertJsonPath('data.measurement_attributes.gender', 'female');

        $this->patchJson('/api/inclusive-finance/profile', [
            'programme_measurement_consent' => false,
        ])->assertOk()
            ->assertJsonPath('data.measurement_attributes', []);
    }

    public function test_customer_reported_and_measurement_signals_cannot_become_credit_risk_inputs(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $signalId = $this->postJson('/api/inclusive-finance/signals', [
            'signal_key' => 'gender',
            'signal_value' => 'female',
            'purpose' => 'programme_measurement',
        ])->assertCreated()
            ->assertJsonPath('data.risk_eligible', false)
            ->json('data.id');

        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/inclusive-finance/signals/'.$signalId.'/verify', [
            'risk_eligible' => true,
        ])->assertStatus(422);
    }

    public function test_verified_provider_signal_needs_credit_processing_consent_before_risk_use(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $signalId = $this->postJson('/api/admin/inclusive-finance/signals', [
            'user_id' => $customer->id,
            'source_type' => 'employer',
            'signal_key' => 'verified_monthly_income_minor',
            'signal_value' => 150000000,
            'provider_reference' => 'EMP-VERIFY-001',
        ])->assertCreated()->json('data.id');

        $this->patchJson('/api/admin/inclusive-finance/signals/'.$signalId.'/verify', [
            'risk_eligible' => true,
        ])->assertStatus(422);

        ConsentRecord::create([
            'user_id' => $customer->id,
            'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'test-v1',
            'status' => ConsentRecord::STATUS_GRANTED,
            'channel' => 'app',
            'granted_at' => now(),
        ]);

        $this->patchJson('/api/admin/inclusive-finance/signals/'.$signalId.'/verify', [
            'risk_eligible' => true,
        ])->assertOk()
            ->assertJsonPath('data.risk_eligible', true)
            ->assertJsonPath('data.verified', true);
    }

    public function test_programme_enrolment_and_impact_reporting_are_privacy_suppressed_for_small_cohorts(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $programmeId = $this->postJson('/api/admin/inclusive-finance/programmes', [
            'code' => 'BIFS-YOUTH-01',
            'name' => 'Inclusive Finance Pilot',
            'status' => 'active',
            'target_population' => ['age_cohort' => '18-35'],
        ])->assertCreated()->json('data.id');

        $customer = User::factory()->create();
        Sanctum::actingAs($customer);
        $this->patchJson('/api/inclusive-finance/profile', [
            'programme_measurement_consent' => true,
            'measurement_attributes' => ['age_cohort' => '18-35'],
        ])->assertOk();
        $this->postJson('/api/inclusive-finance/programmes/'.$programmeId.'/enrol', [])
            ->assertCreated()
            ->assertJsonPath('data.enrolment.status', 'enrolled');

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/inclusive-finance/impact?programme_id='.$programmeId)
            ->assertOk()
            ->assertJsonPath('data.enrolled_people', 1)
            ->assertJsonPath('data.privacy.small_cohorts_suppressed', true)
            ->assertJsonPath('data.cohorts', []);
    }

    public function test_alternative_collateral_is_recorded_but_not_auto_approved(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $instrumentId = $this->postJson('/api/inclusive-finance/support-instruments', [
            'instrument_type' => 'warehouse_receipt',
            'provider_name' => 'Example Warehouse',
            'external_reference' => 'WR-001',
            'value_minor' => 50000000,
            'evidence' => ['receipt_hash' => 'abc123'],
        ])->assertCreated()
            ->assertJsonPath('data.verification_status', 'pending')
            ->json('data.id');

        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/inclusive-finance/support-instruments/'.$instrumentId.'/verify', [
            'status' => 'verified',
        ])->assertOk()
            ->assertJsonPath('data.verification_status', 'verified');

        $this->assertDatabaseHas('credit_support_instruments', [
            'id' => $instrumentId,
            'verification_status' => 'verified',
        ]);
    }

    public function test_fair_treatment_view_explicitly_excludes_programme_measurement_attributes(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $this->getJson('/api/inclusive-finance/fair-treatment')
            ->assertOk()
            ->assertJsonPath('data.protected_or_measurement_attributes_used', false);
    }
}
