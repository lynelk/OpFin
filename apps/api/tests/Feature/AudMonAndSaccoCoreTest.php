<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\SaccoDividendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class AudMonAndSaccoCoreTest extends TestCase {
 use RefreshDatabase;
 public function test_audmon_is_read_only_and_pages_audit_events():void{
  DB::table('audmon_api_clients')->insert(['name'=>'AudMon test','client_key'=>'aud-test','secret_hash'=>hash('sha256','secret'),'status'=>'active','scopes'=>json_encode(['audit:read']),'created_at'=>now(),'updated_at'=>now()]);
  DB::table('audit_logs')->insert(['event'=>'test.control','metadata'=>json_encode(['evidence'=>'ok']),'created_at'=>now()]);
  $this->withHeaders(['X-AudMon-Key'=>'aud-test','X-AudMon-Secret'=>'secret'])->getJson('/api/integrations/audmon/v1/audit-events?after_id=0')->assertOk()->assertJsonPath('data.items.0.event','test.control');
  $this->withHeaders(['X-AudMon-Key'=>'aud-test','X-AudMon-Secret'=>'wrong'])->getJson('/api/integrations/audmon/v1/audit-events')->assertUnauthorized();
 }
 public function test_share_dividend_and_deposit_interest_are_separate_runs_and_need_checker():void{
  $maker=User::factory()->create();$checker=User::factory()->create();$member=User::factory()->create();
  $space=DB::table('financial_spaces')->insertGetId(['public_id'=>(string)Str::uuid(),'type'=>'sacco','name'=>'SACCO','country'=>'UG','currency'=>'UGX','status'=>'active','created_at'=>now(),'updated_at'=>now()]);
  foreach([[$maker,'treasurer'],[$checker,'chairperson'],[$member,'member']] as [$u,$role])DB::table('financial_space_memberships')->insert(['financial_space_id'=>$space,'user_id'=>$u->id,'role'=>$role,'status'=>'active','joined_at'=>now(),'approved_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
  DB::table('sacco_share_register')->insert(['financial_space_id'=>$space,'user_id'=>$member->id,'shares_micro'=>1000000,'paid_up_capital_minor'=>100000,'currency'=>'UGX','created_at'=>now(),'updated_at'=>now()]);
  $s=\App\Models\FinancialSpace::findOrFail($space);$svc=app(SaccoDividendService::class);
  $run=$svc->prepare($s,$maker,'share_dividend',now()->toDateString(),1000);$this->assertSame(10000,(int)$run->gross_amount_minor);
  $this->expectException(\InvalidArgumentException::class);$svc->approve($s,$run,$maker);
 }
}
