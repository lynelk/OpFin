<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrganisationJourneyController extends Controller
{
    public function onboarding(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->admin($request,$space);
        abort_unless(in_array($space->type,['business','sacco','investment_fund','partner'],true),422,'This Space does not use institutional onboarding.');
        $v=$request->validate(['stage'=>['sometimes',Rule::in(['profile','kyb','regulatory','products','integration','certification'])],'requirements'=>['nullable','array'],'evidence'=>['nullable','array'],'submit'=>['sometimes','boolean']]);
        $id=DB::table('organisation_onboarding_cases')->updateOrInsert(['financial_space_id'=>$space->id],['submitted_by_user_id'=>$request->user()->id,'organisation_type'=>$space->type,'stage'=>$v['stage']??'profile','status'=>($v['submit']??false)?'submitted':'draft','requirements'=>isset($v['requirements'])?json_encode($v['requirements']):null,'evidence'=>isset($v['evidence'])?json_encode($v['evidence']):null,'submitted_at'=>($v['submit']??false)?now():null,'updated_at'=>now(),'created_at'=>now()]);
        return response()->json(['data'=>['saved'=>(bool)$id,'space_type'=>$space->type]]);
    }

    public function enableEmployer(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->admin($request,$space); abort_unless($space->type==='business',422,'Employer is a Business capability.');
        DB::table('financial_space_capabilities')->updateOrInsert(['financial_space_id'=>$space->id,'capability_key'=>'employer_services'],['enabled'=>true,'status'=>'configured','configuration'=>json_encode(['privacy_boundary'=>'personal_space_private']),'updated_at'=>now(),'created_at'=>now()]);
        return response()->json(['data'=>['employer_services'=>true]]);
    }

    public function workspace(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->member($request,$space);
        $capabilities=DB::table('financial_space_capabilities')->where('financial_space_id',$space->id)->where('enabled',true)->pluck('capability_key');
        $members=DB::table('financial_space_memberships')->where('financial_space_id',$space->id)->where('status','active')->count();
        $case=DB::table('organisation_onboarding_cases')->where('financial_space_id',$space->id)->first();
        return response()->json(['data'=>['space'=>$space,'active_members'=>$members,'capabilities'=>$capabilities,'onboarding'=>$case]]);
    }
    private function member(Request $r, FinancialSpace $s): void { abort_unless(DB::table('financial_space_memberships')->where('financial_space_id',$s->id)->where('user_id',$r->user()->id)->where('status','active')->exists(),403); }
    private function admin(Request $r, FinancialSpace $s): void { abort_unless(DB::table('financial_space_memberships')->where('financial_space_id',$s->id)->where('user_id',$r->user()->id)->where('status','active')->whereIn('role',['owner','administrator','director','chairperson','treasurer'])->exists(),403); }
}
