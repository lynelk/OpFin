import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { experienceApi } from "@/lib/api/experience";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function GrowPage() {
  const token = await getAccessToken();
  try {
    const workspace = await experienceApi.investments(token);
    const committed = workspace.orders.reduce((sum, order) => sum + (order.status === "settled" ? order.amount_minor : 0), 0);

    return (
      <Screen title="Grow" description="Move from financial resilience toward longer-term growth through suitability-led, provider-governed products.">
        <section className="panel compass-next-action">
          <p className="eyebrow">GROW WITH CONTEXT</p>
          <h2>{workspace.suitability ? "Your suitability profile is ready" : "Start with suitability, not a product catalogue"}</h2>
          <p className="muted">OpFin separates your financial position, risk tolerance and provider settlement so higher-risk products do not quietly become the default next step.</p>
          <Link className="button compass-action" href="/investments">{workspace.suitability ? "Explore suitable products" : "Complete suitability"}</Link>
        </section>

        <div className="grid grid-3">
          <section className="panel"><h2>Approved products</h2><div className="stat">{workspace.products.length}</div><p className="muted">Only independently activated products.</p></section>
          <section className="panel"><h2>Settled positions</h2><div className="stat">{formatUgx(committed)}</div><p className="muted">Pending orders are deliberately excluded.</p></section>
          <section className="panel"><h2>Settlement rail</h2><div className="stat stat-text">{workspace.settlement_status.toLowerCase()}</div><p className="muted">Provider capability remains explicit.</p></section>
        </div>

        <section className="panel">
          <h2>Growth guardrails</h2>
          <div className="grid grid-3">
            <div><strong>Suitability first</strong><p className="muted">Risk, horizon, liquidity needs and experience are assessed before ordering.</p></div>
            <div><strong>Provider named</strong><p className="muted">Products identify the responsible provider rather than hiding behind OpFin branding.</p></div>
            <div><strong>No fake finality</strong><p className="muted">Order creation, provider acceptance and settlement remain distinct states.</p></div>
          </div>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Grow" description="Build toward longer-term financial goals."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load Grow."} /></Screen>;
  }
}
