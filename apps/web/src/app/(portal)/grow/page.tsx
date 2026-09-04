import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { experienceApi } from "@/lib/api/experience";
import { longRangeApi } from "@/lib/api/long-range";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

function text(value: unknown): string { return typeof value === "string" ? value : String(value ?? ""); }
function amount(value: unknown): number { return typeof value === "number" ? value : Number(value ?? 0); }

export default async function GrowPage() {
  const token = await getAccessToken();
  try {
    const [workspace, marketplace, overview] = await Promise.all([
      experienceApi.investments(token),
      longRangeApi.marketplace(token),
      longRangeApi.overview(token)
    ]);
    const providerInvested = workspace.orders.reduce((sum, order) => sum + (order.status === "settled" ? order.amount_minor : 0), 0);
    const peerInvested = overview.participatory_commitments.reduce((sum, commitment) => sum + (text(commitment.status) === "settled" ? amount(commitment.amount_minor) : 0), 0);

    return (
      <Screen title="Grow" description="Build wealth through suitable provider investments or by lending to independently reviewed borrowers in the OpFin Marketplace.">
        <section className="panel compass-next-action">
          <p className="eyebrow">GROW WITH CONTEXT</p>
          <h2>Choose how you want your money to work.</h2>
          <p className="muted">OpFin keeps conventional investments and peer lending distinct so you can understand the provider, liquidity, risk and settlement model before committing money.</p>
        </section>

        <section className="panel">
          <div className="grid grid-2">
            <article className="case-card">
              <p className="eyebrow">INVEST</p>
              <h3>Provider investments</h3>
              <p className="muted">Use suitability, risk tolerance and time horizon to explore independently activated investment products.</p>
              <Link className="button" href="/investments">{workspace.suitability ? "Explore suitable products" : "Complete suitability"}</Link>
            </article>
            <article className="case-card">
              <p className="eyebrow">LEND</p>
              <h3>Peer lending</h3>
              <p className="muted">Fund reviewed borrowers with expected return, risk grade, repayment pattern and loss treatment shown before you invest.</p>
              <Link className="button secondary" href="/peer-lending">Open marketplace</Link>
            </article>
          </div>
        </section>

        <div className="grid grid-3">
          <section className="panel"><h2>Provider positions</h2><div className="stat">{formatUgx(providerInvested)}</div><p className="muted">Settled provider investment orders.</p></section>
          <section className="panel"><h2>Peer lending</h2><div className="stat">{formatUgx(peerInvested)}</div><p className="muted">Confirmed marketplace lending only.</p></section>
          <section className="panel"><h2>Open peer opportunities</h2><div className="stat">{marketplace.listings.length}</div><p className="muted">Independently approved requests accepting funding.</p></section>
        </div>

        <section className="panel">
          <h2>Growth guardrails</h2>
          <div className="grid grid-3">
            <div><strong>Know the risk</strong><p className="muted">Higher expected return can mean higher loss risk. OpFin does not present returns as guaranteed.</p></div>
            <div><strong>Know who is responsible</strong><p className="muted">The responsible provider or lender of record is named before you commit.</p></div>
            <div><strong>Know when money moved</strong><p className="muted">Orders, commitments, provider acceptance and settlement remain separate states.</p></div>
          </div>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Grow" description="Invest and lend through governed products."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load Grow."} /></Screen>;
  }
}
