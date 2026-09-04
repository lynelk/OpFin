<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class V5P0CompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_p0_capabilities_are_available_and_provider_callbacks_are_not_routed(): void
    {
        foreach ([
            'financial_passport', 'security_centre', 'budgeting', 'financial_calendar', 'credit_builder',
            'financial_shock_centre', 'payments', 'payment_reconciliation', 'product_factory',
            'workflow_engine', 'rules_engine',
        ] as $capability) {
            $this->assertSame('AVAILABLE', config('opfin.capabilities.'.$capability.'.status'));
        }

        $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri());
        $this->assertTrue($routes->contains('api/webhooks/cpay'));
        $this->assertFalse($routes->contains('api/airtel-callback'));
        $this->assertFalse($routes->contains('api/mtn-callback'));
        $this->assertFalse($routes->contains('api/handleCallback'));
    }

    public function test_governed_p0_tables_exist(): void
    {
        foreach ([
            'security_controls', 'security_events', 'credit_builder_plans', 'hardship_cases',
            'financial_passport_snapshots', 'product_definitions', 'decision_rules', 'workflow_definitions',
            'workflow_runs', 'workflow_transition_events',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' missing');
        }
    }
}
