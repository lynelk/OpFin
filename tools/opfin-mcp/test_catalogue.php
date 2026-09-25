<?php

require __DIR__.'/../../apps/api/app/Support/ApiDocumentation/ContractCatalogue.php';

use App\Support\ApiDocumentation\ContractCatalogue;

$count = 0;
function check(bool $condition, string $name): void {
    global $count;
    if (! $condition) { throw new RuntimeException('Failed: '.$name); }
    $count++;
}
function rejected(callable $call, string $name): void {
    try { $call(); } catch (InvalidArgumentException) { check(true, $name); return; }
    throw new RuntimeException('Expected rejection: '.$name);
}
$contracts = require __DIR__.'/../../apps/api/resources/developer-guides/contracts.php';
$routes = [];
foreach ($contracts as $key => $contract) {
    [$method, $path] = explode(' ', $key, 2);
    $routes[] = ['method' => $method, 'path' => $path, 'middleware' => $contract['publication'] === 'public' ? [] : ['auth:sanctum'], 'source_digest' => str_repeat('a',64)];
}
$routes[] = ['method'=>'GET','path'=>'/api/profile','middleware'=>['auth:sanctum'],'source_digest'=>'profile-v1'];
$routes[] = ['method'=>'POST','path'=>'/api/loans/{loan}/repay','middleware'=>['auth:sanctum'],'source_digest'=>'repay-v1'];
$routes[] = ['method'=>'GET','path'=>'/api/admin/foundation-check','middleware'=>['auth:sanctum','role:platform_admin,operations'],'source_digest'=>'admin-v1'];
$routes[] = ['method'=>'GET','path'=>'/api/admin/support','middleware'=>['auth:sanctum','role:platform_admin,support'],'source_digest'=>'support-v1'];
$routes[] = ['method'=>'GET','path'=>'/api/partner/secret','middleware'=>['auth:sanctum','role:partner_api','role:operations'],'source_digest'=>'mixed-v1'];
$routes[] = ['method'=>'POST','path'=>'/api/webhooks/cpay','middleware'=>[],'source_digest'=>'hook-v1'];
$routes[] = ['method'=>'GET','path'=>'/api/demo/account','middleware'=>[]];
$guide = ['start'=>['id'=>'start','title'=>'Start here','text'=>'Never share a token.','audience'=>'novice','sha256'=>hash('sha256','Never share a token.')]];
$meta=['contract_fingerprint'=>str_repeat('b',64),'runtime_fingerprint'=>str_repeat('c',64),'environment'=>'testing','source_revision'=>null];
$catalogue = new ContractCatalogue($routes,$contracts,$guide,$meta);
check(count($catalogue->visible(null))===4,'public allow-list');
check($catalogue->coverage(null,true)['registered']===14,'non-demo registered count');
check($catalogue->coverage(null,true)['documented']===8,'reviewed count');
check($catalogue->coverage(null,true)['registration_only']===6,'honest gaps');
check(!$catalogue->coverage(null,true)['complete'],'not falsely complete');
check($catalogue->coverage(null,true)['definition_errors']===[],'all definition routes exist');
check(count($catalogue->search('customer','profile')['items'])===1,'all-word search');
check(count($catalogue->search('customer','repay')['items'])===1,'path search');
check($catalogue->search('customer','profile unknown')['total']===0,'AND word matching');
check($catalogue->search('customer','',1,1)['has_more'],'pagination');
check($catalogue->search('customer','',2,1)['page']===2,'page cursor');
check($catalogue->search('customer','foundation')['total']===0,'customer cannot see admin');
check($catalogue->search('operations','foundation')['total']===1,'authorised admin discovery');
check($catalogue->search('support','admin support')['total']===1,'support route with explicit role');
check($catalogue->search('partner_api','secret')['total']===0,'ANDed role groups');
check($catalogue->search('customer','webhooks')['total']===0,'unclassified callback hidden');
check($catalogue->guide('../config')===null,'no guide path resolution');
check(count($catalogue->guides('token'))===1,'guide full-text search');
check(count($catalogue->guides('token missing'))===0,'guide AND search');
$spec=$catalogue->openApi('platform_admin');
check($spec['openapi']==='3.1.1','OAS version');
check(!isset($spec['paths']['/api/loans/{loan}/repay']),'unreviewed excluded from executing specs');
check(isset($spec['paths']['/api/developer/catalogue']),'documented included');
check($spec['paths']['/api/developer/catalogue']['get']['security']===[['sanctumBearer'=>[]]],'security explicit');
check($spec['paths']['/api/developer/public']['get']['security']===[],'known public security');
check($catalogue->diff($catalogue->snapshot())===[],'stable baseline');
$edited=$routes;$edited[8]['source_digest']='profile-v2';
$changed=(new ContractCatalogue($edited,$contracts,$guide,$meta))->diff($catalogue->snapshot());
check(count($changed)===1 && $changed[0]['review_required'],'controller drift detected');
$removed=array_filter($routes,fn($r)=>$r['path']!=='/api/profile');
$changes=(new ContractCatalogue($removed,$contracts,$guide,$meta))->diff($catalogue->snapshot());
check(count($changes)===1 && $changes[0]['kind']==='removed','removal detected');
rejected(fn()=>new ContractCatalogue([],$contracts,$guide,$meta),'empty export fails');
rejected(fn()=>new ContractCatalogue(array_merge($routes,[$routes[0]]),$contracts,$guide,$meta),'duplicate route fails');
rejected(fn()=>$catalogue->search('customer','',0,20),'invalid pagination');
rejected(fn()=>$catalogue->search('customer',str_repeat('x',161)),'bounded query');
rejected(fn()=>$catalogue->diff([]),'invalid baseline');
$bad=$contracts;$bad['GET /api/developer/public']['operation_id']=$bad['GET /api/developer/manifest']['operation_id'];
rejected(fn()=>new ContractCatalogue($routes,$bad,$guide,$meta),'duplicate operation id');
$bad=$contracts;$bad['GET /api/developer/public']['responses']=[];
rejected(fn()=>new ContractCatalogue($routes,$bad,$guide,$meta),'empty response contract rejected');
file_put_contents(sys_get_temp_dir().'/opfin-reviewed-openapi.fixture.json',json_encode($spec,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "CATALOGUE_STANDALONE: {$count} assertions passed\n";
