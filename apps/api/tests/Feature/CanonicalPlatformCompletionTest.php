<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CanonicalPlatformCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_person_can_create_multiple_spaces_and_spaces_are_isolated(): void
    {
        $owner=User::factory()->create(); $outsider=User::factory()->create(); Sanctum::actingAs($owner);
        $group=$this->postJson('/api/financial-spaces',['type'=>'savings_group','name'=>'Kampala Savers'])->assertCreated()->json('data.space');
        $business=$this->postJson('/api/financial-spaces',['type'=>'business','name'=>'Example Business'])->assertCreated()->json('data.space');
        $this->assertNotSame($group['id'],$business['id']);
        $this->postJson('/api/financial-spaces/'.$group['id'].'/assets',['asset_type'=>'cash_box','name'=>'Group cash','value_minor'=>100000])->assertCreated();
        $this->getJson('/api/financial-spaces/'.$group['id'].'/financial-life')->assertOk()->assertJsonPath('data.assets_minor',100000);
        Sanctum::actingAs($outsider);
        $this->getJson('/api/financial-spaces/'.$group['id'].'/financial-life')->assertForbidden();
    }

    public function test_invitation_is_single_use_and_joins_existing_identity(): void
    {
        $owner=User::factory()->create(); $member=User::factory()->create(); Sanctum::actingAs($owner);
        $space=$this->postJson('/api/financial-spaces',['type'=>'savings_group','name'=>'Village Group'])->json('data.space');
        $token=$this->postJson('/api/financial-spaces/'.$space['id'].'/invitations',['phone'=>'256700000001','role'=>'member'])->assertCreated()->json('data.invitation_token');
        Sanctum::actingAs($member);
        $this->postJson('/api/financial-spaces/invitations/accept',['token'=>$token])->assertOk();
        $this->postJson('/api/financial-spaces/invitations/accept',['token'=>$token])->assertStatus(422);
    }

    public function test_business_can_enable_employer_without_exposing_personal_space(): void
    {
        $user=User::factory()->create(); Sanctum::actingAs($user);
        $business=$this->postJson('/api/financial-spaces',['type'=>'business','name'=>'Employer Ltd'])->json('data.space');
        $this->postJson('/api/financial-spaces/'.$business['id'].'/employer/enable')->assertOk()->assertJsonPath('data.employer_services',true);
        $this->getJson('/api/financial-spaces/'.$business['id'].'/workspace')->assertOk()->assertJsonFragment(['employer_services']);
    }

    public function test_financial_life_calculates_net_worth_and_safe_to_spend(): void
    {
        $user=User::factory()->create(); Sanctum::actingAs($user);
        $space=$this->postJson('/api/financial-spaces',['type'=>'savings_group','name'=>'Test Group'])->json('data.space');
        $this->postJson('/api/financial-spaces/'.$space['id'].'/assets',['asset_type'=>'land','name'=>'Land','value_minor'=>500000])->assertCreated();
        $this->postJson('/api/financial-spaces/'.$space['id'].'/obligations',['kind'=>'personal_debt','direction'=>'i_owe','amount_minor'=>100000])->assertCreated();
        $this->postJson('/api/financial-spaces/'.$space['id'].'/obligations',['kind'=>'receivable','direction'=>'owed_to_me','amount_minor'=>50000])->assertCreated();
        $this->getJson('/api/financial-spaces/'.$space['id'].'/financial-life')->assertOk()->assertJsonPath('data.net_worth_minor',450000);
    }
}
