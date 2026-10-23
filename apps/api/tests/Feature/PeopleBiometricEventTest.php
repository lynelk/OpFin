<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PeopleBiometricEventTest extends TestCase
{
    use RefreshDatabase;

    private function setupDevice(): array
    {
        config()->set('services.opfin_people.biometric_event_enabled', true);
        config()->set('services.opfin_people.biometric_event_secret', str_repeat('f', 64));
        Cache::flush();

        $owner = User::factory()->create();
        $hr = User::factory()->create();
        $platform = User::factory()->create(['role' => 'platform_admin']);
        $worker = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/financial-spaces', [
            'type' => 'business', 'name' => 'Signed Vendor Attendance',
        ])->assertCreated()->json('data.space.id');

        foreach ([$worker->id => 'member', $hr->id => 'administrator'] as $userId => $role) {
            DB::table('financial_space_memberships')->insert([
                'financial_space_id' => $id, 'user_id' => $userId,
                'role' => $role, 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $base = '/api/people/spaces/'.$id;
        $engagement = $this->postJson($base.'/employees', [
            'user_id' => $worker->id,
            'workforce_number' => 'TEST-BIO-01',
            'engagement_type' => 'permanent',
            'job_title' => 'Warehouse Operative',
            'starts_on' => '2026-01-01',
        ])->assertCreated()->json('data.employee.id');
        $connector = $this->postJson($base.'/biometrics/connectors', [
            'vendor' => 'Permitted Clock Vendor',
            'external_device_reference' => 'clock-001',
        ])->assertCreated()->json('data.connector.id');

        return [$owner, $hr, $platform, $worker, $id, $engagement, $connector, $base];
    }

    private function signedEvent(int $spaceId, int $connectorId, string $eventId): TestResponse
    {
        $body = json_encode([
            'schema_version' => 1,
            'vendor_event_id' => $eventId,
            'external_subject_id' => 'worker-device-001',
            'direction' => 'in',
            'occurred_at' => now()->subMinute()->toIso8601String(),
            'verification_method' => 'device_verified',
        ], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $signature = hash_hmac(
            'sha256',
            "POST\n".$spaceId."\n".$connectorId."\n".$timestamp."\n".$nonce."\n".$body,
            str_repeat('f', 64)
        );

        return $this->call(
            'POST',
            '/api/people/device-ingest/'.$spaceId.'/'.$connectorId,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PEOPLE_DEVICE_TIMESTAMP' => $timestamp,
                'HTTP_X_PEOPLE_DEVICE_NONCE' => $nonce,
                'HTTP_X_PEOPLE_DEVICE_SIGNATURE' => $signature,
            ],
            $body
        );
    }

    public function test_unsigned_device_event_and_unreviewed_device_cannot_create_attendance(): void
    {
        [$owner, $hr, $platform, $worker, $id, $engagement, $connector, $base] = $this->setupDevice();

        $this->postJson('/api/people/device-ingest/'.$id.'/'.$connector, [
            'schema_version' => 1,
        ])->assertUnauthorized();
        $this->signedEvent($id, $connector, 'vendor-event-000001')->assertForbidden();
        $this->assertDatabaseCount('people_attendance_events', 0);
        $this->assertDatabaseCount('people_biometric_event_receipts', 0);
    }

    public function test_approved_device_and_privacy_bound_subject_ingest_only_reviewable_clock_event(): void
    {
        [$owner, $hr, $platform, $worker, $id, $engagement, $connector, $base] = $this->setupDevice();

        Sanctum::actingAs($platform);
        $this->postJson('/api/admin/people/spaces/'.$id.'/biometrics/connectors/'.$connector.'/approve', [
            'processing_approval_reference' => 'approved-dpia-reference-001',
            'privacy_notice_reference' => 'employee-notice-reference-001',
            'vendor_security_review_reference' => 'vendor-assurance-record-001',
        ])->assertOk()->assertJsonPath('data.template_storage_enabled', false);

        Sanctum::actingAs($owner);
        $binding = $this->postJson($base.'/biometrics/connectors/'.$connector.'/bindings', [
            'engagement_id' => $engagement,
            'external_subject_id' => 'worker-device-001',
            'lawful_basis_reference' => 'lawful-processing-reference-001',
            'privacy_notice_reference' => 'employee-privacy-notice-001',
        ])->assertCreated()->assertJsonPath('data.subject_hash_only', true)
            ->json('data.binding_id');
        $this->signedEvent($id, $connector, 'vendor-event-000001')->assertForbidden();

        Sanctum::actingAs($hr);
        $this->postJson($base.'/biometrics/connectors/'.$connector.'/bindings/'.$binding.'/approve')
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->signedEvent($id, $connector, 'vendor-event-000001')
            ->assertOk()->assertJsonPath('data.status', 'accepted_for_review')
            ->assertJsonPath('data.attendance_status', 'submitted');
        $this->signedEvent($id, $connector, 'vendor-event-000001')->assertOk();
        $this->assertDatabaseCount('people_attendance_events', 1);
        $this->assertDatabaseCount('people_biometric_event_receipts', 1);
        $this->assertDatabaseHas('people_attendance_events', [
            'business_space_id' => $id,
            'engagement_id' => $engagement,
            'source' => 'biometric_device',
            'status' => 'submitted',
        ]);
        $this->assertDatabaseMissing('people_attendance_events', ['status' => 'approved']);
    }

    public function test_adapter_is_fail_closed_when_disabled(): void
    {
        [$owner, $hr, $platform, $worker, $id, $engagement, $connector, $base] = $this->setupDevice();
        config()->set('services.opfin_people.biometric_event_enabled', false);
        $this->signedEvent($id, $connector, 'vendor-event-000001')->assertStatus(503);
        $this->assertDatabaseCount('people_attendance_events', 0);
    }
}
