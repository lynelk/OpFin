<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use App\Services\AutonomousOperationsService;
use App\Services\ExperiencePlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExperiencePlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_otp_proof_is_required_for_registration(): void
    {
        Otp::create([
            'phone' => '256700111222',
            'otp' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $verification = $this->postJson('/api/verify-otp', [
            'phone' => '256700111222',
            'otp' => '123456',
        ])->assertOk();

        $token = $verification->json('data.verification_token');
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));

        $this->postJson('/api/register', [
            'name' => 'Verified Customer',
            'phone' => '256700111222',
            'verification_token' => $token,
            'password' => 'Secure!Password123',
            'password_confirmation' => 'Secure!Password123',
        ])->assertCreated()->assertJsonPath('success', true);

        $this->assertNotNull(User::where('phone', '256700111222')->firstOrFail()->phone_verified_at);
        $this->assertDatabaseMissing('otps', ['phone' => '256700111222']);
    }

    public function test_activation_and_money_autopilot_are_persisted_without_claiming_settlement(): void
    {
        $user = User::factory()->create(['phone_verified_at' => now()]);
        $service = app(ExperiencePlatformService::class);

        $activation = $service->saveActivation($user, [
            'primary_financial_goal' => 'build_emergency_fund',
            'notifications_enabled' => true,
        ]);

        $this->assertSame('build_emergency_fund', $activation['profile']->primary_financial_goal);

        $rule = $service->createMoneyAutopilotRule($user, [
            'name' => 'Emergency buffer',
            'rule_type' => 'scheduled_save',
            'trigger_config' => ['cadence' => 'monthly'],
            'action_config' => ['type' => 'savings_contribution', 'amount_minor' => 50000],
            'max_amount_minor' => 50000,
        ]);

        $this->assertSame('active', $rule->status);
        $service->evaluateMoneyAutopilotRules($user->id);

        $execution = DB::table('money_autopilot_executions')->where('rule_id', $rule->id)->first();
        $this->assertNotNull($execution);
        $this->assertSame('awaiting_provider_or_trigger_confirmation', $execution->status);
        $this->assertNull($execution->executed_at);
    }

    public function test_investment_order_requires_suitability_and_remains_pending_provider(): void
    {
        $user = User::factory()->create();
        $service = app(ExperiencePlatformService::class);

        $product = $service->createInvestmentProduct([
            'product_code' => 'TEST-MMF',
            'name' => 'Test Money Market Fund',
            'provider_name' => 'Test Licensed Provider',
            'product_type' => 'money_market_fund',
            'risk_level' => 'low',
            'minimum_investment_minor' => 10000,
            'disclosures' => ['capital_at_risk' => true],
        ], 'USER:maker');
        DB::table('investment_products')->where('id', $product->id)->update([
            'status' => 'active',
            'approved_by' => 'USER:checker',
            'approved_at' => now(),
        ]);

        $service->saveSuitability($user, [
            'risk_tolerance' => 'low',
            'investment_horizon' => '1-3-years',
            'liquidity_need' => 'medium',
            'experience_level' => 'new',
        ]);

        $order = $service->createInvestmentOrder($user, $product->id, [
            'amount_minor' => 25000,
            'idempotency_key' => '11111111-1111-4111-8111-111111111111',
            'disclosure_acknowledged' => true,
        ]);

        $this->assertSame('pending_provider', $order->status);
        $this->assertNull($order->settled_at);
    }

    public function test_platform_autopilot_run_completes_with_empty_operational_queue(): void
    {
        $result = app(AutonomousOperationsService::class)->run('test');

        $this->assertArrayHasKey('run_id', $result);
        $this->assertDatabaseHas('autopilot_runs', [
            'id' => $result['run_id'],
            'status' => 'completed',
        ]);
    }
}
