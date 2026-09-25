<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ApiDocumentation\ApiDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeveloperDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_public_manifest_is_read_only_and_has_source_fingerprints(): void
    {
        $response = $this->getJson('/api/developer/manifest')->assertOk()->assertJsonPath('data.financial_execution', false);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $response->json('data.provenance.contract_fingerprint'));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $response->json('data.provenance.runtime_fingerprint'));
        Http::assertNothingSent();
    }

    public function test_public_catalogue_contains_only_explicitly_published_operations(): void
    {
        $items = $this->getJson('/api/developer/public?limit=50')->assertOk()->json('data.items');
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertTrue($item['public_documentation']);
            $this->assertSame('documented', $item['contract_status']);
            $this->assertStringStartsWith('/api/developer/', $item['path']);
        }
    }

    public function test_private_catalogue_requires_authentication(): void
    {
        $this->getJson('/api/developer/catalogue')->assertUnauthorized();
        $this->getJson('/api/developer/openapi')->assertUnauthorized();
        $this->getJson('/api/developer/agent-tools')->assertUnauthorized();
    }

    public function test_query_role_does_not_override_authenticated_role(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $items = $this->getJson('/api/developer/catalogue?q=admin&role=platform_admin&limit=50')->assertOk()->json('data.items');
        foreach ($items as $item) {
            $this->assertStringNotContainsString('/api/admin/', $item['path']);
        }
    }

    public function test_administrator_can_discover_but_not_execute_an_admin_route(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'platform_admin']));
        $result = $this->getJson('/api/developer/catalogue?q=foundation-check')->assertOk();
        $this->assertGreaterThan(0, $result->json('data.total'));
        $this->assertSame('not_exposed', $result->json('data.items.0.agent_execution'));
        Http::assertNothingSent();
    }

    public function test_all_visible_catalogue_entries_are_documentation_not_agent_execution(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $items = $this->getJson('/api/developer/catalogue?limit=50')->assertOk()->json('data.items');
        foreach ($items as $item) {
            $this->assertSame('not_exposed', $item['agent_execution']);
        }
    }

    public function test_missing_contracts_are_counted_honestly(): void
    {
        $coverage = app(ApiDiscovery::class)->catalogue()->coverage(null, true);
        $this->assertGreaterThan(0, $coverage['registration_only']);
        $this->assertFalse($coverage['complete']);
        $this->assertSame(8, $coverage['documented']);
        $this->assertSame([], $coverage['definition_errors']);
    }

    public function test_sdk_openapi_excludes_unreviewed_domain_operations(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'platform_admin']));
        $document = $this->getJson('/api/developer/openapi')->assertOk()->json();
        $this->assertSame('3.1.1', $document['openapi']);
        $this->assertArrayNotHasKey('success', $document);
        $this->assertArrayNotHasKey('/api/essentials/advances/{advance}/repay', $document['paths']);
        $this->assertArrayHasKey('/api/developer/catalogue', $document['paths']);
        $this->assertFalse($document['x-opfin-coverage']['complete']);
    }

    public function test_operation_details_are_subject_to_catalogue_visibility(): void
    {
        $catalogue = app(ApiDiscovery::class)->catalogue();
        $admin = $catalogue->search('platform_admin', 'foundation-check')['items'][0];
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson('/api/developer/operations/'.$admin['id'])->assertNotFound();
    }

    public function test_guides_are_allow_listed_and_searchable_without_customer_data(): void
    {
        $response = $this->getJson('/api/developer/guides?q=CPay')->assertOk();
        $this->assertNotEmpty($response->json('data.guides'));
        $guide = $this->getJson('/api/developer/guides/agents')->assertOk()->json('data.guide');
        $this->assertSame('agents', $guide['id']);
        $this->assertSame(hash('sha256', $guide['text']), $guide['sha256']);
        $this->getJson('/api/developer/guides/not-an-approved-guide')->assertNotFound();
    }

    public function test_search_limits_are_validated(): void
    {
        $this->getJson('/api/developer/public?limit=51')->assertUnprocessable();
        $this->getJson('/api/developer/public?page=0')->assertUnprocessable();
        $this->getJson('/api/developer/public?q='.str_repeat('x', 161))->assertUnprocessable();
        $this->getJson('/api/developer/public?method=EXECUTE')->assertUnprocessable();
    }

    public function test_search_is_deterministic_and_paginated(): void
    {
        $first = $this->getJson('/api/developer/public?limit=1&page=1')->assertOk();
        $second = $this->getJson('/api/developer/public?limit=1&page=2')->assertOk();
        $this->assertTrue($first->json('data.has_more'));
        $this->assertNotSame($first->json('data.items.0.id'), $second->json('data.items.0.id'));
        $again = $this->getJson('/api/developer/public?limit=1&page=1')->assertOk();
        $this->assertSame($first->json('data.items.0.id'), $again->json('data.items.0.id'));
    }

    public function test_metadata_has_no_store_and_safe_correlation_headers(): void
    {
        $result = $this->getJson('/api/developer/manifest')->assertOk();
        $this->assertStringContainsString('no-store', $result->headers->get('Cache-Control'));
        $this->assertNotEmpty($result->headers->get('X-Request-ID'));
        $this->assertSame($result->json('data.provenance.contract_fingerprint'), $result->headers->get('X-OpFin-Contract-Fingerprint'));
    }

    public function test_agent_catalogue_has_only_the_four_read_only_tools(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $tools = $this->getJson('/api/developer/agent-tools')->assertOk()->json('data.tools');
        $this->assertSame(['opfin_search_api', 'opfin_describe_operation', 'opfin_search_guides', 'opfin_read_guide'], array_column($tools, 'name'));
        foreach ($tools as $tool) {
            $this->assertTrue($tool['annotations']['readOnlyHint']);
            $this->assertFalse($tool['annotations']['destructiveHint']);
            $this->assertFalse($tool['inputSchema']['additionalProperties']);
        }
        Http::assertNothingSent();
    }

    public function test_there_is_no_generic_agent_execution_route(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/developer/execute', ['path' => '/api/loans/1/repay'])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_portal_uses_local_assets_and_no_inline_execution(): void
    {
        $response = $this->get('/developers')->assertOk()->assertSee('OpFin Developer Centre');
        $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $response->assertDontSee('localStorage', false)->assertDontSee('cdn.', false);
    }

    public function test_catalogue_definition_check_passes_but_full_completion_gate_fails(): void
    {
        $this->artisan('api:catalogue', ['--check' => true])->assertSuccessful();
        $this->artisan('api:catalogue', ['--check' => true, '--require-complete' => true])->assertFailed();
    }

    public function test_invalid_baseline_is_not_silently_skipped(): void
    {
        $this->artisan('api:catalogue', ['--check' => true, '--baseline' => '/not-present/opfin-baseline.json'])->assertFailed();
    }
}
