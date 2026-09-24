<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Services\PersonalFinancialSpaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FinancialSpaceController extends Controller
{
    public function __construct(private readonly PersonalFinancialSpaceService $personalSpaces) {}

    public function index(Request $request): JsonResponse
    {
        $this->personalSpaces->ensure($request->user());
        $spaces = DB::table('financial_space_memberships as m')
            ->join('financial_spaces as s', 's.id', '=', 'm.financial_space_id')
            ->where('m.user_id', $request->user()->id)->where('m.status', 'active')->whereNull('m.deleted_at')->whereNull('s.deleted_at')
            ->select('s.id','s.public_id','s.type','s.name','s.country','s.currency','s.status','m.role')
            ->orderByRaw("CASE WHEN s.type = 'personal' THEN 0 ELSE 1 END")
            ->orderBy('s.name')
            ->orderBy('s.id')
            ->get();
        return response()->json(['data'=>['spaces'=>$spaces]]);
    }

    public function store(Request $request): JsonResponse
    {
        $v=$request->validate(['type'=>['required',Rule::in(['household','savings_group','investment_club','business','sacco','investment_fund','partner'])],'name'=>['required','string','max:160'],'country'=>['sometimes','string','size:2'],'currency'=>['sometimes','string','size:3']]);
        return DB::transaction(function() use($request,$v){
            $id=DB::table('financial_spaces')->insertGetId(['public_id'=>(string)Str::uuid(),'type'=>$v['type'],'name'=>$v['name'],'country'=>strtoupper($v['country']??'UG'),'currency'=>strtoupper($v['currency']??'UGX'),'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
            DB::table('financial_space_memberships')->insert(['financial_space_id'=>$id,'user_id'=>$request->user()->id,'role'=>'owner','status'=>'active','joined_at'=>now(),'approved_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
            return response()->json(['data'=>['space'=>FinancialSpace::findOrFail($id)]],201);
        });
    }

    public function invite(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->assertAdministrator($request,$space);
        $v=$request->validate(['phone'=>['nullable','string','max:40','required_without:email'],'email'=>['nullable','email','required_without:phone'],'role'=>['required','string','max:60']]);
        $plain=Str::random(48);
        $id=DB::table('financial_space_invitations')->insertGetId(['financial_space_id'=>$space->id,'invited_by_user_id'=>$request->user()->id,'recipient_phone'=>$v['phone']??null,'recipient_email'=>$v['email']??null,'intended_role'=>$v['role'],'token_hash'=>hash('sha256',$plain),'status'=>'pending','expires_at'=>now()->addDays(14),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['data'=>['invitation_id'=>$id,'invitation_token'=>$plain,'expires_at'=>now()->addDays(14)->toIso8601String()]],201);
    }

    public function acceptInvitation(Request $request): JsonResponse
    {
        $v=$request->validate(['token'=>['required','string']]);
        return DB::transaction(function() use($request,$v){
            $invite=DB::table('financial_space_invitations')->where('token_hash',hash('sha256',$v['token']))->where('status','pending')->lockForUpdate()->first();
            abort_if(!$invite || ($invite->expires_at && now()->greaterThan($invite->expires_at)),422,'Invitation is invalid or expired.');
            DB::table('financial_space_memberships')->updateOrInsert(['financial_space_id'=>$invite->financial_space_id,'user_id'=>$request->user()->id],['role'=>$invite->intended_role,'status'=>'active','joined_at'=>now(),'approved_at'=>now(),'updated_at'=>now(),'created_at'=>now()]);
            DB::table('financial_space_invitations')->where('id',$invite->id)->update(['status'=>'accepted','accepted_at'=>now(),'accepted_by_user_id'=>$request->user()->id,'updated_at'=>now()]);
            return response()->json(['data'=>['financial_space_id'=>$invite->financial_space_id,'joined'=>true]]);
        });
    }

    public function members(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->assertMember($request,$space);
        $members=DB::table('financial_space_memberships')->where('financial_space_id',$space->id)->whereNull('deleted_at')->orderBy('id')->get();
        return response()->json(['data'=>['members'=>$members]]);
    }

    public function enableCapability(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->assertAdministrator($request,$space);
        $v=$request->validate(['capability_key'=>['required','string','max:100'],'enabled'=>['required','boolean'],'configuration'=>['nullable','array']]);
        DB::table('financial_space_capabilities')->updateOrInsert(['financial_space_id'=>$space->id,'capability_key'=>$v['capability_key']],['enabled'=>$v['enabled'],'status'=>'configured','configuration'=>isset($v['configuration'])?json_encode($v['configuration']):null,'updated_at'=>now(),'created_at'=>now()]);
        return response()->json(['data'=>['updated'=>true]]);
    }

    private function assertMember(Request $r, FinancialSpace $s): void { abort_unless(DB::table('financial_space_memberships')->where('financial_space_id',$s->id)->where('user_id',$r->user()->id)->where('status','active')->whereNull('deleted_at')->exists(),403); }
    private function assertAdministrator(Request $r, FinancialSpace $s): void { abort_unless(DB::table('financial_space_memberships')->where('financial_space_id',$s->id)->where('user_id',$r->user()->id)->where('status','active')->whereNull('deleted_at')->whereIn('role',['owner','administrator','chairperson','treasurer','director'])->exists(),403); }
}
