<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialSpaceCommercialOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_space_action_fails_closed_without_explicit_pricing(): void
    {
        $user=User::factory()->create(); Sanctum::actingAs($user);
        $space=$this->space($user,'investment_club','owner');

        $this->postJson("/api/financial-spaces/{$space}/operations/actions",[
            'action_type'=>'contribution','direction'=>'collection','amount_minor'=>100000,
            'counterparty_phone'=>'256700000001','idempotency_key'=>'pricing-required-1',
        ])->assertStatus(409)->assertJsonFragment(['message'=>'No active commercial pricing rule exists for contribution. Product/action activation is blocked until pricing is explicit, including an intentional zero-fee rule.']);
    }

    public function test_zero_fee_is_explicit_and_maker_checker_is_enforced(): void
    {
        $maker=User::factory()->create(); $checker=User::factory()->create();
        $space=$this->space($maker,'investment_club','treasurer');
        DB::table('financial_space_memberships')->insert([
            'financial_space_id'=>$space,'user_id'=>$checker->id,'role'=>'chairperson','status'=>'active',
            'joined_at'=>now(),'approved_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('commercial_pricing_rules')->insert([
            'code'=>'CLUB-CONTRIBUTION-ZERO','financial_space_type'=>'investment_club','event_type'=>'contribution',
            'charge_basis'=>'flat','flat_amount_minor'=>0,'rate_bps'=>0,'currency'=>'UGX','payer'=>'customer','status'=>'active',
            'effective_from'=>now()->subMinute(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        Sanctum::actingAs($maker);
        $created=$this->postJson("/api/financial-spaces/{$space}/operations/actions",[
            'action_type'=>'contribution','direction'=>'collection','amount_minor'=>100000,
            'counterparty_phone'=>'256700000001','idempotency_key'=>'explicit-zero-1',
        ])->assertCreated()->json('data.action');
        $this->assertSame(0,(int)$created['platform_fee_minor']);
        $this->postJson("/api/financial-spaces/{$space}/operations/actions/{$created['id']}/approve")->assertStatus(409)
            ->assertJsonFragment(['message'=>'Maker and checker must be different users.']);
    }

    private function space(User $user,string $type,string $role): int
    {
        $id=DB::table('financial_spaces')->insertGetId([
            'public_id'=>(string)\Illuminate\Support\Str::uuid(),'type'=>$type,'name'=>'Test Space','country'=>'UG','currency'=>'UGX','status'=>'active','created_at'=>now(),'updated_at'=>now()
        ]);
        DB::table('financial_space_memberships')->insert([
            'financial_space_id'=>$id,'user_id'=>$user->id,'role'=>$role,'status'=>'active','joined_at'=>now(),'approved_at'=>now(),'created_at'=>now(),'updated_at'=>now()
        ]);
        return $id;
    }
}
