<?php

namespace App\Services;

use App\Models\FinancialSpace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FinancialSpaceBrandService
{
 public function configure(FinancialSpace $space,array $data): object {
  if(!in_array($space->type,['sacco','investment_club'],true)) throw new InvalidArgumentException('White-labelled access is currently limited to SACCO and Investment Club Spaces.');
  DB::table('financial_space_brands')->updateOrInsert(['financial_space_id'=>$space->id],[
   'display_name'=>$data['display_name'],'short_name'=>$data['short_name']??null,'logo_url'=>$data['logo_url']??null,
   'primary_colour'=>$data['primary_colour']??null,'accent_colour'=>$data['accent_colour']??null,
   'support_email'=>$data['support_email']??null,'support_phone'=>$data['support_phone']??null,'updated_at'=>now(),'created_at'=>now()
  ]);
  return DB::table('financial_space_brands')->where('financial_space_id',$space->id)->first();
 }
 public function requestDomain(FinancialSpace $space,string $hostname): array {
  $host=strtolower(trim($hostname,'. '));
  if(!preg_match('/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',$host)) throw new InvalidArgumentException('Enter a valid fully qualified domain name.');
  $plain='opfin-domain-verification='.Str::random(48);
  $id=DB::table('financial_space_domains')->insertGetId([
   'financial_space_id'=>$space->id,'hostname'=>$host,'status'=>'pending_verification','verification_token_hash'=>hash('sha256',$plain),
   'metadata'=>json_encode(['verification_method'=>'dns_txt'],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()
  ]);
  return ['domain'=>DB::table('financial_space_domains')->find($id),'dns_txt_value'=>$plain];
 }
 public function resolve(string $hostname): ?array {
  $domain=DB::table('financial_space_domains')->where('hostname',strtolower($hostname))->where('status','active')->first();
  if(!$domain) return null;
  $brand=DB::table('financial_space_brands')->where('financial_space_id',$domain->financial_space_id)->first();
  return ['financial_space_id'=>(int)$domain->financial_space_id,'hostname'=>$domain->hostname,'brand'=>$brand];
 }
}
