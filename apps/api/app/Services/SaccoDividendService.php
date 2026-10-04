<?php
namespace App\Services;
use App\Models\FinancialSpace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
class SaccoDividendService {
 public function prepare(FinancialSpace $space,User $maker,string $type,string $recordDate,int $rateBps):object{
  if($space->type!=='sacco')throw new InvalidArgumentException('Distribution runs require a SACCO Space.');
  if(!in_array($type,['share_dividend','deposit_interest'],true))throw new InvalidArgumentException('Unsupported SACCO distribution type.');
  return DB::transaction(function()use($space,$maker,$type,$recordDate,$rateBps){
   $id=DB::table('sacco_dividend_runs')->insertGetId(['public_id'=>(string)Str::uuid(),'financial_space_id'=>$space->id,'run_type'=>$type,'record_date'=>$recordDate,'rate_bps'=>$rateBps,'gross_amount_minor'=>0,'status'=>'draft','prepared_by_user_id'=>$maker->id,'created_at'=>now(),'updated_at'=>now()]);
   $bases=$type==='share_dividend'
    ? DB::table('sacco_share_register')->where('financial_space_id',$space->id)->select('user_id','paid_up_capital_minor as basis_minor')->get()
    : DB::table('sacco_member_accounts as a')->join('sacco_products as p','p.id','=','a.sacco_product_id')->where('a.financial_space_id',$space->id)->whereIn('p.product_type',['savings','mandatory_savings','fixed_deposit'])->select('a.user_id',DB::raw('SUM(a.balance_minor) as basis_minor'))->groupBy('a.user_id')->get();
   $gross=0;foreach($bases as $b){$amount=intdiv(((int)$b->basis_minor*$rateBps)+9999,10000);if($amount<=0)continue;$gross+=$amount;DB::table('sacco_dividend_allocations')->insert(['sacco_dividend_run_id'=>$id,'user_id'=>$b->user_id,'basis_minor'=>$b->basis_minor,'gross_amount_minor'=>$amount,'withholding_minor'=>0,'net_amount_minor'=>$amount,'status'=>'allocated','created_at'=>now(),'updated_at'=>now()]);}
   DB::table('sacco_dividend_runs')->where('id',$id)->update(['gross_amount_minor'=>$gross,'updated_at'=>now()]);return DB::table('sacco_dividend_runs')->find($id);
  });
 }
 public function approve(FinancialSpace $space,object $run,User $checker):object{
  if((int)$run->prepared_by_user_id===(int)$checker->id)throw new InvalidArgumentException('Maker and checker must be different users.');
  if((int)$run->financial_space_id!==(int)$space->id||$run->status!=='draft')throw new InvalidArgumentException('Only a draft run in this SACCO can be approved.');
  DB::table('sacco_dividend_runs')->where('id',$run->id)->update(['status'=>'approved','approved_by_user_id'=>$checker->id,'approved_at'=>now(),'updated_at'=>now()]);
  return DB::table('sacco_dividend_runs')->find($run->id);
 }
}
