<?php

namespace Tests\Feature;

use App\Models\LocationContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_location_defaults_to_user_controlled_approximate_context(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $this->getJson('/api/location/status')
            ->assertOk()
            ->assertJsonPath('data.background_tracking', false)
            ->assertJsonPath('data.default_precision', 'approximate');

        $response = $this->postJson('/api/location-contexts', [
            'subject_type' => 'user',
            'subject_id' => $user->id,
            'purpose' => 'personal_service_discovery',
            'source' => 'device',
            'precision_level' => 'approximate',
            'latitude' => 0.347596,
            'longitude' => 32.582520,
            'accuracy_metres' => 48,
            'consent_purpose' => 'personal_service_discovery',
        ])->assertCreated()
            ->assertJsonPath('data.location.latitude', 0.348)
            ->assertJsonPath('data.location.longitude', 32.583)
            ->assertJsonPath('data.location.precision_level', 'approximate');

        $contextId = (int) $response->json('data.location.id');

        $other = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($other);
        $this->getJson('/api/location-contexts?subject_type=user&subject_id='.$user->id)
            ->assertForbidden();

        Sanctum::actingAs($user);
        $this->deleteJson('/api/location-contexts/'.$contextId)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
    }

    public function test_google_place_resolution_and_static_map_keep_key_server_side(): void
    {
        config()->set('services.google_maps.enabled', true);
        config()->set('services.google_maps.server_api_key', 'server-secret-key');
        config()->set('services.google_maps.static_maps_enabled', true);

        Http::fake([
            'https://places.googleapis.com/v1/places/*' => Http::response([
                'id' => 'place-123',
                'displayName' => ['text' => 'Kampala Central'],
                'formattedAddress' => 'Kampala, Uganda',
                'location' => ['latitude' => 0.3136, 'longitude' => 32.5811],
                'addressComponents' => [
                    ['longText' => 'Kampala', 'shortText' => 'Kampala', 'types' => ['administrative_area_level_1']],
                    ['longText' => 'Kampala', 'shortText' => 'Kampala', 'types' => ['locality']],
                    ['longText' => 'Uganda', 'shortText' => 'UG', 'types' => ['country']],
                ],
                'plusCode' => ['globalCode' => '6GGJ8H7J+CJ'],
            ], 200),
            'https://maps.googleapis.com/maps/api/staticmap*' => Http::response('png-bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $stored = $this->postJson('/api/location-contexts', [
            'subject_type' => 'user',
            'subject_id' => $user->id,
            'purpose' => 'personal_service_discovery',
            'source' => 'manual',
            'precision_level' => 'approximate',
            'google_place_id' => 'place-123',
            'consent_purpose' => 'personal_service_discovery',
        ])->assertCreated()
            ->assertJsonPath('data.location.formatted_address', 'Kampala, Uganda')
            ->assertJsonPath('data.location.country_code', 'UG')
            ->assertJsonPath('data.location.google_place_id', 'place-123');

        $payload = $stored->json('data.location');
        $this->assertStringNotContainsString('server-secret-key', json_encode($payload));

        $contextId = (int) $payload['id'];
        $map = $this->get('/api/location/static-map/'.$contextId);
        $map->assertOk();
        $this->assertSame('image/png', $map->headers->get('Content-Type'));
        $this->assertSame('png-bytes', $map->getContent());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'staticmap')
                && str_contains($request->url(), 'server-secret-key');
        });
    }

    public function test_group_member_can_read_location_but_only_group_admin_can_change_it(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);

        $spaceId = (int) $this->postJson('/api/financial-spaces', [
            'type' => 'savings_group',
            'name' => 'Village Prosperity Group',
        ])->assertCreated()->json('data.space.id');

        $this->postJson('/api/location-contexts', [
            'subject_type' => 'financial_space',
            'subject_id' => $spaceId,
            'purpose' => 'group_meeting_place',
            'source' => 'manual',
            'precision_level' => 'locality',
            'place_name' => 'Trading Centre Hall',
            'formatted_address' => 'Trading Centre, Uganda',
            'country_code' => 'UG',
            'consent_purpose' => 'group_meeting_place',
        ])->assertCreated();

        $member = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        DB::table('financial_space_memberships')->insert([
            'financial_space_id' => $spaceId,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($member);
        $this->getJson('/api/location-contexts?subject_type=financial_space&subject_id='.$spaceId)
            ->assertOk()
            ->assertJsonCount(1, 'data.locations');

        $this->postJson('/api/location-contexts', [
            'subject_type' => 'financial_space',
            'subject_id' => $spaceId,
            'purpose' => 'group_operating_area',
            'source' => 'manual',
            'precision_level' => 'district',
            'country_code' => 'UG',
            'admin_area_1' => 'Central Region',
            'consent_purpose' => 'group_operating_area',
        ])->assertForbidden();

        DB::table('financial_space_memberships')
            ->where('financial_space_id', $spaceId)
            ->where('user_id', $member->id)
            ->update(['deleted_at' => now()]);

        $this->getJson('/api/location-contexts?subject_type=financial_space&subject_id='.$spaceId)
            ->assertForbidden();
    }

    public function test_nearby_service_points_do_not_store_query_location_and_aggregate_insights_suppress_small_cohorts(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        Sanctum::actingAs($operator);

        for ($i = 1; $i <= 5; $i++) {
            $partnerId = DB::table('partners')->insertGetId([
                'code' => 'PARTNER-'.$i,
                'name' => 'Partner '.$i,
                'partner_type' => 'financial_service',
                'country' => 'UG',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->postJson('/api/location-contexts', [
                'subject_type' => 'partner_service_point',
                'subject_id' => $partnerId,
                'purpose' => 'partner_service_point',
                'source' => 'partner',
                'precision_level' => 'precise',
                'place_name' => 'Service Point '.$i,
                'country_code' => 'UG',
                'admin_area_1' => 'Central Region',
                'admin_area_2' => 'Kampala',
                'latitude' => 0.3136 + ($i * 0.001),
                'longitude' => 32.5811 + ($i * 0.001),
                'consent_purpose' => 'partner_service_point',
                'verification_status' => 'partner_confirmed',
            ])->assertCreated();
        }

        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($customer);

        $before = LocationContext::query()->count();
        $this->getJson('/api/location/nearby-services?latitude=0.3136&longitude=32.5811&radius_km=25')
            ->assertOk()
            ->assertJsonCount(5, 'data.service_points')
            ->assertJsonPath('data.location_stored', false);
        $this->assertSame($before, LocationContext::query()->count());

        Sanctum::actingAs($operator);
        $this->getJson('/api/admin/location-insights')
            ->assertOk()
            ->assertJsonPath('data.minimum_cohort', 5)
            ->assertJsonPath('data.individual_locations_exposed', false)
            ->assertJsonPath('data.rows.0.count', 5);
    }
}
