<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
class AuthenticateAudMon {
 public function handle(Request $r,Closure $next):Response{
  $key=trim((string)$r->header('X-AudMon-Key'));$secret=(string)$r->header('X-AudMon-Secret');
  $client=DB::table('audmon_api_clients')->where('client_key',$key)->where('status','active')->first();
  abort_unless($client && (!$client->expires_at || now()->lt($client->expires_at)) && hash_equals($client->secret_hash,hash('sha256',$secret)),401,'Invalid AudMon credentials.');
  DB::table('audmon_api_clients')->where('id',$client->id)->update(['last_used_at'=>now(),'updated_at'=>now()]);
  $r->attributes->set('audmon_client',$client);return $next($r);
 }
}
