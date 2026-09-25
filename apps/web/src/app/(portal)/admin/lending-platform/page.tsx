import Link from "next/link";
import { saveLendingConfiguration } from "@/app/lending-platform-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { lendingPlatformRequest, type LendingConfiguration } from "@/lib/api/lending-platform";
import { getAccessToken } from "@/lib/auth/session";

function Field({ name, label, value, required = false, type = "text" }: { name: string; label: string; value?: string; required?: boolean; type?: string }) {
  return <label className="field">{label}<input name={name} defaultValue={value} required={required} type={type} step={type === "number" ? "any" : undefined} /></label>;
}
function Status() { return <label className="field">Status<select name="status" defaultValue="Inactive"><option>Inactive</option><option>Active</option></select></label>; }

export default async function LendingPlatformPage({ searchParams }: { searchParams?: Promise<{ error?: string; saved?: string }> }) {
  const params = await searchParams;
  let data: LendingConfiguration;
  try { data = await lendingPlatformRequest<LendingConfiguration>("", await getAccessToken()); }
  catch (error) { return <Screen title="Lending platform" description="Lender products and affiliated credit deployment."><StateNotice state="server" message={error instanceof Error ? error.message : "Unable to load lending configuration."} /></Screen>; }
  const lenders = <><option value="">Select lender</option>{data.institutions.map((lender) => <option value={lender.id} key={lender.id}>{lender.name} — {lender.country}</option>)}</>;
  const products = <><option value="">Select product</option>{data.products.map((product) => <option value={product.id} key={product.id}>{product.name} — {product.country}</option>)}</>;
  return <Screen title="Lending platform" description="Manage independent and Core Synergies lending through one platform. Each lender retains its own products, funding and credit records.">
    {params?.error ? <StateNotice state="validation" message={params.error} /> : null}
    {params?.saved ? <StateNotice state="success" message="Configuration saved and audit recorded." /> : null}
    <section className="panel"><h2>Affiliated credit deployment</h2><p><strong>{data.strategy.mode.replaceAll("_", " ")}</strong> — {data.strategy.reason}</p><p className="muted">This changes new lending. Existing repayment obligations continue. Activated credit markets: {data.enabled_countries.join(", ")}.</p>
      {data.can_set_strategy ? <form action={saveLendingConfiguration} className="form-grid"><input type="hidden" name="kind" value="strategy" />
        <label className="field">Deployment strategy<select name="mode" defaultValue={data.strategy.mode}><option value="withhold">Withhold affiliated credit</option><option value="external_first">External lenders first; affiliated fallback</option><option value="affiliated_first">Prioritise affiliated credit</option></select></label>
        <Field name="max_affiliated_loan_minor" label="Maximum affiliated loan (minor units; optional)" type="number" />
        <Field name="reason" label="Reason for this deployment decision" required /><Field name="effective_to" label="Expires at (ISO 8601 with timezone; optional)" />
        <button className="button">Record deployment decision</button></form> : <p>A platform administrator controls deployment strategy.</p>}
    </section>
    <section className="panel"><h2>Lenders</h2><table className="table"><thead><tr><th>ID / lender</th><th>Relationship</th><th>Country / authority</th><th>Status</th></tr></thead><tbody>{data.institutions.map((lender) => <tr key={lender.id}><td>{lender.id} · {lender.name}</td><td>{lender.lender_relationship}</td><td>{lender.country} · {lender.regulator_code ?? lender.authority_basis}</td><td>{lender.status}</td></tr>)}</tbody></table>
      <details><summary>Add or update a lender</summary><p>For Core Synergies, select Affiliated and enter its actual legal and licence details. No partner login is needed.</p><form action={saveLendingConfiguration} className="form-grid"><input type="hidden" name="kind" value="institution" />
        <Field name="id" label="Existing lender ID (blank to create)" type="number" /><Field name="name" label="Legal lender name" required /><Field name="country" label="Country code" value="UG" required />
        <label className="field">Relationship<select name="lender_relationship"><option value="independent">Independent</option><option value="affiliated">Affiliated — Core Synergies or group entity</option></select></label><Status />
        <Field name="regulator_code" label="Regulator, where applicable" /><Field name="licence_class" label="Regulatory pricing policy class" />
        <label className="field">Authority basis<select name="authority_basis"><option value="pending">Evidence pending</option><option value="licensed">Licensed</option><option value="other_authority">Other authority</option><option value="exempt">Documented exemption</option></select></label>
        <Field name="authority_reference" label="Licence / authority / exemption evidence reference" /><Field name="authority_valid_until" label="Authority valid until (if applicable)" type="date" />
        <Field name="address" label="Business address" required /><Field name="email" label="Contact email" type="email" required /><Field name="phone" label="Contact phone" required />
        <label><input type="checkbox" name="rate_change_approval_required" defaultChecked /> This lender requires regulatory approval evidence for rate changes</label><button className="button">Save lender</button>
      </form></details></section>
    <section className="panel"><h2>Product catalogue</h2><table className="table"><thead><tr><th>ID / product</th><th>Market</th><th>Category</th><th>Terms</th></tr></thead><tbody>{data.products.map((product) => <tr key={product.id}><td>{product.id} · {product.name}</td><td>{product.country} / {product.currency}</td><td>{product.product_category}</td><td>{product.terms.map((term) => `${term.duration} days (${term.status})`).join(", ") || "No terms configured"}</td></tr>)}</tbody></table>
      <details><summary>Add or update a product</summary><form action={saveLendingConfiguration} className="form-grid"><input type="hidden" name="kind" value="product" /><Field name="id" label="Existing product ID (blank to create)" type="number" />
        <label className="field">Lender<select name="institution_id" required>{lenders}</select></label><Field name="name" label="Product name" required /><Field name="product_category" label="Product classification" value="personal_loan" required /><Status />
        <Field name="country" label="Country code" value="UG" required /><Field name="currency" label="Currency code" value="UGX" required /><Field name="min_amount_minor" label="Minimum amount (minor units)" type="number" value="1" required /><Field name="max_amount_minor" label="Maximum amount (minor units; optional)" type="number" />
        <Field name="borrower_purposes" label="Borrower purposes (comma-separated; blank for all)" /><Field name="funding_pool_id" label="Approved funding pool ID" type="number" /><button className="button">Save product</button></form></details>
      <details><summary>Add a repayment term</summary><form action={saveLendingConfiguration} className="form-grid"><input type="hidden" name="kind" value="term" /><label className="field">Product<select name="loan_product_id" required>{products}</select></label>
        <Field name="duration" label="Duration in days" type="number" required /><Field name="interest_rate" label="Interest rate (%)" type="number" required />
        <label className="field">Interest method<select name="interest_type"><option value="Flat">Flat</option><option value="reducing_balance">Reducing balance</option></select></label>
        <label className="field">Interest cycle<select name="interest_cycle">{["daily", "weekly", "monthly", "annual"].map((cycle) => <option key={cycle}>{cycle}</option>)}</select></label>
        <label className="field">Repayment frequency<select name="repayment_frequency">{["daily", "weekly", "fortnightly", "monthly"].map((cycle) => <option key={cycle}>{cycle}</option>)}</select></label><Status /><button className="button">Create term</button></form></details>
      <p><Link href="/admin/essentials">Manage Essentials products and funding mandates</Link></p>
    </section>
    <section className="panel"><h2>Channel availability</h2><p>Keep a product configured even where publication requires review. Record the applicable store decision or policy evidence; lender authority alone does not establish store acceptance.</p>
      <table className="table"><thead><tr><th>Channel / scope</th><th>Revision</th><th>Availability</th><th>Reason / evidence</th></tr></thead><tbody>{data.distribution_rules.map((rule) => <tr key={rule.id}><td>{rule.channel} / {rule.country} / {rule.product_category}</td><td>{rule.version}</td><td>{rule.availability}</td><td>{rule.reason} · {rule.source_reference}</td></tr>)}</tbody></table>
      {data.can_set_strategy ? <details><summary>Record a distribution policy revision</summary><form action={saveLendingConfiguration} className="form-grid"><input type="hidden" name="kind" value="distribution" />
        <label className="field">Channel<select name="channel">{data.channels.filter((channel) => channel !== "android").map((channel) => <option key={channel}>{channel}</option>)}</select></label>
        <Field name="country" label="Country code or * for all" value="UG" required /><Field name="product_category" label="Product classification or * for all" value="personal_loan" required /><label className="field">Lender scope<select name="institution_id"><option value="">All lenders</option>{data.institutions.map((lender) => <option value={lender.id} key={lender.id}>{lender.name}</option>)}</select></label>
        <Field name="loan_product_id" label="Loan product ID (optional)" type="number" /><Field name="partner_product_id" label="Essentials partner product ID (optional; choose one product scope)" type="number" />
        <label className="field">Availability<select name="availability"><option value="review">Review required</option><option value="available">Available under recorded policy</option><option value="unavailable">Unavailable on this channel</option></select></label>
        <Field name="min_duration_days" label="Minimum duration (days; where applicable)" type="number" /><Field name="max_apr_percent" label="Maximum APR (%; where applicable)" type="number" /><Field name="reason" label="Reason to communicate to the partner" required /><Field name="source_reference" label="Policy / review evidence reference" required /><Field name="source_url" label="Official source URL (optional)" type="url" /><Field name="effective_to" label="Expires at (ISO 8601 with timezone; optional)" /><button className="button">Record policy revision</button></form></details> : null}
    </section>
    {data.can_set_strategy ? <section className="panel"><h2>Platform credit access</h2><p>Delegate lender management to an operations user. Deployment strategy, lender ownership and distribution policy remain controlled by platform administrators.</p><form action={saveLendingConfiguration} className="form-grid"><input type="hidden" name="kind" value="access" /><Field name="user_id" label="Operations user ID" type="number" required /><label className="field">Access<select name="enabled"><option value="true">Grant</option><option value="false">Revoke</option></select></label><button className="button">Update access</button></form></section> : null}
  </Screen>;
}
