import { getAccessToken } from '@/lib/auth/session';

export async function GET(_request:Request,{params}:{params:Promise<{space:string;book:string;statement:string;format:string}>}) {
  const {space,book,statement,format}=await params;
  if(![space,book,statement].every(v=>/^[1-9][0-9]*$/.test(v)&&Number.isSafeInteger(Number(v)))||!['html','csv'].includes(format))
    return new Response('Not found',{status:404});
  const token=await getAccessToken();
  if(!token)return new Response('Authentication required',{status:401});
  const base=process.env.NEXT_PUBLIC_OPFIN_API_URL?.replace(/\/$/,'');
  if(!base)return new Response('API unavailable',{status:503});
  try {
    const response=await fetch(`${base}/financial-spaces/${space}/accounting/books/${book}/statements/${statement}/${format}`,
      {headers:{Authorization:'Bearer '+token,Accept:format==='csv'?'text/csv':'text/html'},cache:'no-store',redirect:'error',signal:AbortSignal.timeout(30000)});
    if(!response.ok)return new Response('The statement is unavailable for this account.',{status:response.status});
    return new Response(response.body,{headers:{'Content-Type':format==='csv'?'text/csv; charset=utf-8':'text/html; charset=utf-8',
      'Content-Disposition':`attachment; filename="opfin-club-${statement}.${format}"`,'Cache-Control':'private, no-store',
      'X-Content-Type-Options':'nosniff','Content-Security-Policy':"sandbox; default-src 'none'; frame-ancestors 'none'",'Referrer-Policy':'no-referrer'}});
  }catch{return new Response('The statement service is unavailable.',{status:503});}
}
