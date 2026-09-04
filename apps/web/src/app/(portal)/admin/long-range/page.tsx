import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { longRangeApi, type LongRangeRecord } from "@/lib/api/long-range";
import { getAccessToken } from "@/lib/auth/session";
import { OpfinApiError } from "@/lib/api/errors";
import { reviewLongRangeAction } from "@/app/long-range-actions";
import { reviewPeerListingAction } from "@/app/focused-long-range-actions";

function text(value: unknown): string { return typeof value === "string" ? value : String(value ?? ""); }
function amount(value: unknown): number { return typeof value === "number" ? value : Number(value ?? 0); }

function Queue({ title, description, records, type, approve = "approved", reject = "rejected" }: Readonly<{ title: string; description: string; records: LongRangeRecord[]; type: string; approve?: string; reject?: string }>) {
  return (
    <section className="panel">
      <div className="case-card-head"><div><h2>{title}</h2><p className="muted">{description}</p></div><span className="badge">{records.length}</span></div>
      {records.length === 0 ? <p className="muted">Nothing needs action.</p> : <div className="case-list">{records.map((record) => (
        <article className="case-card" key={`${type}-${record.id}`}>
          <div className="case-card-head"><strong>{text(record.reference) || `Record ${record.id}`}</strong><span className="badge warn">{text(record.status).replaceAll("_", " ")}</span></div>
          <form action={reviewLongRangeAction} className="form-grid">
            <input type="hidden" name="type" value={type} /><input type="hidden" name="id" value={record.id} />
            {type === "asset_finance" ? <><div className="field"><label htmlFor={`reason-${record.id}`}>Decision reason</label><input id={`reason-${record.id}`} name="reason" required /></div><div className="field"><label htmlFor={`approved-${record.id}`}>Approved amount (UGX)</label><input id={`approved-${record.id}`} name="approved_amount_minor" type="number" min="0" /></div></> : null}
            {type === "linked_account" || type === "community" ? <div className="field"><label htmlFor={`evidence-${type}-${record.id}`}>Verification evidence</label><input id={`evidence-${type}-${record.id}`} name="evidence" /></div> : null}
            {type === "referral" ? <div className="field"><label htmlFor={`reward-${record.id}`}>Eligible reward (UGX)</label><input id={`reward-${record.id}`} name="reward_minor" type="number" min="0" max="10000000" required /></div> : null}
            <div className="auth-actions"><button className="button" type="submit" name="status" value={approve}>{type === "referral" ? "Post reward" : "Approve / verify"}</button>{type !== "referral" ? <button className="button secondary" type="submit" name="status" value={reject}>Reject</button> : null}</div>
          </form>
        </article>
      ))}</div>}
    </section>
  );
}

function PeerQueue({ records }: Readonly<{ records: LongRangeRecord[] }>) {
  return (
    <section className="panel">
      <div className="case-card-head"><div><h2>Peer-lending requests</h2><p className="muted">Borrowers provide only amount, purpose and term. Operations owns the legal, risk, pricing and investor disclosure pack.</p></div><span className="badge">{records.length}</span></div>
      {records.length === 0 ? <p className="muted">No marketplace requests need review.</p> : <div className="case-list">{records.map((record) => (
        <article className="case-card" key={`peer-${record.id}`}>
          <div className="case-card-head"><div><strong>{text(record.purpose)}</strong><p className="muted">UGX {amount(record.target_amount_minor).toLocaleString("en-UG")} · {amount(record.term_days)} days</p></div><span className="badge warn">Needs review</span></div>
          <form action={reviewPeerListingAction} className="form-grid">
            <input type="hidden" name="id" value={record.id} />
            <div className="field"><label htmlFor={`lender-${record.id}`}>Responsible lender of record</label><input id={`lender-${record.id}`} name="lender_of_record" required /></div>
            <div className="grid grid-3">
              <div className="field"><label htmlFor={`return-${record.id}`}>Expected return (%)</label><input id={`return-${record.id}`} name="expected_return_percent" type="number" min="0" max="100" step="0.01" required /></div>
              <div className="field"><label htmlFor={`risk-${record.id}`}>Risk grade</label><input id={`risk-${record.id}`} name="risk_grade" placeholder="A, B, C..." required /></div>
              <div className="field"><label htmlFor={`repay-${record.id}`}>Repayment frequency</label><input id={`repay-${record.id}`} name="repayment_frequency" placeholder="Monthly" required /></div>
            </div>
            <div className="field"><label htmlFor={`borrower-${record.id}`}>Investor-safe borrower summary</label><textarea id={`borrower-${record.id}`} name="borrower_summary" rows={2} required /></div>
            <div className="field"><label htmlFor={`risk-summary-${record.id}`}>Risk summary</label><textarea id={`risk-summary-${record.id}`} name="risk_summary" rows={2} /></div>
            <div className="grid grid-2">
              <div className="field"><label htmlFor={`fees-${record.id}`}>Fees</label><textarea id={`fees-${record.id}`} name="fees" rows={2} required /></div>
              <div className="field"><label htmlFor={`loss-${record.id}`}>Loss treatment</label><textarea id={`loss-${record.id}`} name="loss_allocation" rows={2} required /></div>
              <div className="field"><label htmlFor={`custody-${record.id}`}>Custody / settlement</label><textarea id={`custody-${record.id}`} name="custody" rows={2} required /></div>
              <div className="field"><label htmlFor={`guarantee-${record.id}`}>Guarantee, if any</label><textarea id={`guarantee-${record.id}`} name="guarantee" rows={2} /></div>
            </div>
            <div className="auth-actions"><button className="button" type="submit" name="status" value="approved">Approve for marketplace</button><button className="button secondary" type="submit" name="status" value="rejected">Reject</button></div>
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
      <Screen title="Operations workspace" description="Work from focused queues. Each team sees a clear job to complete instead of one undifferentiated long-range control screen.">
        {params.status ? <StateNotice state="success" message="Review completed and audit evidence retained." /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Review failed."} /> : null}

        <section className="panel compass-next-action"><p className="eyebrow">MY WORK</p><h2>{data.linked_accounts_pending + data.community_memberships_pending + data.asset_finance_pending + data.participatory_listings_pending + data.capital_mandates_pending + data.partners_pending + data.referrals_pending + data.offline_conflicts} item(s) need action</h2><p className="muted">Start with the queue that matches your responsibility. Money-finality exceptions remain separate from product approval.</p><Link className="button secondary" href="/admin/long-range/setup">Capital & partner setup</Link></section>

        <div className="grid grid-3">
          <section className="panel"><h2>Verification</h2><div className="stat">{data.linked_accounts_pending + data.community_memberships_pending}</div><p className="muted">Accounts and community relationships.</p></section>
          <section className="panel"><h2>Finance decisions</h2><div className="stat">{data.asset_finance_pending + data.participatory_listings_pending}</div><p className="muted">Asset finance and peer lending.</p></section>
          <section className="panel"><h2>Payment processing</h2><div className="stat">{data.financial_intents_processing}</div><p className="muted">Awaiting step-up: {data.financial_intents_awaiting_step_up}.</p></section>
        </div>

        <h2>Verification & customer context</h2>
        <Queue title="Linked accounts" description="Confirm provider evidence without treating customer-entered balances as verified." records={data.queues.linked_accounts} type="linked_account" approve="verified" reject="rejected" />
        <Queue title="Community memberships" description="Verify SACCO, VSLA, cooperative and group relationships." records={data.queues.community_memberships} type="community" approve="verified" reject="rejected" />

        <h2>Finance decisions</h2>
        <Queue title="Asset finance" description="Review the requested asset, financeable amount and decision reason." records={data.queues.asset_finance} type="asset_finance" approve="approved" reject="declined" />
        <PeerQueue records={data.queues.participatory_listings} />

        <h2>Capital, partners & rewards</h2>
        <Queue title="Capital mandates" description="Approve capital structures only after the mandate and policy are complete." records={data.queues.capital_mandates} type="capital" />
        <Queue title="Distribution partners" description="Complete due diligence before activating product access." records={data.queues.partners} type="partner" />
        <Queue title="Referral rewards" description="Post only eligible, identity-linked rewards through the controlled ledger." records={data.queues.referrals} type="referral" />

        <section className="panel"><h2>Exceptions</h2><p className="muted">{data.offline_conflicts} offline batch(es) require review. Conflicts never silently overwrite server truth.</p></section>
        <section className="panel"><h2>External activation gates</h2><div className="case-list">{data.external_activation_gates.map((item) => <article className="case-card" key={item.capability}><strong>{item.capability.replaceAll("_", " ")}</strong><p className="muted">{item.gate.replaceAll("_", " ")}</p></article>)}</div></section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Operations workspace" description="Work from focused operational queues."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load operations."} /></Screen>;
  }
}
