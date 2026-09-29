<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Models\User;
use App\Services\SaccoCoreService;
use App\Services\SaccoDividendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class SaccoCoreController extends Controller {
 public function __construct(private readonly SaccoCoreService $core, private readonly SaccoDividendService $dividends){}
 public function products(Request $r,FinancialSpace $space):JsonResponse{$this->member($r,$space);return response()->json(['data'=>['products'=>DB::table('sacco_products')->where('financial_space_id',$space->id)->get()]]);}
 public function createProduct(Request $r,FinancialSpace $space):JsonResponse{$this->admin($r,$space);$v=$r->validate(['code'=>'required|string|max:40','name'=>'required|string|max:160','product_type'=>['required',Rule::in(['shares','savings','mandatory_savings','fixed_deposit','welfare','loan'])],'withdrawable'=>'sometimes|boolean','mandatory'=>'sometimes|boolean','minimum_balance_minor'=>'sometimes|integer|min:0','annual_rate_bps'=>'sometimes|integer|min:0|max:100000','rules'=>'nullable|array']);try{$p=$this->core->createProduct($space,$v);}catch(InvalidArgumentException $e){return response()->json(['message'=>$e->getMessage()],409);}return response()->json(['data'=>['product'=>$p]],201);}
 public function openAccount(Request $r,FinancialSpace $space):JsonResponse{$this->admin($r,$space);$v=$r->validate(['user_id'=>'required|integer|exists:users,id','product_id'=>'required|integer|exists:sacco_products,id']);try{$a=$this->core->openMemberAccount($space,User::findOrFail($v['user_id']),(int)$v['product_id']);}catch(InvalidArgumentException $e){return response()->json(['message'=>$e->getMessage()],409);}return response()->json(['data'=>['account'=>$a]],201);}
 public function myPosition(Request $r,FinancialSpace $space):JsonResponse{$this->member($r,$space);return response()->json(['data'=>$this->core->memberPosition($space,$r->user())]);}
 public function guarantee(Request $r,FinancialSpace $space):JsonResponse{$this->member($r,$space);$v=$r->validate(['borrower_user_id'=>'required|integer|exists:users,id','amount_minor'=>'required|integer|min:1','loan_id'=>'nullable|integer']);try{$g=$this->core->proposeGuarantee($space,$r->user(),User::findOrFail($v['borrower_user_id']),(int)$v['amount_minor'],$v['loan_id']??null);}catch(InvalidArgumentException $e){return response()->json(['message'=>$e->getMessage()],409);}return response()->json(['data'=>['guarantee'=>$g]],201);}
 public function prepareDistribution(Request $r,FinancialSpace $space):JsonResponse{$this->admin($r,$space);$v=$r->validate(['run_type'=>['required',Rule::in(['share_dividend','deposit_interest'])],'record_date'=>'required|date|before_or_equal:today','rate_bps'=>'required|integer|min:0|max:100000']);try{$run=$this->dividends->prepare($space,$r->user(),$v['run_type'],$v['record_date'],(int)$v['rate_bps']);}catch(InvalidArgumentException $e){return response()->json(['message'=>$e->getMessage()],409);}return response()->json(['data'=>['run'=>$run]],201);}
 public function approveDistribution(Request $r,FinancialSpace $space,int $run):JsonResponse{$this->admin($r,$space);$row=DB::table('sacco_dividend_runs')->where('financial_space_id',$space->id)->where('id',$run)->first();abort_if(!$row,404);try{$row=$this->dividends->approve($space,$row,$r->user());}catch(InvalidArgumentException $e){return response()->json(['message'=>$e->getMessage()],409);}return response()->json(['data'=>['run'=>$row]]);}
 private function member(Request $r,FinancialSpace $s):void{abort_unless(DB::table('financial_space_memberships')->where('financial_space_id',$s->id)->where('user_id',$r->user()->id)->where('status','active')->exists(),403);}
 private function admin(Request $r,FinancialSpace $s):void{abort_unless(DB::table('financial_space_memberships')->where('financial_space_id',$s->id)->where('user_id',$r->user()->id)->where('status','active')->whereIn('role',['owner','administrator','chairperson','treasurer','director','manager'])->exists(),403);}
}
