'use strict';
(() => {
  const $ = id => document.getElementById(id);
  let token = '', page = 1, generation = 0;
  function node(tag, text, parent) { const item = document.createElement(tag); item.textContent = text; if (parent) parent.append(item); return item; }
  async function get(path, authenticated = false) {
    const headers = {'Accept':'application/json'};
    if (authenticated && token) headers.Authorization = 'Bearer ' + token;
    const response = await fetch(path, {headers, credentials:'same-origin', cache:'no-store', redirect:'error'});
    if (!response.ok) throw new Error('Documentation request returned HTTP ' + response.status + '. Check your access and try again safely.');
    const body = await response.json();
    return body;
  }
  function evidence(value) {
    if (!value) return;
    $('provenance').textContent = 'Running revision: ' + (value.source_revision || 'not supplied') + ' | Contract fingerprint: ' + value.contract_fingerprint + ' | Environment: ' + value.environment;
  }
  function guideText(text) {
    const article = $('reader'); article.replaceChildren(); let code = null;
    for (const line of text.split('\n')) {
      if (line.startsWith('```')) { if (code) code = null; else code = node('pre','',article); continue; }
      if (code) { code.textContent += line + '\n'; continue; }
      if (!line.trim()) continue;
      const heading = /^(#{1,3})\s+(.+)$/.exec(line);
      if (heading) node('h' + Math.min(heading[1].length + 1, 4),heading[2],article);
      else node('p',line,article);
    }
    article.focus();
  }
  async function showGuide(id) {
    try { const body = await get('/api/developer/guides/' + encodeURIComponent(id)); guideText(body.data.guide.text); evidence(body.data.provenance); }
    catch (error) { $('status').textContent = error.message; }
  }
  function showOperation(operation) {
    const article = $('reader'); article.replaceChildren(); node('h2',operation.summary,article);
    node('pre',operation.method + ' ' + operation.path,article);
    node('p',operation.description,article);
    const notice = node('p',operation.contract_status === 'documented' ? 'Contract documented. Actual authorisation, provider availability and financial acceptance remain separate.' : 'Registration only. This operation is not included in SDK-oriented OpenAPI until its contract is reviewed.',article); notice.className='notice';
    node('h3','Authentication and AI boundary',article);
    node('pre',JSON.stringify({authentication:operation.authentication,role_groups:operation.required_role_groups,agent_execution:operation.agent_execution,risk:operation.risk},null,2),article);
    node('h3','Parameters and body',article); node('pre',JSON.stringify({parameters:operation.parameters,requestBody:operation.request_body},null,2),article);
    node('h3','Responses',article); node('pre',JSON.stringify(operation.responses,null,2),article);
    node('p','Source digest: ' + operation.source_digest,article); article.focus();
  }
  async function search() {
    const current = ++generation;
    const params = new URLSearchParams({q:$('query').value,page:String(page),limit:'20'});
    if ($('method').value) params.set('method',$('method').value);
    $('status').textContent = 'Reading current source-linked documentation…';
    try {
      const [body, guides] = await Promise.all([
        get('/api/developer/' + (token ? 'catalogue' : 'public') + '?' + params,Boolean(token)),
        get('/api/developer/guides?q=' + encodeURIComponent($('query').value))
      ]);
      if (generation !== current) return;
      const data = body.data; $('operations').replaceChildren(); $('guides').replaceChildren();
      $('status').textContent = data.total + ' matching operations. Visible coverage: ' + data.coverage.documented + ' documented; ' + data.coverage.registration_only + ' registration-only. Page ' + data.page + '.';
      for (const guide of guides.data.guides) {
        const b = node('button',guide.title,$('guides')); b.type='button'; node('small',guide.audience,b); b.addEventListener('click',()=>showGuide(guide.id));
      }
      for (const operation of data.items) {
        const b = node('button',operation.summary,$('operations')); b.type='button'; node('small',operation.method + ' ' + operation.path,b); node('small',operation.contract_status.replaceAll('_',' '),b); b.addEventListener('click',()=>showOperation(operation));
      }
      $('previous').disabled = page <= 1; $('next').disabled = !data.has_more; evidence(data.provenance);
    } catch (error) { if (generation === current) $('status').textContent = error.message; }
  }
  $('search').addEventListener('click',()=>{page=1;search();});
  $('query').addEventListener('keydown',event=>{if(event.key==='Enter'){page=1;search();}});
  $('authorise').addEventListener('click',()=>{token=$('token').value.trim();$('token').value='';page=1;search();});
  $('clear').addEventListener('click',()=>{token='';$('token').value='';page=1;search();});
  $('previous').addEventListener('click',()=>{if(page>1){page--;search();}});
  $('next').addEventListener('click',()=>{page++;search();});
  $('export').addEventListener('click',async()=>{
    if(!token){$('status').textContent='Load an authorised token first. No token is stored persistently.';return;}
    try { const document = await get('/api/developer/openapi',true); const url=URL.createObjectURL(new Blob([JSON.stringify(document,null,2)],{type:'application/json'})); const a=window.document.createElement('a');a.href=url;a.download='opfin-reviewed-openapi.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000); }
    catch(error){$('status').textContent=error.message;}
  });
  window.addEventListener('pagehide',()=>{token='';$('token').value='';});
  search();
})();
