<?php

namespace Tests\Feature;

use App\Models\FinancialSpace;
use App\Models\User;
use App\Services\FinancialSpacePayoutMandateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialSpacePayoutMandateTest extends TestCase
{
    use RefreshDatabase;

    private function space(): FinancialSpace
    {
        return FinancialSpace::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'investment_club', 'name' => 'Mandate Test Club',
            'country' => 'UG', 'currency' => 'UGX', 'status' => 'active',
        ]);
    }

    private function payload(): array
    {
        return [
            'currency' => 'UGX',
            'custody_agreement_reference' => 'CUSTODY-2026-123',
            'segregated_settlement_account_reference' => 'SEGREGATED-ABC123',
            'document_sha256' => str_repeat('a', 64),
            'expires_at' => now()->addMonth()->toIso8601String(),
        ];
    }

    public function test_only_independent_platform_admins_can_approve_a_mandate(): void
    {
        $space = $this->space();
        $maker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $checker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        Sanctum::actingAs($customer);
        $this->postJson("/api/admin/financial-spaces/{$space->id}/payout-mandates", $this->payload())
            ->assertForbidden();
        Sanctum::actingAs($maker);
        $id = $this->postJson("/api/admin/financial-spaces/{$space->id}/payout-mandates", $this->payload())
            ->assertCreated()->json('data.mandate.id');
        $this->postJson("/api/admin/financial-spaces/{$space->id}/payout-mandates/{$id}/approve")
            ->assertStatus(409);
        Sanctum::actingAs($checker);
        $this->postJson("/api/admin/financial-spaces/{$space->id}/payout-mandates/{$id}/approve")
            ->assertOk()->assertJsonPath('data.mandate.status', 'approved');
    }

    public function test_approved_mandate_still_requires_independent_provider_release_gate(): void
    {
        $space = $this->space();
        $maker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $checker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $service = app(FinancialSpacePayoutMandateService::class);
        $mandate = $service->submit($space, $maker, $this->payload());
        $service->approve($space, $checker, $mandate->id);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('independently accepted custody');
        $service->assertAccepted($space, 'UGX');
    }

    public function test_mandate_must_match_space_and_currency(): void
    {
        config(['opfin.integrations.financial_space_payouts_accepted' => true]);
        $space = $this->space();
        $other = $this->space();
        $maker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $checker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $service = app(FinancialSpacePayoutMandateService::class);
        $mandate = $service->submit($space, $maker, $this->payload());
        $service->approve($space, $checker, $mandate->id);
        $service->assertAccepted($space, 'UGX');
        $this->expectException(\InvalidArgumentException::class);
        $service->assertAccepted($other, 'UGX');
    }
}
