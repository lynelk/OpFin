'use server';

import { getAccessToken } from '@/lib/auth/session';
import { positiveId, type ClubAction, type ClubResult, type RecordValue } from '@/lib/club-accounting/contracts';

/** Fixed routes only. Tokens and configured origins never enter the browser result. */
export async function clubAction(
  action: ClubAction, spaceId: number, bookId: number | null = null,
  input: RecordValue = {}, targetId: number | null = null,
): Promise<ClubResult> {
  try {
    positiveId(spaceId);
    const token = await getAccessToken();
    if (!token) return {ok:false,status:401,message:'Sign in again to use club accounting.'};
    const configured = process.env.NEXT_PUBLIC_OPFIN_API_URL;
    if (!configured) throw new Error('API unavailable');
    const base = configured.replace(/\/$/,'');
    const prefix = '/financial-spaces/'+spaceId+'/accounting/books';
    const book = bookId === null ? '' : prefix+'/'+positiveId(bookId);
    let path: string; let method = 'GET';
    switch (action) {
      case 'schema': path='/accounting/club-schema'; break;
      case 'profile': path='/profile'; break;
      case 'books': path=prefix; break;
      case 'create-book': path=prefix; method='POST'; break;
      case 'catalogue': case 'instructions': case 'report': case 'journals': case 'integrity': case 'statements':
        if (!book) throw new Error('Book required'); path=book+'/'+action; break;
      case 'submit': if(!book) throw new Error('Book required'); path=book+'/instructions'; method='POST'; break;
      case 'issue-statement': if(!book) throw new Error('Book required'); path=book+'/statements'; method='POST'; break;
      case 'instruction': case 'preview': case 'approve': case 'reject': case 'cancel':
        if(!book) throw new Error('Book required');
        path=book+'/instructions/'+positiveId(targetId)+(action==='instruction'?'':'/'+action);
        if(action!=='instruction') method='POST'; break;
      case 'statement': if(!book) throw new Error('Book required'); path=book+'/statements/'+positiveId(targetId); break;
      default: return {ok:false,status:422,message:'This accounting operation is not supported.'};
    }
    const serialised = JSON.stringify(input);
    if (serialised.length > 200_000) return {ok:false,status:422,message:'The instruction is too large. Split it into smaller reviewed records.'};
    if(method==='GET') {
      const params=new URLSearchParams();
      const allowed = new Set(['page','limit','status','period_start','period_end','member_user_id']);
      for(const [key,value] of Object.entries(input)) {
        if(!allowed.has(key)||!['string','number'].includes(typeof value)) return {ok:false,status:422,message:'Invalid accounting query.'};
        params.set(key,String(value));
      }
      if(params.size) path+='?'+params;
    }
    const response=await fetch(base+path,{method,headers:{Accept:'application/json','Content-Type':'application/json',Authorization:'Bearer '+token},
      body:method==='POST'?serialised:undefined,cache:'no-store',redirect:'error',signal:AbortSignal.timeout(30_000)});
    const payload: unknown=await response.json();
    if(!payload||typeof payload!=='object') throw new Error('Invalid response');
    const envelope=payload as {success?:boolean;data?:RecordValue;message?:unknown};
    if(!response.ok||envelope.success!==true) return {ok:false,status:response.status,message:
      response.status>=500?'The service is temporarily unavailable. Retain the request key and check its status before retrying.':
      typeof envelope.message==='string'?envelope.message:'The accounting request was not accepted.'};
    if(!envelope.data||typeof envelope.data!=='object'||Array.isArray(envelope.data)) throw new Error('Invalid response');
    return {ok:true,data:envelope.data};
  } catch {
    return {ok:false,message:'The result is uncertain. Keep this instruction unchanged and retry with the same request key, or check its status. Do not create a duplicate.'};
  }
}
