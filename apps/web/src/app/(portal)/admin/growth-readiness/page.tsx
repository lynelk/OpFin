import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { OpfinApiError, classifyStatus } from "@/lib/api/errors";

type Product = { product_id: number; product_category: string | null; country: string | null; currency: string | null; terms_days: number[]; configuration_status: string; available_to_customer: boolean | null; funding_and_settlement_status: string };
type Mandate = { mandate_id: number; mandate_type: string; status: string; remaining_committed_capital_minor: number; settled_funding_verified: boolean | null; investor_participation_live: boolean | null };
type Provider = { layout_sample_evidence: string; independent_issuer_authenticity: string; production_accepted: boolean };
type Growth = { as_of: string; products: Product[]; capital_mandates: Mandate[]; summary: { configured_products: number; mandates_configured_for_review: number }; statement_provider_readiness: Record<string, Provider>; growth_policy: string };
function status(value: boolean | null): string { return value === true ? "Verified" : value === false ? "Not approved" : "Not verified"; }
async function getReadiness(token: string): Promise<Growth> {
  const base = process.env.NEXT_PUBLIC_OPFIN_API_URL;
  if (!base) throw new OpfinApiError("server", "OpFin API is not configured.");
  const response = await fetch(`${base}/admin/growth-readiness`, { headers: { Accept: "application/json", Authorization: `Bearer ${token}` }, cache: "no-store" });
  const envelope = await response.json();
  if (!response.ok) throw new OpfinApiError(classifyStatus(response.status), envelope.message || "Could not load readiness.", response.status);
  return envelope.data as Growth;
}
export default async function GrowthReadinessPage() {
  try {
    const token = await getAccessToken();
    if (!token) throw new OpfinApiError("unauthorized", "Please sign in.");
    const data = await getReadiness(token);
    return <Screen title="Lending and investor growth" description="Expand available services now while distinguishing configuration from verified financial activation.">
      <section className="panel">
        <p className="eyebrow">GROWTH FROM DAY ONE</p>
        <h2>Know what can actually be offered</h2>
        <p>{data.growth_policy}</p>
        <p className="muted">As at {new Date(data.as_of).toLocaleString("en-UG")}. This view cannot authorise or move funds.</p>
        <div className="grid grid-3">
          <article className="experience-card"><p className="eyebrow">CONFIGURED LENDING PRODUCTS</p><strong className="stat">{data.summary.configured_products}</strong><p className="muted">Not proof of funded offers</p></article>
          <article className="experience-card"><p className="eyebrow">CAPITAL MANDATES FOR REVIEW</p><strong className="stat">{data.summary.mandates_configured_for_review}</strong><p className="muted">Not verified settled capital</p></article>
          <article className="experience-card"><p className="eyebrow">NEXT STEP</p><strong>Activate eligible channels</strong><p className="muted">Verify legal terms, custody, availability and settlement separately</p></article>
        </div>
      </section>
      <section className="panel">
        <h2>Lending configuration</h2><div style={{overflowX:"auto"}}><table className="table"><thead><tr><th>Product</th><th>Market</th><th>Terms</th><th>Configuration</th><th>Funding</th><th>Customer access</th></tr></thead>
          <tbody>{data.products.map(p => <tr key={p.product_id}><td>#{p.product_id} · {p.product_category || "Uncategorised"}</td><td>{p.country || "Unknown"} / {p.currency || "Unknown"}</td><td>{p.terms_days.map(t => `${t} days`).join(", ") || "None"}</td><td>{p.configuration_status}</td><td>{p.funding_and_settlement_status.replaceAll("_"," ")}</td><td>{status(p.available_to_customer)}</td></tr>)}</tbody>
        </table></div>
        {!data.products.length && <p>No configured lender products.</p>}
        <p><Link href="/admin/lending-platform">Manage lenders, products and channel rules</Link></p>
      </section>
      <section className="panel"><h2>Investor capital mandates</h2><p className="muted">Nominal configured amount, not evidence of investable cash or investor eligibility.</p>
        <div style={{overflowX:"auto"}}><table className="table"><thead><tr><th>Mandate</th><th>Status</th><th>Undeployed amount</th><th>Funding</th><th>Investor access</th></tr></thead><tbody>
          {data.capital_mandates.map(m => <tr key={m.mandate_id}><td>#{m.mandate_id} · {m.mandate_type}</td><td>{m.status}</td><td>{m.remaining_committed_capital_minor.toLocaleString("en-UG")} minor units</td><td>{status(m.settled_funding_verified)}</td><td>{status(m.investor_participation_live)}</td></tr>)}
        </tbody></table></div>{!data.capital_mandates.length && <p>No mandates configured.</p>}
      </section>
      <section className="panel"><h2>Uganda issuer statement readiness</h2><p className="muted">Readable documents do not establish issuer authenticity.</p>
        <div style={{overflowX:"auto"}}><table className="table"><thead><tr><th>Candidate</th><th>Layout evidence</th><th>Issuer verification</th><th>Availability</th></tr></thead><tbody>
          {Object.entries(data.statement_provider_readiness).map(([k,v]) => <tr key={k}><td>{k.replaceAll("_"," ")}</td><td>{v.layout_sample_evidence.replaceAll("_"," ")}</td><td>{v.independent_issuer_authenticity.replaceAll("_"," ")}</td><td>{v.production_accepted ? "Accepted" : "Pending evidence"}</td></tr>)}
        </tbody></table></div>
      </section>
    </Screen>;
  } catch (error) {
    return <Screen title="Lending and investor growth" description="Administrator-only growth readiness."><StateNotice state={error instanceof OpfinApiError ? error.kind : "server"} message={error instanceof Error ? error.message : "Unable to load growth readiness."} /></Screen>;
  }
}
