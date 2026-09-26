'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const {createRequire} = require('node:module');
const root = path.resolve(__dirname,'..');
const ts = createRequire(path.join(root,'apps/web/package.json'))('typescript');
const source = path.join(root,'apps/web/src/lib/club-accounting/contracts.ts');
const options = {strict:true,target:ts.ScriptTarget.ES2022,module:ts.ModuleKind.CommonJS,noEmit:true};
const program = ts.createProgram([source],options);
const diagnostics = ts.getPreEmitDiagnostics(program);
if(diagnostics.length){console.error(ts.formatDiagnosticsWithColorAndContext(diagnostics,{getCurrentDirectory:()=>root,getCanonicalFileName:f=>f,getNewLine:()=> '\n'}));process.exit(1);}
const directory=fs.mkdtempSync(path.join(os.tmpdir(),'opfin-club-contracts-'));
const output=path.join(directory,'contracts.cjs');
fs.writeFileSync(output,ts.transpileModule(fs.readFileSync(source,'utf8'),{compilerOptions:{target:ts.ScriptTarget.ES2022,module:ts.ModuleKind.CommonJS}}).outputText);
const c=require(output);
let assertions=0;
function check(actual,expected){assert.deepEqual(actual,expected);assertions++;}
function rejects(fn){assert.throws(fn);assertions++;}
try {
  check(c.integerInput('1000000'),1000000);check(c.integerInput('0'),0);
  for(const invalid of ['1.5','1,000','-1','Infinity','NaN','1e3',true,null,'9007199254740992']) rejects(()=>c.integerInput(invalid));
  for(const invalid of [0,-1,1.5,'1',null,NaN])rejects(()=>c.positiveId(invalid));
  check(c.positiveId(12),12);check(c.isoDate('2024-02-29'),'2024-02-29');
  for(const invalid of ['2026-02-29','2026-02-30','2026-13-01','26-09-01','2026-9-1'])rejects(()=>c.isoDate(invalid));
  const fields=[{key:'amount_minor',label:'Amount',type:'integer',required:true,minimum:1},
    {key:'direction',label:'Direction',type:'string',required:true,enum:['credit','debit']},
    {key:'note',label:'Note',type:'string',required:false,maxLength:10}];
  check(c.validateFields(fields,{amount_minor:'12',direction:'credit',note:''}),{amount_minor:12,direction:'credit'});
  rejects(()=>c.validateFields(fields,{amount_minor:'0',direction:'credit'}));
  rejects(()=>c.validateFields(fields,{amount_minor:'12',direction:'execute'}));
  rejects(()=>c.validateFields(fields,{amount_minor:'12',direction:'credit',role:'admin'}));
  rejects(()=>c.validateFields(fields,{amount_minor:'12',direction:'credit',note:'01234567890'}));
  const array=[{key:'members',label:'Members',type:'array',required:true,items:[{key:'user_id',label:'Member',type:'integer',required:true,minimum:1}]}];
  check(c.validateFields(array,{members:[]}),{members:[]});
  check(c.validateFields(array,{members:[{user_id:'2'},{user_id:3}]}),{members:[{user_id:2},{user_id:3}]});
  rejects(()=>c.validateFields(array,{members:[{user_id:2,amount_minor:999}]}));
  rejects(()=>c.validateFields(array,{members:new Array(1001).fill({user_id:1})}));
  check(c.sameIntent({a:1,b:{c:2,d:3}},{b:{d:3,c:2},a:1}),true);
  check(c.sameIntent({amount_minor:1},{amount_minor:2}),false);
  check(c.sameIntent({members:[1,2]},{members:[2,1]}),false);
  check(c.humanLabel('amount_minor'),'amount (minor units)');
  rejects(()=>c.validateFields([{key:'x',label:'X',type:'execute',required:true}],{x:'run'}));
  console.log(`PASS: strict TypeScript contract check and ${assertions} client assertions`);
}finally{fs.rmSync(directory,{recursive:true,force:true});}
