<?php

namespace App\Services;

use App\Models\FinancialSpace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SaccoCoreService {
 public function createProduct(FinancialSpace $space,array $d): object {
  $this->sacco($space);$id=DB::table('sacco_products')->insertGetId([
   'financial_space_id'=>$space->id,'code'=>strtoupper($d['code']),'name'=>$d['name'],'product_type'=>$d['product_type'],
   'currency'=>strtoupper($space->currency?:'UGX'),'status'=>$d['status']??'active','withdrawable'=>$d['withdrawable']??false,
   'mandatory'=>$d['mandatory']??false,'minimum_balance_minor'=>$d['minimum_balance_minor']??0,'annual_rate_bps'=>$d['annual_rate_bps']??0,
   'rules'=>json_encode($d['rules']??[],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()
  ]);return DB::table('sacco_products')->find($id);
 }
 public function openMemberAccount(FinancialSpace $space,User $member,int $productId): object {
  $this->sacco($space);$this->member($space,$member);
  $p=DB::table('sacco_products')->where('financial_space_id',$space->id)->where('id',$productId)->where('status','active')->first();
  if(!$p)throw new InvalidArgumentException('Active SACCO product not found in this Space.');
  DB::table('sacco_member_accounts')->updateOrInsert(['user_id'=>$member->id,'sacco_product_id'=>$p->id],[
   'financial_space_id'=>$space->id,'account_number'=>'SAC-'.$space->id.'-'.$member->id.'-'.$p->id.'-'.Str::upper(Str::random(5)),
   'balance_minor'=>0,'held_minor'=>0,'status'=>'active','updated_at'=>now(),'created_at'=>now()
  ]);
  return DB::table('sacco_member_accounts')->where('user_id',$member->id)->where('sacco_product_id',$p->id)->first();
 }
 public function memberPosition(FinancialSpace $space,User $member): array {
  $this->sacco($space);$this->member($space,$member);
  $accounts=DB::table('sacco_member_accounts as a')->join('sacco_products as p','p.id','=','a.sacco_product_id')
   ->where('a.financial_space_id',$space->id)->where('a.user_id',$member->id)
   ->select('a.id','a.account_number','a.balance_minor','a.held_minor','a.status','p.code','p.name','p.product_type','p.withdrawable','p.mandatory')->get();
  $shares=DB::table('sacco_share_register')->where('financial_space_id',$space->id)->where('user_id',$member->id)->first();
  $guarantees=DB::table('sacco_guarantees')->where('financial_space_id',$space->id)->where('guarantor_user_id',$member->id)->whereIn('status',['proposed','accepted'])->get();
  return ['accounts'=>$accounts,'shares'=>$shares,'guarantees'=>$guarantees,
   'total_savings_minor'=>(int)$accounts->whereIn('product_type',['savings','fixed_deposit','mandatory_savings'])->sum('balance_minor'),
   'total_held_minor'=>(int)$accounts->sum('held_minor'),
   'guarantee_exposure_minor'=>(int)$guarantees->where('status','accepted')->sum('amount_minor')];
 }
 public function proposeGuarantee(FinancialSpace $space,User $guarantor,User $borrower,int $amountMinor,?int $loanId=null): object {
  $this->sacco($space);$this->member($space,$guarantor);$this->member($space,$borrower);
  if($guarantor->id===$borrower->id)throw new InvalidArgumentException('A member cannot guarantee their own borrowing.');
  $available=(int)DB::table('sacco_member_accounts')->where('financial_space_id',$space->id)->where('user_id',$guarantor->id)->sum(DB::raw('balance_minor-held_minor'));
  if($amountMinor>$available)throw new InvalidArgumentException('Guarantee exceeds the member available eligible balance.');
  $id=DB::table('sacco_guarantees')->insertGetId(['financial_space_id'=>$space->id,'guarantor_user_id'=>$guarantor->id,'borrower_user_id'=>$borrower->id,'loan_id'=>$loanId,'amount_minor'=>$amountMinor,'status'=>'proposed','created_at'=>now(),'updated_at'=>now()]);
  return DB::table('sacco_guarantees')->find($id);
 }
 private function sacco(FinancialSpace $s):void{if($s->type!=='sacco')throw new InvalidArgumentException('SACCO Core requires a SACCO Financial Space.');}
 private function member(FinancialSpace $s,User $u):void{if(!DB::table('financial_space_memberships')->where('financial_space_id',$s->id)->where('user_id',$u->id)->where('status','active')->exists())throw new InvalidArgumentException('Active SACCO membership is required.');}
}
