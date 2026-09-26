'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const { createRequire } = require('node:module');
const root = path.resolve(__dirname, '..');
let ts;
try { ts = createRequire(path.join(root, 'apps/web/package.json'))('typescript'); }
catch { ts = require('typescript'); }
const source = fs.readFileSync(path.join(root, 'apps/web/src/app/club-accounting-actions.ts'), 'utf8');
const compiled = ts.transpileModule(source, {compilerOptions:{target:ts.ScriptTarget.ES2022, module:ts.ModuleKind.CommonJS}, reportDiagnostics:true});
assert.equal((compiled.diagnostics ?? []).length, 0);
const calls = [];
let replies = [];
let checks = 1;
const api = {exports:{}};
const validId = value => {if(!Number.isSafeInteger(value)||value<=0)throw new Error('Invalid ID');return value;};
const context = {
  module:api, exports:api.exports, URLSearchParams, AbortSignal,
  process:{env:{NEXT_PUBLIC_OPFIN_API_URL:'https://synthetic-api.invalid/api'}},
  require: name => name.includes('auth/session') ? {getAccessToken:async ()=>'synthetic-token'} : {positiveId:validId},
  fetch:async (url, init) => {
    calls.push({url,init});
    const reply = replies.shift();
    if(reply instanceof Error)throw reply;
    if(!reply)throw new Error('Unexpected request');
    return {ok:reply.status<400,status:reply.status,json:async()=>reply.body};
  },
};
vm.runInNewContext(compiled.outputText, context, {filename:'club-accounting-actions.cjs'});
const ok = data => ({status:200,body:{success:true,data}});
const identity = {reference:'11111111-1111-4111-8111-111111111111',content_hash:'a'.repeat(64)};
function check(value, expected) {assert.deepEqual(JSON.parse(JSON.stringify(value)),expected);checks++;}
async function main() {
  const envelope = {type:'opening',business_date:'2026-09-26',idempotency_key:'synthetic-key',payload:{members:[],balances:[],evidence_reference:'SYNTHETIC'}};
  replies=[ok({client_request:identity}),ok({instruction:{id:12,status:'pending'}})];
  check((await api.exports.clubAction('submit',2,3,envelope)).ok,true);
  check(calls.length,2);
  check(calls[0].url,'https://synthetic-api.invalid/api/financial-spaces/2/accounting/books/3/client-requests/prepare/instruction');
  check(JSON.parse(calls[0].init.body),envelope);
  check(JSON.parse(calls[1].init.body),identity);
  check(calls[0].init.redirect,'error');
  check(calls[0].init.cache,'no-store');
  check(calls[0].url.includes('synthetic-token'),false);
  calls.length=0;
  replies=[{status:422,body:{success:false,message:'Invalid input'}}];
  check((await api.exports.clubAction('submit',2,3,envelope)).status,422);
  check(calls.length,1);
  calls.length=0;
  replies=[ok({client_request:identity}),{status:422,body:{success:false,message:'Closed period'}}];
  check((await api.exports.clubAction('submit',2,3,envelope)).status,409);
  check(calls.length,2);
  calls.length=0;
  replies=[ok({client_request:identity}),new Error('Synthetic lost response')];
  const lost=await api.exports.clubAction('submit',2,3,envelope);
  check(lost.ok,false);
  check(lost.message.includes('Saved requests')||lost.message.includes('saved request'),true);
  replies=[ok({instruction:{id:12,status:'pending'}})];
  check((await api.exports.clubRecoveryAction('resume',2,3,identity)).ok,true);
  check(JSON.parse(calls.at(-1).init.body),identity);
  replies=[ok({active:false})];
  check((await api.exports.clubRecoveryAction('acknowledge',2,3,identity)).ok,true);
  check(calls.at(-1).url.endsWith('/client-requests/acknowledge'),true);
  calls.length=0;
  check((await api.exports.clubRecoveryAction('destroy',2,3,identity)).ok,false);
  check((await api.exports.clubAction('instructions',-1,3)).ok,false);
  check(calls.length,0);
  replies=[ok({requests:[],has_more:false})];
  await api.exports.clubRecoveryAction('list',2,undefined,{},3);
  check(calls.at(-1).url,'https://synthetic-api.invalid/api/accounting/saved-requests?page=3&space_id=2');
  replies=[ok({books:[]})];
  check((await api.exports.clubHistoryAction()).ok,true);
  check(calls.at(-1).url.endsWith('/accounting/my-club-books'),true);
  console.log(`PASS: ${checks} saved-request transport assertions (mock HTTP; not a Next.js build)`);
}
main().catch(error=>{console.error(error);process.exitCode=1;});
