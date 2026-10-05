<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentStorageCheckTest extends TestCase
{
    public function test_storage_check_round_trips_a_probe_on_each_document_disk_and_leaves_nothing_behind(): void
    {
        config(['services.identity_verification.disk' => 's3', 'financial_intelligence.statement_disk' => 's3']);
        Storage::fake('s3');

        $this->artisan('opfin:storage-check')
            ->expectsOutputToContain("KYC documents: disk 's3'")
            ->expectsOutputToContain("Statement uploads: disk 's3'")
            ->assertSuccessful();

        $this->assertSame([], Storage::disk('s3')->allFiles());
    }

    public function test_storage_check_fails_when_a_disk_is_not_configured(): void
    {
        config(['financial_intelligence.statement_disk' => 'missing-disk']);
        Storage::fake('local');

        $this->artisan('opfin:storage-check')
            ->expectsOutputToContain("Statement uploads: disk 'missing-disk' did not pass")
            ->assertFailed();
    }
}
