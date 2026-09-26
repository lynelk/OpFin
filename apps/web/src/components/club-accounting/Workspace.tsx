'use client';

import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';
import { clubAction } from '@/app/club-accounting-actions';
import { humanLabel, integerInput, isoDate, validateFields, type Book, type ClubAction, type Field, type Instruction, type Json, type Operation, type RecordValue } from '@/lib/club-accounting/contracts';
import styles from './Workspace.module.css';

class ClubRequestFailure extends Error { constructor(message:string,public status?:number){super(message);} }
function rows(value: Json | undefined): RecordValue[] {
  return Array.isArray(value) ? value.filter((v): v is RecordValue => v !== null && typeof v === 'object' && !Array.isArray(v)) : [];
}
function text(value: Json | undefined): string { return value === null || value === undefined ? 'Not recorded' : String(value); }
function today(): string { return new Intl.DateTimeFormat('en-CA',{timeZone:'Africa/Kampala',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date()); }
function emptyFields(fields: Field[]): RecordValue { return Object.fromEntries(fields.map(f=>[f.key,f.type==='array'?[]:''])); }

function DataView({ value, label='Record' }: { value: Json; label?: string }) {
  if(value===null) return <span>Not recorded</span>;
  if(typeof value!=='object') return <span>{typeof value==='boolean'?(value?'Yes':'No'):String(value)}</span>;
  if(Array.isArray(value)) return value.length ? <div>{value.map((item,index)=><details key={index} open={value.length<4}><summary>{label} {index+1}</summary><DataView value={item} label={label}/></details>)}</div> : <p>No records for this selection.</p>;
  return <dl>{Object.entries(value).map(([key,item])=><div key={key} style={{marginBottom:12}}><dt><strong>{humanLabel(key)}</strong></dt><dd style={{marginLeft:12,overflowWrap:'anywhere'}}><DataView value={item} label={humanLabel(key)}/></dd></div>)}</dl>;
}

function FieldEditor({ field, value, set, catalogue, disabled, prefix }: {
  field: Field; value: Json | undefined; set:(value:Json)=>void; catalogue:RecordValue; disabled:boolean; prefix:string;
}) {
  const id=prefix+'-'+field.key;
  if(field.type==='array') {
    const entries=Array.isArray(value)?value:[];
    return <fieldset disabled={disabled} style={{marginBlock:16,padding:16}}><legend>{field.label}</legend>
      {entries.map((entry,index)=><div key={index} style={{padding:12,borderBottom:'1px solid currentColor'}}>
        <h4>{field.label}: row {index+1}</h4>
        {field.items_type==='integer' ? <FieldEditor field={{...field,type:'integer',key:'member',required:true}} value={entry}
          set={next=>set(entries.map((item,i)=>i===index?next:item))} catalogue={catalogue} disabled={disabled} prefix={id+'-'+index}/> :
          (field.items??[]).map(child=><FieldEditor key={child.key} field={child}
            value={entry!==null&&typeof entry==='object'&&!Array.isArray(entry)?entry[child.key]:undefined}
            set={next=>set(entries.map((item,i)=>i===index?{...(item!==null&&typeof item==='object'&&!Array.isArray(item)?item:{}),[child.key]:next}:item))}
            catalogue={catalogue} disabled={disabled} prefix={id+'-'+index}/>)}
        <button type="button" className="button secondary" onClick={()=>set(entries.filter((_,i)=>i!==index))}>Remove row {index+1}</button>
      </div>)}
      <button type="button" className="button secondary" disabled={disabled||entries.length>=1000}
        onClick={()=>set([...entries,field.items_type==='integer'?'':emptyFields(field.items??[])])}>Add {field.label.toLowerCase()} row</button>
    </fieldset>;
  }
  const options=field.enum?.map(v=>({value:v,label:humanLabel(v)})) ?? (field.source?rows(catalogue[field.source]).map(row=>({
    value:text(field.key==='account_code'?row.code:row.id??row.treasury_account_id??row.user_id),
    label:text(row.name??row.account_name??row.reference??row.code??row.user_id??row.id),
  })):[]);
  const shown=typeof value==='string'||typeof value==='number'?String(value):'';
  return <div className="field" style={{marginBlock:12}}><label htmlFor={id}>{field.label}{field.required?' *':' (optional)'}</label>
    {options.length ? <select id={id} value={shown} required={field.required} disabled={disabled} onChange={event=>set(event.target.value)}>
      <option value="">Choose {field.label.toLowerCase()}</option>{options.map((option,index)=><option key={option.value+'-'+index} value={option.value}>{option.label} · {option.value}</option>)}
    </select> : <input id={id} value={shown} required={field.required} disabled={disabled}
      type={field.type==='date'?'date':field.type==='month'?'month':'text'} inputMode={field.type==='integer'?'numeric':undefined}
      maxLength={field.maxLength??1000} onChange={event=>set(event.target.value)}/>}
  </div>;
}

export default function ClubAccountingWorkspace({spaceId}:{spaceId:number}) {
  const [books,setBooks]=useState<Book[]>([]), [bookId,setBookId]=useState<number|null>(null);
  const [operations,setOperations]=useState<Operation[]>([]), [operation,setOperation]=useState<Operation|null>(null);
  const [catalogue,setCatalogue]=useState<RecordValue>({}), [form,setForm]=useState<RecordValue>({});
  const [canMake,setCanMake]=useState(false), [canCheck,setCanCheck]=useState(false), [userId,setUserId]=useState<number|null>(null);
  const [message,setMessage]=useState('Loading your authorised club books…'), [busy,setBusy]=useState(false);
  const [date,setDate]=useState(today()), [draftKey,setDraftKey]=useState(''), [frozen,setFrozen]=useState(false);
  const [instruction,setInstruction]=useState<Instruction|null>(null), [preview,setPreview]=useState<RecordValue|null>(null);
  const [result,setResult]=useState<RecordValue|null>(null), [reviewed,setReviewed]=useState(false), [reason,setReason]=useState('');
  const [from,setFrom]=useState(today()), [to,setTo]=useState(today()), [ownOnly,setOwnOnly]=useState(true);
  const [view,setView]=useState<ClubAction>('instructions'), [page,setPage]=useState(1), [hasMore,setHasMore]=useState(false);
  const pending=useRef<RecordValue|null>(null), generation=useRef(0), statementRequest=useRef<RecordValue|null>(null);
  const book=books.find(item=>item.id===bookId);

  const request=useCallback(async(action:ClubAction,input:RecordValue={},target:number|null=null,selectedBook:number|null=bookId)=>{
    const response=await clubAction(action,spaceId,selectedBook,input,target);
    if(!response.ok) throw new ClubRequestFailure(response.message,response.status);
    return response.data;
  },[spaceId,bookId]);
  const initialise=useCallback(async()=>{
    const version=++generation.current;
    try {
      const [state,schema,profile]=await Promise.all([clubAction('books',spaceId),clubAction('schema',spaceId),clubAction('profile',spaceId)]);
      if(version!==generation.current)return;
      if(!state.ok||!schema.ok||!profile.ok) throw new Error(!state.ok?state.message:!schema.ok?schema.message:!profile.ok?profile.message:'Unable to load');
      const available=rows(state.data.books) as unknown as Book[];
      setBooks(available);setCanMake(state.data.can_make===true);setCanCheck(state.data.can_check===true);
      setOperations(rows(schema.data.operations) as unknown as Operation[]);
      const user=profile.data.user as RecordValue|undefined;
      setUserId(typeof user?.id==='number'?user.id:null);
      setBookId(old=>available.some(item=>item.id===old)?old:available[0]?.id??null);
      setMessage(available.length?'Choose a task. Amounts and ownership calculations come from the API.':'No accounting book exists yet. An authorised officer can create one below.');
    } catch(error){if(version===generation.current)setMessage(error instanceof Error?error.message:'Could not load accounting.');}
  },[spaceId]);
  useEffect(()=>{void initialise();return()=>{generation.current++;};},[initialise]);
  useEffect(()=>{
    setInstruction(null);setPreview(null);setResult(null);setOperation(null);setFrozen(false);pending.current=null;
    setPage(1);statementRequest.current=null;
    if(book){setFrom(book.cutover_date);setTo(today());}
    if(bookId!==null&&canMake){let live=true;void clubAction('catalogue',spaceId,bookId).then(response=>{if(live&&response.ok)setCatalogue(response.data);});return()=>{live=false;};}
  },[bookId,spaceId,canMake,book?.cutover_date]);
  async function run(task:()=>Promise<void>){if(busy)return;setBusy(true);try{await task();}catch(error){setMessage(error instanceof Error?error.message:'The request failed.');}finally{setBusy(false);}}
  function selectOperation(next:Operation){if(frozen)return;setOperation(next);setForm(emptyFields(next.fields));setDate(today());setDraftKey(crypto.randomUUID());setInstruction(null);setPreview(null);setReviewed(false);}
  async function submit(){await run(async()=>{
    if(!operation||!bookId)return;
    if(!pending.current){
      const payload=validateFields(operation.fields,form);
      pending.current={type:operation.type,business_date:isoDate(date),idempotency_key:draftKey,payload};setFrozen(true);
    }
    let data:RecordValue;
    try { data=await request('submit',pending.current); }
    catch(error){
      if(error instanceof ClubRequestFailure&&[400,401,403,404,422].includes(error.status??0)){
        // Preserve the same key when correcting rejected input: a competing
        // accepted request must not turn into a second economic instruction.
        pending.current=null;setFrozen(false);
      }
      throw error;
    }
    setInstruction(data.instruction as unknown as Instruction);setReviewed(false);
    setMessage('Instruction recorded for independent approval. No provider payment was executed.');
  });}
  async function inspect(id:number){await run(async()=>{
    const data=await request('instruction',{},id);setInstruction(data.instruction as unknown as Instruction);setPreview(null);setReviewed(false);setReason('');
  });}
  async function decide(action:'preview'|'approve'|'reject'|'cancel'){await run(async()=>{
    if(!instruction)return;
    const input:RecordValue=action==='approve'?{payload_hash:instruction.payload_hash}:action==='reject'||action==='cancel'?{reason}:{};
    const data=await request(action,input,instruction.id);
    if(action==='preview'){setPreview(data);setMessage('Simulation only. Approval recalculates using current authorised records; simulated identifiers are not real records.');}
    else {setInstruction(data.instruction as unknown as Instruction);setPreview(null);setReviewed(false);setMessage('Decision recorded. The accounting API remains authoritative.');}
  });}
  async function loadView(next:ClubAction,nextPage=1){await run(async()=>{
    let input:RecordValue={};
    if(next==='report')input={period_start:isoDate(from),period_end:isoDate(to),...(ownOnly&&userId?{member_user_id:userId}:{})};
    else if(next==='statements')input={page:nextPage,...(ownOnly&&userId?{member_user_id:userId}:{})};
    else if(next==='instructions'||next==='journals')input={page:nextPage,limit:25};
    const data=await request(next,input);setResult(data);setView(next);setPage(nextPage);setHasMore(data.has_more===true);
    setMessage('Showing the current API result. Different currencies are not combined.');
  });}
  async function issue(){await run(async()=>{
    const input:RecordValue={period_start:isoDate(from),period_end:isoDate(to),...(ownOnly&&userId?{member_user_id:userId}:{})};
    statementRequest.current??={...input,idempotency_key:crypto.randomUUID()};
    const data=await request('issue-statement',statementRequest.current);setResult(data);setView('statement');
    statementRequest.current=null;setMessage('Frozen statement issued. This does not certify bank reconciliation or execute a payout.');
  });}
  async function createBook(event:FormEvent<HTMLFormElement>){event.preventDefault();const data=new FormData(event.currentTarget);await run(async()=>{
    const unitised=data.get('ownership_model')==='unitised';
    const input:RecordValue={currency:String(data.get('currency')).trim().toUpperCase(),ownership_model:String(data.get('ownership_model')),
      cutover_date:isoDate(String(data.get('cutover_date'))),valuation_max_age_days:integerInput(data.get('valuation_max_age_days'),1),
      ...(unitised?{initial_unit_price_minor:integerInput(data.get('initial_unit_price_minor'),1)}:{})};
    await request('create-book',input,null,null);await initialise();
    setMessage('Draft book created. Reconcile and independently approve its opening balances before posting normal activity.');
  });}

  return <div className={'screen '+styles.workspace}>
    <h1>Club accounting</h1><p>Member capital, investments, approvals and statements. Bookkeeping does not execute bank or mobile-money payments.</p>
    <p role="status" aria-live="polite" className="state-notice">{message}</p>
    <p><a href={'/spaces/'+spaceId+'/treasury'}>Treasury and external statement reconciliation</a></p>
    {books.length>0&&<label>Accounting book <select value={bookId??''} disabled={busy||frozen&&instruction===null}
      onChange={event=>setBookId(integerInput(event.target.value,1))}>
      {books.map(item=><option key={item.id} value={item.id}>{item.currency} · {humanLabel(item.ownership_model)} · {item.status}</option>)}
    </select></label>}
    {canMake&&<details><summary>Create a currency-specific accounting book</summary><form onSubmit={createBook} className="form-grid">
      <label>Currency<input name="currency" defaultValue="UGX" required pattern="[A-Za-z]{3}"/></label>
      <label>Ownership model<select name="ownership_model"><option value="capital_accounts">Member capital accounts</option><option value="unitised">Ownership units</option></select></label>
      <label>Cutover date<input name="cutover_date" type="date" defaultValue={today()} max={today()} required/></label>
      <label>Initial unit price, for unitised books only<input name="initial_unit_price_minor" inputMode="numeric"/></label>
      <label>Approved valuation age, days<input name="valuation_max_age_days" inputMode="numeric" required/></label>
      <p>No price, opening balance or member ownership is invented. Choose the club’s approved accounting policy.</p>
      <button disabled={busy} className="button">Create draft book</button>
    </form></details>}
    {book&&<>
      <section className="panel"><h2>{book.currency} book</h2><p>Opened {book.cutover_date}; {book.closed_through?'closed through '+book.closed_through:'no period closure recorded'}.</p>
        {book.my_position&&<DataView value={book.my_position} label="My position"/>}
      </section>
      <section className="panel"><h2>Statements and reports</h2>
        <label>From<input type="date" value={from} disabled={busy||statementRequest.current!==null} onChange={event=>setFrom(event.target.value)}/></label>
        <label>Through<input type="date" value={to} max={today()} disabled={busy||statementRequest.current!==null} onChange={event=>setTo(event.target.value)}/></label>
        {canMake&&<label><input type="checkbox" checked={ownOnly} disabled={busy||statementRequest.current!==null} onChange={event=>setOwnOnly(event.target.checked)}/>My member records only</label>}
        <div className="inline-form"><button disabled={busy||!userId} onClick={()=>void loadView('report')}>View report</button>
          <button disabled={busy||!userId} onClick={()=>void loadView('statements')}>Issued statements</button>
          <button disabled={busy||!userId} onClick={()=>void issue()}>{statementRequest.current?'Retry same statement request':'Issue frozen statement'}</button></div>
      </section>
      {canMake&&<section className="panel"><h2>Record and approve activity</h2>
        <div className="inline-form"><button disabled={busy} onClick={()=>void loadView('instructions')}>Instruction queue</button><button disabled={busy} onClick={()=>void loadView('journals')}>Journal history</button><button disabled={busy} onClick={()=>void loadView('integrity')}>Integrity checks</button></div>
        <label>New task<select disabled={busy||frozen} value={operation?.type??''} onChange={event=>{const next=operations.find(item=>item.type===event.target.value);if(next)selectOperation(next);}}><option value="">Choose a task</option>{operations.map(item=><option key={item.type} value={item.type}>{item.title}</option>)}</select></label>
        {operation&&<form onSubmit={event=>{event.preventDefault();void submit();}}>
          <p>{operation.effect}</p><p>Currency: {book.currency}. Enter integer minor units; ownership quantities use micro-units. The server calculates all financial results.</p>
          <label>Business date<input type="date" required value={date} max={today()} disabled={busy||frozen} onChange={event=>setDate(event.target.value)}/></label>
          {operation.fields.map(field=><FieldEditor key={field.key} field={field} value={form[field.key]} set={value=>setForm(old=>({...old,[field.key]:value}))} catalogue={catalogue} disabled={busy||frozen} prefix="club-draft"/>)}
          <p>Request key: <code>{draftKey}</code></p><button className="button" disabled={busy||instruction!==null} type="submit">{frozen?'Retry unchanged instruction':'Submit for independent approval'}</button>
          {instruction&&<button className="button secondary" type="button" disabled={busy} onClick={()=>{pending.current=null;setFrozen(false);setOperation(null);setInstruction(null);setPreview(null);}}>Start a different instruction</button>}
          {frozen&&!instruction&&<p>Keep this page open while resolving an uncertain result. Inputs are locked to preserve the original request identity.</p>}
        </form>}
      </section>}
      {result&&<section className="panel"><h2>{humanLabel(view)}</h2>
        {view==='instructions'?<>{rows(result.instructions).map(row=><article key={text(row.id)}><p>{text(row.reference)} · {humanLabel(text(row.type))} · {text(row.status)}</p><button disabled={busy} onClick={()=>void inspect(integerInput(row.id,1))}>Inspect instruction</button></article>)}</>:<DataView value={result}/>}
        {view==='statements'&&rows(result.statements).map(row=><div key={text(row.id)}><button disabled={busy} onClick={()=>void run(async()=>{setResult(await request('statement',{},integerInput(row.id,1)));setView('statement');})}>Open statement {text(row.reference)}</button>
          <a href={'/api/club-statements/'+spaceId+'/'+bookId+'/'+text(row.id)+'/csv'}>CSV export</a> · <a href={'/api/club-statements/'+spaceId+'/'+bookId+'/'+text(row.id)+'/html'}>Printable HTML</a></div>)}
        {['instructions','journals','statements'].includes(view)&&<div><button disabled={busy||page<=1} onClick={()=>void loadView(view,page-1)}>Previous page</button><span> Page {page} </span><button disabled={busy||!hasMore} onClick={()=>void loadView(view,page+1)}>Next page</button></div>}
      </section>}
      {instruction&&<section className="panel"><h2>Review instruction {instruction.reference}</h2>
        <p>Status: {instruction.status}. Maker: {instruction.maker_id}. Business date: {instruction.business_date}.</p>
        <DataView value={instruction.payload}/><p>Approved meaning hash: <code style={{overflowWrap:'anywhere'}}>{instruction.payload_hash}</code></p>
        {instruction.result&&<DataView value={instruction.result} label="Recorded result"/>}
        {instruction.status==='pending'&&<>
          <button disabled={busy} onClick={()=>void decide('preview')}>Simulate current outcome</button>
          {preview&&<DataView value={preview} label="Simulation only"/>}
          {canCheck&&instruction.maker_id!==userId&&<><label><input type="checkbox" checked={reviewed} onChange={event=>setReviewed(event.target.checked)}/>I checked the source evidence, amount, currency, members and accounting effect.</label>
            <button disabled={busy||!reviewed} onClick={()=>void decide('approve')}>Approve this exact instruction</button></>}
          <label>Reason for rejection or cancellation<textarea value={reason} maxLength={500} onChange={event=>setReason(event.target.value)}/></label>
          {instruction.maker_id===userId?<button disabled={busy||!reason.trim()} onClick={()=>void decide('cancel')}>Cancel my pending instruction</button>:
            canCheck&&<button disabled={busy||!reason.trim()} onClick={()=>void decide('reject')}>Reject with reason</button>}
        </>}
      </section>}
    </>}
  </div>;
}
