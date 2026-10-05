<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AudMonExportController extends Controller {
 public function capabilities(Request $r):JsonResponse{return response()->json(['data'=>['version'=>'v1','resources'=>['audit-events','reconciliation-exceptions','revenue-events','financial-space-controls'],'pagination'=>'cursor:id','money'=>'integer minor units','timestamps'=>'ISO-8601 UTC','mutations'=>false]]);}
 public function auditEvents(Request $r):JsonResponse{
  $q=DB::table('audit_logs')->where('id','>',max(0,(int)$r->query('after_id',0)))->orderBy('id')->limit(min(500,max(1,(int)$r->query('limit',100))));
  $rows=$q->get(['id','event','actor_type','actor_id','subject_type','subject_id','metadata','created_at']);
  return $this->page($rows);
 }
 public function reconciliation(Request $r):JsonResponse{
  $q=DB::table('reconciliation_items')->where('id','>',max(0,(int)$r->query('after_id',0)))->orderBy('id')->limit(min(500,max(1,(int)$r->query('limit',100))));
  return $this->page($q->get());
 }
 public function revenue(Request $r):JsonResponse{
  $q=DB::table('revenue_events')->where('id','>',max(0,(int)$r->query('after_id',0)))->orderBy('id')->limit(min(500,max(1,(int)$r->query('limit',100))));
  return $this->page($q->get(['id','public_id','financial_space_id','partner_id','partner_product_id','commercial_agreement_id','event_type','source_type','source_reference','gross_amount_minor','opfin_amount_minor','partner_amount_minor','tax_amount_minor','currency','status','cpay_reference','reconciliation_reference','occurred_at','settled_at','created_at']));
 }
 public function controls(Request $r):JsonResponse{
  $client=$r->attributes->get('audmon_client');$allowed=json_decode((string)($client->financial_space_ids??'[]'),true)?:[];
  $q=DB::table('financial_spaces as s')->leftJoin('financial_space_domains as d','d.financial_space_id','=','s.id')->leftJoin('financial_space_brands as b','b.financial_space_id','=','s.id')
   ->select('s.id','s.public_id','s.type','s.name','s.status','s.country','s.currency','d.hostname','d.status as domain_status','b.display_name');
  if($allowed)$q->whereIn('s.id',array_map('intval',$allowed));
  return response()->json(['data'=>['spaces'=>$q->get(),'generated_at'=>now()->utc()->toIso8601String()]]);
 }
 private function page($rows):JsonResponse{$last=$rows->last();return response()->json(['data'=>['items'=>$rows,'next_after_id'=>$last?->id,'has_more'=>$rows->count()>=500,'generated_at'=>now()->utc()->toIso8601String()]]);}
}
