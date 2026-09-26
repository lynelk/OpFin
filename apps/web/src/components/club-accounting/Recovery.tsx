'use client';

import { useCallback, useEffect, useState } from 'react';
import { clubRecoveryAction } from '@/app/club-accounting-actions';
import type { Json, RecordValue } from '@/lib/club-accounting/contracts';

export function ClubRecord({value}: {value: Json}) {
  if (value === null) return <span>Not recorded</span>;
  if (Array.isArray(value)) return <ol>{value.map((row, index) => <li key={index}><ClubRecord value={row}/></li>)}</ol>;
  if (typeof value === 'object') return <dl>{Object.entries(value).map(([key, row]) => <div key={key}><dt>{key.replaceAll('_', ' ')}</dt><dd><ClubRecord value={row}/></dd></div>)}</dl>;
  return <span style={{overflowWrap:'anywhere'}}>{String(value)}</span>;
}

export default function ClubRecovery({spaceId}: {spaceId?: number}) {
  const [records, setRecords] = useState<RecordValue[]>([]);
  const [selected, setSelected] = useState<RecordValue | null>(null);
  const [result, setResult] = useState<RecordValue | null>(null);
  const [message, setMessage] = useState('Saved requests remain available after a reload.');
  const [busy, setBusy] = useState(false);
  const [page, setPage] = useState(1);
  const [more, setMore] = useState(false);
  const load = useCallback(async (nextPage = 1) => {
    const response = await clubRecoveryAction('list', spaceId, undefined, {}, nextPage);
    if (!response.ok) { setMessage(response.message); return; }
    setRecords((response.data.requests as RecordValue[]) ?? []);
    setPage(nextPage); setMore(response.data.has_more === true);
  }, [spaceId]);
  useEffect(() => { void load(); }, [load]);
  async function inspect(record: RecordValue) {
    if (busy) return;
    setBusy(true);
    try {
      const response = await clubRecoveryAction('inspect', Number(record.financial_space_id), Number(record.book_id), {
        reference: record.reference, content_hash: record.content_hash,
      });
      if (!response.ok) { setMessage(response.message); return; }
      setSelected({...record, ...response.data}); setResult(null);
    } finally {setBusy(false);}
  }
  async function act(action: 'resume' | 'acknowledge' | 'cancel') {
    if (!selected || busy) return;
    setBusy(true);
    try {
      const response = await clubRecoveryAction(action, Number(selected.financial_space_id), Number(selected.book_id), {
        reference: selected.reference, content_hash: selected.content_hash,
      });
      if (!response.ok) { setMessage(response.message); return; }
      if (action === 'resume') {
        setResult(response.data);
        setMessage('This is the recorded result of the original request. Acknowledging it does not approve an instruction or send money.');
      } else {
        setSelected(null); setResult(null);
        setMessage(action === 'cancel' ? 'Unsubmitted request cancelled. Reload the form before starting a different request.' : 'Result acknowledged. You may start another request.');
        await load(page);
      }
    } finally { setBusy(false); }
  }
  return <section className="panel" id="saved-requests" aria-labelledby="recovery-title">
    <h2 id="recovery-title">Saved requests</h2>
    <p>After a timeout or reload, resume the exact saved request. Read and acknowledge a recorded result before starting another request of the same kind.</p>
    <p role="status" aria-live="polite">{message}</p>
    <button className="button secondary" disabled={busy} onClick={() => void load(page)}>Refresh saved requests</button>
    {records.length === 0 && <p>No saved requests on this page.</p>}
    {records.map(record => <article key={String(record.reference)}>
      <p>{String(record.currency)} · {String(record.purpose)} · {String(record.status)} · Book {String(record.book_id)}</p>
      <button className="button secondary" disabled={busy} onClick={() => void inspect(record)}>Review saved request</button>
    </article>)}
    <div><button disabled={busy || page <= 1} onClick={() => void load(page - 1)}>Previous</button> Page {page} <button disabled={busy || !more} onClick={() => void load(page + 1)}>Next</button></div>
    {selected && <div>
      <h3>Original request {String(selected.reference)}</h3>
      <ClubRecord value={selected.envelope}/>
      <button className="button" disabled={busy} onClick={() => void act('resume')}>Resume or read original result</button>
      {selected.status === 'prepared' && !result && <button className="button secondary" disabled={busy} onClick={() => void act('cancel')}>Cancel this unsubmitted request</button>}
      {result && <><h3>Recorded result</h3><ClubRecord value={result}/><button className="button" disabled={busy} onClick={() => void act('acknowledge')}>I have read this result</button></>}
    </div>}
  </section>;
}
