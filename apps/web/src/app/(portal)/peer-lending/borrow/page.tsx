import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { longRangeApi } from "@/lib/api/long-range";
import { OpfinApiError } from "@/lib/api/errors";
import { formatUgx } from "@/lib/format";
import { createPeerFundingRequestAction } from "@/app/focused-long-range-actions";

function text(value: unknown): string { return typeof value === "string" ? value : String(value ?? ""); }
function amount(value: unknown): number { return typeof value === "number" ? value : Number(value ?? 0); }
function requestStatus(value: unknown): string {
  const status = text(value);
  if (status === "awaiting_compliance_review") return "Under review";
  if (status === "funding") return "Open to investors";
  if (status === "funded") return "Funded";
  if (status === "rejected") return "Not approved";
  return status.replaceAll("_", " ");
}

export default async function PeerBorrowPage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};

  try {
    const overview = await longRangeApi.overview(token);
    return (
      <Screen title="Borrow from investors" description="Tell OpFin the amount, purpose and timeframe. Independent operations staff handle lender, pricing, risk and settlement disclosures before your request can reach investors.">
        {params.status ? <StateNotice state="success" message="Your marketplace funding request was submitted for independent review." /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to submit your request."} /> : null}

        <section className="panel compass-next-action">
          <p className="eyebrow">THREE THINGS ONLY</p>
          <h2>How much, what for, and how long.</h2>
          <p className="muted">OpFin reuses your verified identity and financial information. You are not asked to configure a lender of record, custody arrangement, loss allocation or investor pricing.</p>
        </section>

        <section className="panel">
          <form action={createPeerFundingRequestAction} className="form-grid">
            <div className="field"><label htmlFor="target_amount_minor">How much do you need? (UGX)</label><input id="target_amount_minor" name="target_amount_minor" type="number" min="1000" required autoFocus /></div>
            <div className="field"><label htmlFor="purpose">What is the money for?</label><textarea id="purpose" name="purpose" rows={3} placeholder="For example: business stock, school fees, equipment" required /></div>
            <div className="field"><label htmlFor="term_days">How long do you need? (days)</label><input id="term_days" name="term_days" type="number" min="1" max="730" placeholder="90" required /></div>
            <button className="button" type="submit">Submit for marketplace review</button>
          </form>
        </section>

        <section className="panel">
          <div className="case-card-head"><h2>Your marketplace requests</h2><Link href="/peer-lending">I want to lend instead</Link></div>
          {overview.participatory_listings.length === 0 ? <p className="muted">You have not requested marketplace funding yet.</p> : (
            <div className="case-list">{overview.participatory_listings.map((listing) => {
              const target = amount(listing.target_amount_minor);
              const funded = amount(listing.funded_amount_minor);
              const progress = target > 0 ? Math.min(100, Math.round((funded / target) * 100)) : 0;
              return (
                <article className="case-card" key={listing.id}>
                  <div className="case-card-head"><strong>{text(listing.purpose)}</strong><span className="badge">{requestStatus(listing.status)}</span></div>
                  <p>{formatUgx(target)} · {amount(listing.term_days)} days</p>
                  {text(listing.status) === "funding" || funded > 0 ? <><div className="setup-progress" aria-label={`${progress}% funded`}><span style={{ width: `${progress}%` }} /></div><p className="muted">{formatUgx(funded)} funded · {progress}%</p></> : null}
                </article>
              );
            })}</div>
          )}
        </section>

        <section className="panel">
          <h2>What happens next?</h2>
          <div className="grid grid-3">
            <div><strong>1. Review</strong><p className="muted">OpFin checks eligibility, affordability and the legal/product structure.</p></div>
            <div><strong>2. Marketplace</strong><p className="muted">Approved requests open to eligible investors with complete disclosures.</p></div>
            <div><strong>3. Funding</strong><p className="muted">You receive funds only after the required funding, acceptance and payment finality steps complete.</p></div>
          </div>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Borrow from investors" description="Request independently reviewed marketplace funding."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load your marketplace requests."} /></Screen>;
  }
}
