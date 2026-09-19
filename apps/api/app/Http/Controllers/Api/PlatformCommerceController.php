<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlatformCommerceController extends Controller
{
    public function catalogue(Request $request): JsonResponse
    {
        $products=DB::table('partner_products as pp')->join('partners as p','p.id','=','pp.partner_id')
            ->where('pp.status','active')->where('p.status','active')->where('pp.country',strtoupper((string)$request->query('country','UG')))
            ->select('pp.id','pp.code','pp.name','pp.product_type','pp.currency','pp.eligibility_rules','pp.pricing','pp.disclosures','p.name as provider_name','p.partner_type')->get();
        return response()->json(['data'=>['products'=>$products]]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(['data'=>['plans'=>DB::table('opfin_plans')->where('status','active')->orderBy('price_minor')->get()]]);
    }

    public function subscribe(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->assertAdministrator($request,$space);
        $v=$request->validate(['plan_code'=>['required','string']]);
        $plan=DB::table('opfin_plans')->where('code',$v['plan_code'])->where('status','active')->first(); abort_if(!$plan,422,'Plan is unavailable.');
        DB::transaction(function() use($request,$space,$plan){
            DB::table('financial_space_entitlements')->where('financial_space_id',$space->id)->update(['status'=>'replaced','ends_at'=>now(),'updated_at'=>now()]);
            $keys=(array)(json_decode($plan->metadata??'{}',true)['entitlements']??[]);
            foreach($keys as $key) DB::table('financial_space_entitlements')->updateOrInsert(['financial_space_id'=>$space->id,'entitlement_key'=>$key],['opfin_plan_id'=>$plan->id,'status'=>'active','starts_at'=>now(),'updated_at'=>now(),'created_at'=>now()]);
            if((int)$plan->price_minor>0) $this->revenue($space->id,$request->user()->id,null,null,null,'subscription','opfin_plan',(string)$plan->id,(int)$plan->price_minor,(int)$plan->price_minor,0,0,$plan->currency);
        });
        return response()->json(['data'=>['subscribed'=>true,'plan'=>$plan->code]]);
    }

    public function recordRevenue(Request $request): JsonResponse
    {
        abort_unless(in_array($request->user()->role,['platform_admin','operations'],true),403);
        $v=$request->validate(['financial_space_id'=>['nullable','integer'],'partner_id'=>['nullable','integer'],'partner_product_id'=>['nullable','integer'],'commercial_agreement_id'=>['nullable','integer'],'event_type'=>['required',Rule::in(['subscription','commission','revenue_share','transaction','platform_fee','api_fee','servicing'])],'source_type'=>['required','string'],'source_reference'=>['required','string'],'gross_amount_minor'=>['required','integer','min:0'],'opfin_amount_minor'=>['required','integer'],'partner_amount_minor'=>['sometimes','integer'],'tax_amount_minor'=>['sometimes','integer'],'currency'=>['sometimes','string','size:3'],'cpay_reference'=>['nullable','string']]);
        $id=$this->revenue($v['financial_space_id']??null,$request->user()->id,$v['partner_id']??null,$v['partner_product_id']??null,$v['commercial_agreement_id']??null,$v['event_type'],$v['source_type'],$v['source_reference'],$v['gross_amount_minor'],$v['opfin_amount_minor'],$v['partner_amount_minor']??0,$v['tax_amount_minor']??0,strtoupper($v['currency']??'UGX'),$v['cpay_reference']??null);
        return response()->json(['data'=>['revenue_event_id'=>$id]],201);
    }

    public function reconcileRevenue(Request $request, int $event): JsonResponse
    {
        abort_unless(in_array($request->user()->role,['platform_admin','operations'],true),403);
        $v=$request->validate(['cpay_reference'=>['required','string'],'reconciliation_reference'=>['required','string'],'status'=>['required',Rule::in(['settled','reconciled'])]]);
        DB::table('revenue_events')->where('id',$event)->update(['cpay_reference'=>$v['cpay_reference'],'reconciliation_reference'=>$v['reconciliation_reference'],'status'=>$v['status'],'settled_at'=>now(),'updated_at'=>now()]);
        return response()->json(['data'=>['reconciled'=>true]]);
    }

    private function revenue($space,$user,$partner,$product,$agreement,$type,$sourceType,$sourceRef,$gross,$opfin,$partnerAmount,$tax,$currency,$cpay=null): int
    {
        $existing=DB::table('revenue_events')->where(['event_type'=>$type,'source_type'=>$sourceType,'source_reference'=>$sourceRef])->first(); if($existing) return $existing->id;
        return DB::table('revenue_events')->insertGetId(['public_id'=>(string)Str::uuid(),'financial_space_id'=>$space,'user_id'=>$user,'partner_id'=>$partner,'partner_product_id'=>$product,'commercial_agreement_id'=>$agreement,'event_type'=>$type,'source_type'=>$sourceType,'source_reference'=>$sourceRef,'gross_amount_minor'=>$gross,'opfin_amount_minor'=>$opfin,'partner_amount_minor'=>$partnerAmount,'tax_amount_minor'=>$tax,'currency'=>$currency,'status'=>'accrued','cpay_reference'=>$cpay,'occurred_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
    }
    private function assertAdministrator(Request $r, FinancialSpace $s): void { abort_unless(DB::table('financial_space_memberships')->where('financial_space_id',$s->id)->where('user_id',$r->user()->id)->where('status','active')->whereIn('role',['owner','administrator','chairperson','treasurer','director'])->exists(),403); }
}
