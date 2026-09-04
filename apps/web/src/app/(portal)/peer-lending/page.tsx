import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { longRangeApi } from "@/lib/api/long-range";
import { OpfinApiError } from "@/lib/api/errors";
import { formatUgx } from "@/lib/format";
import { createPeerInvestmentAction } from "@/app/focused-long-range-actions";

function text(value: unknown): string { return typeof value === "string" ? value : String(value ?? ""); }
function amount(value: unknown): number { return typeof value === "number" ? value : Number(value ?? 0); }
function disclosures(value: unknown): Record<string, unknown> {
  try {
    const parsed = JSON.parse(text(value) || "{}");
    return typeof parsed === "object" && parsed !== null ? parsed as Record<string, unknown> : {};
  } catch { return {}; }
}
function statusLabel(value: unknown): string {
  const status = text(value);
  if (status === "awaiting_step_up") return "Verification needed";
  if (status === "provider_processing") return "Payment processing";
  if (status === "settled") return "Invested";
  if (status === "reversed") return "Reversed";
  if (status === "failed") return "Failed";
  return status.replaceAll("_", " ");
}

export default async function PeerLendingPage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};

  try {
    const [marketplace, overview] = await Promise.all([longRangeApi.marketplace(token), longRangeApi.overview(token)]);
    const invested = overview.participatory_commitments.reduce((sum, item) => sum + (text(item.status) === "settled" ? amount(item.amount_minor) : 0), 0);

    return (
      <Screen title="Peer lending" description="Lend to independently reviewed borrowers through the OpFin Marketplace, with risk, return and repayment information shown before you commit.">
        {params.status ? <StateNotice state="success" message={params.status === "settled" ? "Your investment is confirmed." : "Your investment instruction is being processed. OpFin will not show it as settled until provider evidence confirms it."} /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to complete that investment."} /> : null}

        <section className="panel compass-next-action">
          <p className="eyebrow">OPFIN MARKETPLACE</p>
          <h2>Put your money to work by funding verified needs.</h2>
          <p className="muted">Every opportunity is independently reviewed before it appears here. Review expected return, risk, term, repayment pattern and loss treatment before investing.</p>
          <div className="auth-actions">
            <Link className="button" href="#opportunities">View opportunities</Link>
            <Link className="button secondary" href="/peer-lending/borrow">Need funding instead?</Link>
          </div>
        </section>

        <div className="grid grid-3">
          <section className="panel"><h2>Open opportunities</h2><div className="stat">{marketplace.listings.length}</div><p className="muted">Approved requests currently accepting funding.</p></section>
          <section className="panel"><h2>Your confirmed lending</h2><div className="stat">{formatUgx(invested)}</div><p className="muted">Only provider-confirmed settled commitments.</p></section>
          <section className="panel"><h2>Your commitments</h2><div className="stat">{overview.participatory_commitments.length}</div><p className="muted">Includes verification and processing states.</p></section>
        </div>

        <section className="panel" id="opportunities">
          <div className="case-card-head"><div><p className="eyebrow">INVEST</p><h2>Available opportunities</h2></div><span className="badge">{marketplace.listings.length} open</span></div>
          {marketplace.listings.length === 0 ? <StateNotice state="empty" message="There are no approved marketplace opportunities open for funding right now." /> : (
            <div className="case-list">
              {marketplace.listings.map((listing) => {
                const target = amount(listing.target_amount_minor);
                const funded = amount(listing.funded_amount_minor);
                const remaining = Math.max(0, target - funded);
                const progress = target > 0 ? Math.min(100, Math.round((funded / target) * 100)) : 0;
                const disclosure = disclosures(listing.disclosures);
                const expectedReturn = amount(disclosure.expected_return_percent);
                const riskGrade = text(disclosure.risk_grade) || "Not rated";
                const borrowerSummary = text(disclosure.borrower_summary) || "Reviewed borrower summary is contained in the formal marketplace disclosure.";
                const repaymentFrequency = text(disclosure.repayment_frequency) || "See agreement";

                return (
                  <article className="case-card" key={listing.id}>
                    <div className="case-card-head">
                      <div><p className="eyebrow">{text(listing.purpose) || "Funding request"}</p><h3>{formatUgx(target)} target</h3></div>
                      <span className="badge ok">Risk {riskGrade}</span>
                    </div>
                    <div className="grid grid-3">
                      <div><strong>Expected return</strong><p>{expectedReturn > 0 ? `${expectedReturn.toFixed(1)}%` : "See disclosure"}</p></div>
                      <div><strong>Term</strong><p>{amount(listing.term_days)} days</p></div>
                      <div><strong>Repayment</strong><p>{repaymentFrequency}</p></div>
                    </div>
                    <p className="muted">{borrowerSummary}</p>
                    <div className="setup-progress" aria-label={`${progress}% funded`}><span style={{ width: `${progress}%` }} /></div>
                    <p className="muted">{formatUgx(funded)} funded · {formatUgx(remaining)} remaining · {progress}%</p>
                    <details>
                      <summary>Risk & full marketplace disclosures</summary>
                      <div className="grid grid-2">
                        <div><strong>Responsible lender</strong><p className="muted">{text(listing.lender_of_record)}</p></div>
                        <div><strong>Risk summary</strong><p className="muted">{text(disclosure.risk_summary) || "See formal agreement."}</p></div>
                        <div><strong>Fees</strong><p className="muted">{text(disclosure.fees) || "See formal agreement."}</p></div>
                        <div><strong>Loss treatment</strong><p className="muted">{text(disclosure.loss_allocation) || "See formal agreement."}</p></div>
                        <div><strong>Custody / settlement</strong><p className="muted">{text(disclosure.custody) || "See formal agreement."}</p></div>
                        <div><strong>Guarantee</strong><p className="muted">{text(disclosure.guarantee) || "No guarantee disclosed."}</p></div>
                      </div>
                    </details>
                    <form action={createPeerInvestmentAction} className="form-grid">
                      <input type="hidden" name="listing_id" value={listing.id} />
                      <div className="field"><label htmlFor={`invest-${listing.id}`}>How much would you like to lend? (UGX)</label><input id={`invest-${listing.id}`} name="amount_minor" type="number" min="1000" max={remaining} required /></div>
                      <button className="button" type="submit">Review & confirm investment</button>
                    </form>
                  </article>
                );
              })}
            </div>
          )}
        </section>

        <section className="panel">
          <h2>Your lending activity</h2>
          {overview.participatory_commitments.length === 0 ? <p className="muted">You have not made a marketplace investment yet.</p> : (
            <div className="case-list">{overview.participatory_commitments.map((commitment) => (
              <article className="case-card" key={commitment.id}>
                <div className="case-card-head"><strong>{formatUgx(amount(commitment.amount_minor))}</strong><span className="badge">{statusLabel(commitment.status)}</span></div>
                <p className="muted">Reference {text(commitment.reference)}</p>
              </article>
            ))}</div>
          )}
        </section>

        <section className="panel">
          <h2>Important</h2>
          <p className="muted">Expected return is not guaranteed. Borrowers can default and you may lose money according to the disclosed loss-allocation terms. A commitment is not counted as invested until fresh verification, CPay finality and reconciliation confirm settlement.</p>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Peer lending" description="Fund reviewed opportunities through the OpFin Marketplace."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load the marketplace."} /></Screen>;
  }
}
