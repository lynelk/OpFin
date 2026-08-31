import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { longRangeApi } from "@/lib/api/long-range";
import { OpfinApiError } from "@/lib/api/errors";
import { formatUgx } from "@/lib/format";
import { createParticipatoryCommitmentAction } from "@/app/long-range-actions";

function text(value: unknown): string { return typeof value === "string" ? value : String(value ?? ""); }
function amount(value: unknown): number { return typeof value === "number" ? value : Number(value ?? 0); }

export default async function ParticipatoryFinancePage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};
  try {
    const [marketplace, overview] = await Promise.all([longRangeApi.marketplace(token), longRangeApi.overview(token)]);
    return (
      <Screen title="Participatory finance" description="Discover only independently approved financing requests, review the responsible lender and disclosures, then commit without confusing an intention to fund with money already settled.">
        {params.status ? <StateNotice state="success" message="Commitment created. Fresh step-up authentication is required before any collection is sent to CPay." /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to complete that action."} /> : null}
        <section className="panel compass-next-action">
          <p className="eyebrow">CONTROLLED MARKETPLACE</p>
          <h2>Approved listings only. Commitment is not settlement.</h2>
          <p className="muted">Every listing must pass independent compliance review with a lender of record plus fee, custody and loss-allocation disclosures. A funding commitment remains awaiting step-up until the investor confirms it through the governed payment rail.</p>
          <Link className="button secondary" href="/ecosystem">Create your own financing request</Link>
        </section>
        <section className="panel">
          <h2>Open opportunities</h2>
          {marketplace.listings.length === 0 ? <StateNotice state="empty" message="There are no independently approved listings open for funding right now." /> : <div className="case-list">{marketplace.listings.map((listing) => {
            const target = amount(listing.target_amount_minor);
            const funded = amount(listing.funded_amount_minor);
            const remaining = Math.max(0, target - funded);
            let disclosures: Record<string, unknown> = {};
            try { disclosures = JSON.parse(text(listing.disclosures) || "{}"); } catch { disclosures = {}; }
            return <article className="case-card" key={listing.id}>
              <div className="case-card-head"><div><strong>{text(listing.purpose)}</strong><p className="muted">{text(listing.lender_of_record)} · {amount(listing.term_days)} days</p></div><span className="badge ok">Open</span></div>
              <div className="grid grid-3"><div><strong>Target</strong><p>{formatUgx(target)}</p></div><div><strong>Funded</strong><p>{formatUgx(funded)}</p></div><div><strong>Remaining</strong><p>{formatUgx(remaining)}</p></div></div>
              <details><summary>Review disclosures</summary><div className="grid grid-3"><div><strong>Fees</strong><p className="muted">{text(disclosures.fees) || "See formal agreement before funding."}</p></div><div><strong>Custody</strong><p className="muted">{text(disclosures.custody) || "See formal agreement before funding."}</p></div><div><strong>Loss allocation</strong><p className="muted">{text(disclosures.loss_allocation) || "See formal agreement before funding."}</p></div></div></details>
              <form action={createParticipatoryCommitmentAction} className="form-grid"><input type="hidden" name="listing_id" value={listing.id} /><div className="field"><label htmlFor={`commit-${listing.id}`}>Amount to commit (UGX)</label><input id={`commit-${listing.id}`} name="amount_minor" type="number" min="1000" max={remaining} required /></div><button className="button" type="submit">Create commitment</button></form>
            </article>;
          })}</div>}
        </section>
        <section className="panel"><h2>Your commitments</h2>{overview.participatory_commitments.length === 0 ? <p className="muted">No commitments yet.</p> : <div className="case-list">{overview.participatory_commitments.map((commitment) => <article className="case-card" key={commitment.id}><div className="case-card-head"><strong>{formatUgx(amount(commitment.amount_minor))}</strong><span className="badge">{text(commitment.status).replaceAll("_", " ")}</span></div><p className="muted">Reference {text(commitment.reference)}</p>{text(commitment.status) === "awaiting_step_up" ? <p>Money has not moved. Complete fresh OTP step-up before payment execution.</p> : null}</article>)}</div>}</section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Participatory finance" description="Approved financing opportunities appear here."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load participatory finance."} /></Screen>;
  }
}
