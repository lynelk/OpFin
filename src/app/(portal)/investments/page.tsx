import { randomUUID } from "crypto";
import { revalidatePath } from "next/cache";
import { Screen, StateNotice } from "@/components/Screen";
import { experienceApi } from "@/lib/api/experience";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

async function saveSuitability(formData: FormData) {
  "use server";
  const token = await getAccessToken();
  await experienceApi.saveSuitability({
    risk_tolerance: String(formData.get("risk_tolerance")),
    investment_horizon: String(formData.get("investment_horizon")),
    liquidity_need: String(formData.get("liquidity_need")),
    experience_level: String(formData.get("experience_level")),
    answers: { self_assessed: true }
  }, token);
  revalidatePath("/investments");
  revalidatePath("/grow");
}

async function createOrder(formData: FormData) {
  "use server";
  const token = await getAccessToken();
  const productId = Number(formData.get("product_id"));
  await experienceApi.createInvestmentOrder(productId, {
    amount_minor: Number(formData.get("amount_minor")),
    idempotency_key: randomUUID(),
    disclosure_acknowledged: true
  }, token);
  revalidatePath("/investments");
  revalidatePath("/grow");
}

export default async function InvestmentsPage() {
  const token = await getAccessToken();
  try {
    const workspace = await experienceApi.investments(token);
    return (
      <Screen title="Investments" description="Assess suitability first, then view only active products whose risk is compatible with your current profile. Provider settlement remains a separate governed step.">
        <section className="panel compass-next-action">
          <p className="eyebrow">SUITABILITY BEFORE SALES</p>
          <h2>{workspace.suitability ? `Current risk profile: ${workspace.suitability.risk_tolerance}` : "Complete your suitability check"}</h2>
          <p className="muted">Settlement capability: {workspace.settlement_status}. An order marked pending is not proof that assets have been purchased or settled.</p>
        </section>

        <section className="panel">
          <h2>Suitability assessment</h2>
          <form action={saveSuitability} className="experience-form-row" style={{ marginTop: 18 }}>
            <div className="field"><label htmlFor="risk">Risk tolerance</label><select id="risk" name="risk_tolerance" defaultValue={workspace.suitability?.risk_tolerance ?? "low"}><option value="low">Low</option><option value="moderate">Moderate</option><option value="high">High</option></select></div>
            <div className="field"><label htmlFor="horizon">Investment horizon</label><select id="horizon" name="investment_horizon" defaultValue={workspace.suitability?.investment_horizon ?? "1-3-years"}><option value="under-1-year">Under 1 year</option><option value="1-3-years">1–3 years</option><option value="3-plus-years">3+ years</option></select></div>
            <div className="field"><label htmlFor="liquidity">Liquidity need</label><select id="liquidity" name="liquidity_need" defaultValue={workspace.suitability?.liquidity_need ?? "medium"}><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select></div>
            <div className="field"><label htmlFor="experience">Experience</label><select id="experience" name="experience_level" defaultValue={workspace.suitability?.experience_level ?? "new"}><option value="new">New investor</option><option value="some">Some experience</option><option value="experienced">Experienced</option></select></div>
            <button className="button" type="submit">Save assessment</button>
          </form>
        </section>

        <section className="panel">
          <div className="case-card-head"><div><h2>Available products</h2><p className="muted">Only products explicitly activated by independent product approval appear here.</p></div><span className="badge">{workspace.products.length}</span></div>
          {workspace.products.length === 0 ? <StateNotice state="empty" message="No approved investment products are currently available." /> : (
            <div className="experience-card-grid">
              {workspace.products.map((product) => (
                <article className="experience-card" key={product.id}>
                  <div className="case-card-head"><strong>{product.name}</strong><span className="badge">{product.risk_level} risk</span></div>
                  <p className="muted">Provider: {product.provider_name} · {product.product_type.replaceAll("_", " ")}</p>
                  <div className="stat">From {formatUgx(product.minimum_investment_minor)}</div>
                  <div className="experience-disclosure">Review provider identity, fees, liquidity, loss risk and all product disclosures before placing an order. OpFin does not imply a guaranteed return.</div>
                  {workspace.suitability ? (
                    <form action={createOrder} className="form-grid">
                      <input type="hidden" name="product_id" value={product.id} />
                      <div className="field"><label htmlFor={`amount-${product.id}`}>Amount, UGX</label><input id={`amount-${product.id}`} name="amount_minor" type="number" min={product.minimum_investment_minor} step="1000" required /></div>
                      <label><input type="checkbox" required /> I reviewed and acknowledge the product disclosures.</label>
                      <button className="button" type="submit">Create investment order</button>
                    </form>
                  ) : <p className="muted">Complete suitability before placing an order.</p>}
                </article>
              ))}
            </div>
          )}
        </section>

        <section className="panel">
          <h2>Orders</h2>
          {workspace.orders.length === 0 ? <p className="muted">No investment orders yet.</p> : <div className="case-list">{workspace.orders.map((order) => <article className="case-card" key={order.id}><div className="case-card-head"><strong>{order.product_name}</strong><span className="badge">{order.status.replaceAll("_", " ")}</span></div><p>{formatUgx(order.amount_minor)} · {order.provider_name}</p><p className="muted">Created {new Date(order.created_at).toLocaleString("en-UG")}. Pending/provider status is not settlement confirmation.</p></article>)}</div>}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Investments" description="Suitable, provider-governed investment access."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load investments."} /></Screen>;
  }
}
