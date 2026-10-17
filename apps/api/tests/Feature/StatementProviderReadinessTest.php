<?php

namespace Tests\Feature;

use App\Services\FinancialIntelligence\Layouts\GenericRunningBalanceLayout;
use App\Services\FinancialIntelligence\Layouts\LayoutRegistry;
use Tests\TestCase;

class StatementProviderReadinessTest extends TestCase
{
    public function test_unverified_uganda_provider_statements_never_acquire_authenticity_by_configuration(): void
    {
        foreach (config('statement_provider_readiness.UG') as $provider) {
            $this->assertFalse($provider['production_accepted']);
            $this->assertSame('not_verified', $provider['independent_issuer_authenticity']);
        }
        foreach (['mtn_momo_uganda', 'airtel_money_uganda', 'licensed_banks'] as $issuer) {
            $adapters = app(LayoutRegistry::class)->for($issuer);
            $this->assertCount(1, $adapters);
            $this->assertInstanceOf(GenericRunningBalanceLayout::class, $adapters[0]);
            $this->assertSame([], $adapters[0]->validatedFor());
        }
    }
}
