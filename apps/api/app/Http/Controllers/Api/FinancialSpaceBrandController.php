<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Services\FinancialSpaceBrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FinancialSpaceBrandController extends Controller {
 public function __construct(private readonly FinancialSpaceBrandService $brands){}
 public function configure(Request $r,FinancialSpace $space): JsonResponse {
  $this->admin($r,$space); $v=$r->validate(['display_name'=>'required|string|max:160','short_name'=>'nullable|string|max:60','logo_url'=>'nullable|url|max:1000','primary_colour'=>['nullable','regex:/^#[0-9A-Fa-f]{6}$/'],'accent_colour'=>['nullable','regex:/^#[0-9A-Fa-f]{6}$/'],'support_email'=>'nullable|email','support_phone'=>'nullable|string|max:40']);
  try{$brand=$this->brands->configure($space,$v);}catch(InvalidArgumentException $e){return response()->json(['message'=>$e->getMessage()],409);}
  return response()->json(['data'=>['brand'=>$brand]]);
 }
 public function domain(Request $r,FinancialSpace $space): JsonResponse {
  $this->admin($r,$space);$v=$r->validate(['hostname'=>'required|string|max:253']);
  try{$data=$this->brands->requestDomain($space,$v['hostname']);}catch(InvalidArgumentException $e){return response()->json(['message'=>$e->getMessage()],409);}
  return response()->json(['data'=>$data],201);
 }
 public function resolve(Request $r): JsonResponse {
  $v=$r->validate(['hostname'=>'required|string|max:253']);$resolved=$this->brands->resolve($v['hostname']);abort_if(!$resolved,404);
  return response()->json(['data'=>$resolved]);
 }
 private function admin(Request $r,FinancialSpace $s):void{abort_unless(DB::table('financial_space_memberships')->where('financial_space_id',$s->id)->where('user_id',$r->user()->id)->where('status','active')->whereIn('role',['owner','administrator','chairperson','director'])->exists(),403);}
}
