<?php

namespace Tests\Feature;

use App\Models\LegalProductPassport;
use App\Models\ShariaApproval;
use App\Models\FinancialProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntegratedFinancingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sharia_only_intent_never_returns_conventional_product(): void
    {
        $user = User::factory()->create();
        $space = $this->personalSpace($user);
        $passport = $this->passport();
        $approval = ShariaApproval::create([
            'reference'=>(string) Str::uuid(),'authority_name'=>'Test Sharia Authority','approval_reference'=>'TEST-SHARIA-1',
            'scope_type'=>'product','scope_reference'=>'MUR-1','status'=>'approved','effective_from'=>now()->subDay(),'expires_at'=>now()->addMonth(),
        ]);
        $this->product('CONV-1','CONVENTIONAL',$passport->id,null);
        $islamic=$this->product('MUR-1','ISLAMIC',$passport->id,$approval->id);

        Sanctum::actingAs($user);
        $intent=$this->postJson('/api/financial-intents',[
            'financial_space_id'=>$space,'need_type'=>'merchant_inventory','principles_preference'=>'SHARIA_ONLY','amount_minor'=>100000,
        ])->assertCreated()->json('data');

        $this->postJson('/api/product-matches',['financial_intent_id'=>$intent['id']])
            ->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$islamic->id);
    }

    public function test_expired_sharia_approval_blocks_islamic_product(): void
    {
        $user=User::factory()->create(); $space=$this->personalSpace($user); $passport=$this->passport();
        $approval=ShariaApproval::create([
            'reference'=>(string) Str::uuid(),'authority_name'=>'Test Authority','approval_reference'=>'EXPIRED-1',
            'scope_type'=>'product','scope_reference'=>'MUR-X','status'=>'approved','effective_from'=>now()->subMonth(),'expires_at'=>now()->subDay(),
        ]);
        $this->product('MUR-X','ISLAMIC',$passport->id,$approval->id);
        Sanctum::actingAs($user);
        $intent=$this->postJson('/api/financial-intents',['financial_space_id'=>$space,'need_type'=>'inventory','principles_preference'=>'SHARIA_ONLY'])->assertCreated()->json('data');
        $this->postJson('/api/product-matches',['financial_intent_id'=>$intent['id']])->assertOk()->assertJsonCount(0,'data');
    }

    public function test_product_without_approved_legal_passport_is_not_matchable(): void
    {
        $user=User::factory()->create(); $space=$this->personalSpace($user);
        $passport=$this->passport('draft');
        $this->product('CONV-DRAFT','CONVENTIONAL',$passport->id,null);
        Sanctum::actingAs($user);
        $intent=$this->postJson('/api/financial-intents',['financial_space_id'=>$space,'need_type'=>'cashflow'])->assertCreated()->json('data');
        $this->postJson('/api/product-matches',['financial_intent_id'=>$intent['id']])->assertOk()->assertJsonCount(0,'data');
    }

    public function test_financial_space_isolation_is_enforced(): void
    {
        $owner=User::factory()->create(); $other=User::factory()->create(); $space=$this->personalSpace($owner);
        Sanctum::actingAs($other);
        $this->postJson('/api/financial-intents',['financial_space_id'=>$space,'need_type'=>'cashflow'])->assertUnprocessable();
    }

    private function personalSpace(User $user): int
    {
        $id=DB::table('financial_spaces')->insertGetId([
            'public_id'=>(string) Str::uuid(),'type'=>'personal','name'=>'Personal','status'=>'active','created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('financial_space_memberships')->insert([
            'financial_space_id'=>$id,'user_id'=>$user->id,'role'=>'owner','status'=>'active','joined_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $id;
    }

    private function passport(string $status='approved'): LegalProductPassport
    {
        return LegalProductPassport::create([
            'reference'=>(string) Str::uuid(),'jurisdiction'=>'UG','regulated_activity'=>'financing','booking_entity'=>'Test regulated partner',
            'status'=>$status,'effective_from'=>now()->subDay(),'approved_at'=>$status==='approved'?now():null,
        ]);
    }

    private function product(string $code,string $rail,int $passportId,?int $shariaId): FinancialProduct
    {
        return FinancialProduct::create([
            'reference'=>(string) Str::uuid(),'code'=>$code,'version'=>1,'name'=>$code,'rail'=>$rail,'family'=>'finance',
            'contract_type'=>$rail==='ISLAMIC'?'MURABAHA':'CREDIT','jurisdiction'=>'UG','currency'=>'UGX',
            'legal_product_passport_id'=>$passportId,'sharia_approval_id'=>$shariaId,'status'=>'live','effective_from'=>now()->subDay(),
        ]);
    }
}
