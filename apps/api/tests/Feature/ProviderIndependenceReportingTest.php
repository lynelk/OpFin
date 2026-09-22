<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\User;
use App\Services\PositiveEmploymentBehaviourService;
use App\Services\ServiceEconomicsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProviderIndependenceReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_employer_behaviour_is_neutral_and_verified_positive_signal_only_adds_benefit(): void
    {
        $user = User::factory()->create();

        $neutral = app(PositiveEmploymentBehaviourService::class)->assess($user);
        $this->assertSame(0.0, $neutral['uplift_points']);
        $this->assertSame('neutral', $neutral['absence_effect']);
        $this->assertSame('neutral', $neutral['negative_signal_effect']);

        $consent = ConsentRecord::create([
            'user_id' => $user->id,
            'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'credit-v1',
            'status' => ConsentRecord::STATUS_GRANTED,
            'channel' => 'api',
            'granted_at' => now(),
        ]);

        DB::table('alternative_data_signals')->insert([
            'user_id' => $user->id,
            'source_type' => 'employer',
            'signal_key' => 'positive_performance',
            'signal_value' => json_encode(true, JSON_THROW_ON_ERROR),
            'purpose' => 'credit_assessment',
            'consent_record_id' => $consent->id,
            'risk_eligible' => true,
            'verified' => true,
            'provider_reference' => 'EMP-POSITIVE-001',
            'provenance' => json_encode(['source' => 'employer_verified'], JSON_THROW_ON_ERROR),
            'observed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('alternative_data_signals')->insert([
            'user_id' => $user->id,
            'source_type' => 'employer',
            'signal_key' => 'attendance_reliability',
            'signal_value' => json_encode(false, JSON_THROW_ON_ERROR),
            'purpose' => 'credit_assessment',
            'consent_record_id' => $consent->id,
            'risk_eligible' => true,
            'verified' => true,
            'provider_reference' => 'EMP-NEGATIVE-001',
            'provenance' => json_encode(['source' => 'employer_verified'], JSON_THROW_ON_ERROR),
            'observed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $benefit = app(PositiveEmploymentBehaviourService::class)->assess($user);

        $this->assertSame(1.5, $benefit['uplift_points']);
        $this->assertSame(['POSITIVE_EMPLOYMENT_BEHAVIOUR_UPLIFT'], $benefit['reason_codes']);
        $this->assertCount(1, $benefit['applied_signals']);
        $this->assertSame('positive_performance', $benefit['applied_signals'][0]['signal_key']);
    }

    public function test_service_economics_reconciliation_is_idempotent_and_preserves_known_values(): void
    {
        $service = app(ServiceEconomicsService::class);

        $first = $service->record([
            'service_code' => 'insurance',
            'capability_code' => 'premium_collection_and_settlement',
            'provider' => 'Example Insurer',
            'route' => 'DIRECT_PARTNER',
            'request_reference' => 'OPF-PRM-ECON-001',
            'status' => 'COLLECTED',
            'currency' => 'UGX',
            'provider_gross_cost_minor' => 100,
            'provider_discount_minor' => 0,
            'customer_service_charge_minor' => 0,
            'customer_platform_fee_minor' => 50,
            'partner_commission_minor' => 0,
            'cito_platform_fee_minor' => 0,
            'opfin_platform_fee_minor' => 0,
            'tax_amount_minor' => 0,
        ]);

        $this->assertSame(50, (int) $first->gross_revenue_minor);
        $this->assertSame(50, (int) $first->net_revenue_minor);
        $this->assertSame(-50, (int) $first->gross_margin_minor);

        $second = $service->record([
            'service_code' => 'insurance',
            'provider' => 'Example Insurer',
            'route' => 'DIRECT_PARTNER',
            'request_reference' => 'OPF-PRM-ECON-001',
            'status' => 'RECONCILED',
            'provider_reference' => 'INS-SETTLEMENT-001',
            'net_settlement_to_provider_minor' => 100,
            'reconciled_at' => now(),
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(50, (int) $second->customer_platform_fee_minor);
        $this->assertSame(100, (int) $second->net_settlement_to_provider_minor);
        $this->assertSame('RECONCILED', $second->status);
        $this->assertDatabaseCount('service_economics_events', 1);
    }
}
