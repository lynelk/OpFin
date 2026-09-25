<?php

namespace Tests\Feature;

use App\Services\ApiDocumentation\ApiDiscovery;
use App\Support\ApiDocumentation\ContractCatalogue;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class DeveloperCatalogueReviewTest extends TestCase
{
    private function catalogue(string $operationId = 'partner_report', string $status = 'documented'): ContractCatalogue
    {
        return new ContractCatalogue([
            ['method' => 'GET', 'path' => '/api/partner/report',
                'middleware' => ['auth:sanctum', 'role:programme_partner'], 'source_digest' => 'synthetic-source'],
            ['method' => 'GET', 'path' => '/api/undocumented',
                'middleware' => ['auth:sanctum'], 'source_digest' => 'synthetic-other-source'],
        ], [
            'GET /api/partner/report' => ['status' => $status, 'operation_id' => $operationId,
                'summary' => 'Synthetic report contract', 'description' => 'Documentation fixture only.',
                'responses' => ['200' => ['description' => 'Synthetic result']]],
        ], [], ['runtime_fingerprint' => 'synthetic-runtime', 'contract_fingerprint' => 'synthetic-contract']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_operation_id_only_change_is_a_blocking_compatibility_change(): void
    {
        $before = $this->catalogue()->snapshot();
        $after = $this->catalogue('renamed_partner_report');
        $changes = $after->diff($before);
        $this->assertCount(1, $changes);
        $this->assertTrue($changes[0]['identity_changed']);
        $this->assertTrue($changes[0]['review_required']);
        $this->assertSame('partner_report', $changes[0]['previous_operation_id']);
        $this->assertSame('renamed_partner_report', $changes[0]['operation_id']);
    }

    public function test_contract_status_downgrade_is_not_hidden_by_unchanged_schemas(): void
    {
        $changes = $this->catalogue('partner_report', 'registration_only')->diff($this->catalogue()->snapshot());
        $this->assertCount(1, $changes);
        $this->assertTrue($changes[0]['contract_status_changed']);
        $this->assertTrue($changes[0]['review_required']);
    }

    public function test_duplicate_baseline_operations_are_rejected(): void
    {
        $baseline = $this->catalogue()->snapshot();
        $baseline['operations'][] = $baseline['operations'][0];
        $this->expectException(InvalidArgumentException::class);
        $this->catalogue()->diff($baseline);
    }

    public function test_cli_exports_all_reviewed_roles_without_exporting_unreviewed_routes(): void
    {
        $catalogue = $this->catalogue();
        $directory = storage_path('framework/testing/catalogue-'.Str::uuid());
        $this->mock(ApiDiscovery::class, function ($mock) use ($catalogue): void {
            $mock->shouldReceive('catalogue')->andReturn($catalogue);
        });
        try {
            $this->artisan('api:catalogue', ['--output' => $directory])->assertSuccessful();
            $export = json_decode(File::get($directory.'/reviewed-openapi.json'), true, 64, JSON_THROW_ON_ERROR);
            $this->assertArrayHasKey('/api/partner/report', $export['paths']);
            $this->assertArrayNotHasKey('/api/undocumented', $export['paths']);
            $this->assertSame(1, $export['x-opfin-coverage']['documented']);
            $this->assertFalse($export['x-opfin-coverage']['complete']);
        } finally {
            File::deleteDirectory($directory);
        }
        Http::assertNothingSent();
    }

    public function test_http_role_view_does_not_inherit_offline_inventory_authority(): void
    {
        $catalogue = $this->catalogue();
        $adminPaths = (array) $catalogue->openApi('platform_admin')['paths'];
        $customerPaths = (array) $catalogue->openApi('customer')['paths'];
        $this->assertArrayNotHasKey('/api/partner/report', $adminPaths);
        $this->assertArrayNotHasKey('/api/partner/report', $customerPaths);
        $this->assertArrayHasKey('/api/partner/report', $catalogue->openApi('programme_partner')['paths']);
    }

    public function test_cli_baseline_gate_fails_on_an_operation_id_only_change(): void
    {
        $directory = storage_path('framework/testing/catalogue-baseline-'.Str::uuid());
        File::ensureDirectoryExists($directory);
        $path = $directory.'/baseline.json';
        File::put($path, json_encode($this->catalogue()->snapshot(), JSON_THROW_ON_ERROR));
        $catalogue = $this->catalogue('renamed_partner_report');
        $this->mock(ApiDiscovery::class, function ($mock) use ($catalogue): void {
            $mock->shouldReceive('catalogue')->andReturn($catalogue);
        });
        try {
            $this->artisan('api:catalogue', ['--check' => true, '--baseline' => $path])->assertFailed();
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
