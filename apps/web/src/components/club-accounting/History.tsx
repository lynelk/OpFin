'use client';

import { useEffect, useState } from 'react';
import { clubAction, clubHistoryAction } from '@/app/club-accounting-actions';
import type { RecordValue } from '@/lib/club-accounting/contracts';
import ClubRecovery, {ClubRecord} from './Recovery';
const today = () => new Intl.DateTimeFormat('en-CA', {timeZone:'Africa/Kampala', year:'numeric', month:'2-digit', day:'2-digit'}).format(new Date());

export default function ClubHistory() {
  const [books, setBooks] = useState<RecordValue[]>([]);
  const [selected, setSelected] = useState<RecordValue | null>(null);
  const [userId, setUserId] = useState<number | null>(null);
  const [from, setFrom] = useState('');
  const [to, setTo] = useState(today());
  const [records, setRecords] = useState<RecordValue[]>([]);
  const [report, setReport] = useState<RecordValue | null>(null);
  const [message, setMessage] = useState('Loading your own club capital history…');
  const [busy, setBusy] = useState(false);
  const [page, setPage] = useState(1);
  const [more, setMore] = useState(false);
  useEffect(() => {
    let live = true;
    void Promise.all([clubHistoryAction(), clubAction('profile', 1)]).then(([history, profile]) => {
      if (!live) return;
      if (!history.ok || !profile.ok) { setMessage(!history.ok ? history.message : !profile.ok ? profile.message : 'Unavailable'); return; }
      setBooks(history.data.books as RecordValue[]);
      const user = profile.data.user as RecordValue;
      setUserId(Number(user.id));
      setMessage('Only your member records are shown. Leaving a club does not remove access to your retained financial history.');
    }).catch(() => { if (live) setMessage('History could not be loaded. Refresh after signing in.'); });
    return () => {live = false;};
  }, []);
  async function load(kind: 'report' | 'statements' | 'issue-statement', nextPage = 1) {
    if (!selected || !userId || busy) return;
    setBusy(true);
    try {
      const input: RecordValue = kind === 'statements' ? {member_user_id:userId, page:nextPage} : {
        period_start:from, period_end:to, member_user_id:userId,
        ...(kind === 'issue-statement' ? {idempotency_key:crypto.randomUUID()} : {}),
      };
      const response = await clubAction(kind, Number(selected.financial_space_id), Number(selected.id), input);
      if (!response.ok) {setMessage(response.message); return;}
      if (kind === 'statements') {
        setRecords(response.data.statements as RecordValue[]); setPage(nextPage); setMore(response.data.has_more === true);
      } else setReport(response.data);
      setMessage(kind === 'issue-statement' ? 'Your statement is recorded. Read and acknowledge its saved request before issuing another.' : 'Loaded your own authorised history.');
    } finally {setBusy(false);}
  }
  return <main className="screen">
    <h1>My club capital history</h1><p role="status" aria-live="polite">{message}</p>
    {books.map(book => <article className="panel" key={String(book.id)}>
      <h2>{String(book.name)}</h2><p>{String(book.currency)} · {String(book.ownership_model).replaceAll('_',' ')}</p>
      <button className="button" disabled={busy} onClick={() => {setSelected(book); setFrom(String(book.cutover_date)); setRecords([]); setReport(null); setPage(1);}}>Open my member history</button>
    </article>)}
    {selected && <section className="panel">
      <h2>{String(selected.name)}: my records</h2>
      <label>From<input type="date" value={from} onChange={event => setFrom(event.target.value)}/></label>
      <label>Through<input type="date" value={to} max={today()} onChange={event => setTo(event.target.value)}/></label>
      <button disabled={busy} onClick={() => void load('report')}>Read my report</button>
      <button disabled={busy} onClick={() => void load('statements')}>My issued statements</button>
      <button disabled={busy} onClick={() => void load('issue-statement')}>Issue my statement</button>
      {report && <ClubRecord value={report}/>}
      {records.map(record => <article key={String(record.id)}><p>{String(record.period_start)} to {String(record.period_end)} · {String(record.reference)}</p>
        <a className="button secondary" href={`/api/club-statements/${selected.financial_space_id}/${selected.id}/${record.id}/csv`}>CSV</a>{' '}
        <a className="button secondary" href={`/api/club-statements/${selected.financial_space_id}/${selected.id}/${record.id}/html`}>Printable statement</a>
      </article>)}
      <button disabled={busy || page <= 1} onClick={() => void load('statements', page - 1)}>Previous</button> Page {page} <button disabled={busy || !more} onClick={() => void load('statements', page + 1)}>Next</button>
    </section>}
    <ClubRecovery/>
  </main>;
}
