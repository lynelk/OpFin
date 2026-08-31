import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { longRangeApi, type LongRangeRecord } from "@/lib/api/long-range";
import { getAccessToken } from "@/lib/auth/session";
import { OpfinApiError } from "@/lib/api/errors";
import { reviewLongRangeAction } from "@/app/long-range-actions";

function text(value: unknown): string { return typeof value === "string" ? value : String(value ?? ""); }

function Queue({ title, records, type, approve = "approved", reject = "rejected" }: Readonly<{ title: string; records: LongRangeRecord[]; type: string; approve?: string; reject?: string }>) {
  return (
    <section className="panel">
      <div className="case-card-head"><h2>{title}</h2><span className="badge">{records.length} shown</span></div>
      {records.length === 0 ? <p className="muted">No items require review.</p> : <div className="case-list">{records.map((record) => (
        <article className="case-card" key={`${type}-${record.id}`}>
          <div className="case-card-head"><strong>{text(record.reference) || `Record ${record.id}`}</strong><span className="badge warn">{text(record.status)}</span></div>
          <p className="muted">ID {record.id} · User {text(record.user_id ?? record.borrower_user_id ?? record.owner_user_id ?? record.referrer_user_id ?? record.created_by)}</p>
          <form action={reviewLongRangeAction} className="form-grid">
            <input type="hidden" name="type" value={type} /><input type="hidden" name="id" value={record.id} />
            {type === "asset_finance" ? <><div className="field"><label htmlFor={`reason-${record.id}`}>Decision reason</label><input id={`reason-${record.id}`} name="reason" required /></div><div className="field"><label htmlFor={`approved-${record.id}`}>Approved amount, if applicable</label><input id={`approved-${record.id}`} name="approved_amount_minor" type="number" min="0" /></div></> : null}
            {type === "linked_account" || type === "community" ? <div className="field"><label htmlFor={`evidence-${type}-${record.id}`}>Verification evidence</label><input id={`evidence-${type}-${record.id}`} name="evidence" /></div> : null}
            {type === "referral" ? <div className="field"><label htmlFor={`reward-${record.id}`}>Reward amount (UGX)</label><input id={`reward-${record.id}`} name="reward_minor" type="number" min="0" max="10000000" required /></div> : null}
            <div className="auth-actions"><button className="button" type="submit" name="status" value={approve}>{type === "referral" ? "Post eligible reward" : "Approve / verify"}</button>{type !== "referral" ? <button className="button secondary" type="submit" name="status" value={reject}>Reject</button> : null}</div>
          </form>
        </article>
      ))}</div>}
    </section>
  );
}

export default async function LongRangeAdminPage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};
  try {
    const data = await longRangeApi.governance(token);
    return (
      <Screen title="Long-range operations" description="Operate OpFin's connected-account, community, asset, participatory, capital and distribution extensions through explicit queues, maker-checker review and externally visible activation gates.">
        {params.status ? <StateNotice state="success" message={`Completed: ${params.status.replaceAll("-", " ")}. Audit evidence has been retained.`} /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Review failed."} /> : null}
        <section className="panel compass-next-action"><p className="eyebrow">CONTROLLED EXPANSION</p><h2>Create capital and distribution structures without bypassing approval.</h2><p className="muted">New mandates and partners start in review states and require an independent operator before activation.</p><Link className="button" href="/admin/long-range/setup">Create mandate or partner</Link></section>
        <div className="grid grid-3">
          <section className="panel"><h2>Verification</h2><div className="stat">{data.linked_accounts_pending + data.community_memberships_pending}</div><p className="muted">Linked accounts and community memberships awaiting independent evidence.</p></section>
          <section className="panel"><h2>Finance review</h2><div className="stat">{data.asset_finance_pending + data.participatory_listings_pending + data.capital_mandates_pending}</div><p className="muted">Asset, participatory and capital decisions awaiting governance.</p></section>
          <section className="panel"><h2>Money finality</h2><div className="stat">{data.financial_intents_processing}</div><p className="muted">Financial intents waiting on provider finality. Awaiting step-up: {data.financial_intents_awaiting_step_up}.</p></section>
        </div>
        <Queue title="Linked accounts" records={data.queues.linked_accounts} type="linked_account" approve="verified" reject="rejected" />
        <Queue title="Asset finance" records={data.queues.asset_finance} type="asset_finance" approve="approved" reject="declined" />
        <Queue title="Community memberships" records={data.queues.community_memberships} type="community" approve="verified" reject="rejected" />
        <Queue title="Participatory finance" records={data.queues.participatory_listings} type="participatory" />
        <Queue title="Capital mandates" records={data.queues.capital_mandates} type="capital" />
        <Queue title="Distribution partners" records={data.queues.partners} type="partner" />
        <Queue title="Referral rewards" records={data.queues.referrals} type="referral" />
        <section className="panel"><h2>Offline conflicts</h2><p className="muted">{data.offline_conflicts} batch(es) require review. Offline capture never silently overwrites server truth.</p></section>
        <section className="panel"><h2>External activation gates</h2><div className="case-list">{data.external_activation_gates.map((item) => <article className="case-card" key={item.capability}><strong>{item.capability.replaceAll("_", " ")}</strong><p className="muted">{item.gate.replaceAll("_", " ")}</p></article>)}</div></section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Long-range operations" description="Govern long-range platform capabilities from one controlled queue."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load long-range operations."} /></Screen>;
  }
}
