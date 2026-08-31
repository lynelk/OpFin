import Link from "next/link";
import { Screen } from "@/components/Screen";
import { createCapitalMandateAction, createDistributionPartnerAction } from "@/app/long-range-actions";

export default function LongRangeSetupPage() {
  return (
    <Screen title="Extended finance setup" description="Create governed capital mandates and distribution partners as drafts for independent review. Creation never activates a partner, deploys capital or authorises money movement by itself.">
      <Link href="/admin/long-range">← Back to extended finance operations</Link>
      <section className="panel">
        <h2>Capital mandate</h2>
        <p className="muted">Define the mandate and investment policy. A different authorised operator must approve it before activation.</p>
        <form action={createCapitalMandateAction} className="form-grid">
          <div className="grid grid-2">
            <div className="field"><label htmlFor="mandate_type">Mandate type</label><select id="mandate_type" name="mandate_type" defaultValue="private_loan_book"><option value="private_loan_book">Private loan book</option><option value="managed_capital">Managed capital</option><option value="co_lending">Co-lending</option><option value="warehouse_line">Warehouse line</option></select></div>
            <div className="field"><label htmlFor="mandate_name">Name</label><input id="mandate_name" name="name" required /></div>
          </div>
          <div className="field"><label htmlFor="committed_capital_minor">Committed capital (UGX)</label><input id="committed_capital_minor" name="committed_capital_minor" type="number" min="0" defaultValue="0" /></div>
          <div className="field"><label htmlFor="investment_policy">Investment policy JSON</label><textarea id="investment_policy" name="investment_policy" rows={6} defaultValue={'{"eligible_products":[],"concentration_limits":{},"loss_limits":{}}'} required /></div>
          <button className="button" type="submit">Create for compliance review</button>
        </form>
      </section>
      <section className="panel">
        <h2>Distribution partner</h2>
        <p className="muted">Due diligence and allowed-product scope remain explicit. A partner is never given access merely because its name appears in a CRM field.</p>
        <form action={createDistributionPartnerAction} className="form-grid">
          <div className="grid grid-2">
            <div className="field"><label htmlFor="partner_name">Partner name</label><input id="partner_name" name="partner_name" required /></div>
            <div className="field"><label htmlFor="partner_type">Partner type</label><select id="partner_type" name="partner_type" defaultValue="employer"><option value="employer">Employer</option><option value="sacco">SACCO</option><option value="merchant">Merchant</option><option value="lender">Lender</option><option value="insurer">Insurer</option><option value="investment_provider">Investment provider</option><option value="agent">Agent</option><option value="aggregator">Aggregator</option></select></div>
          </div>
          <div className="field"><label htmlFor="allowed_products">Allowed product codes JSON</label><textarea id="allowed_products" name="allowed_products" rows={4} defaultValue="[]" required /></div>
          <div className="field"><label htmlFor="commercial_terms">Commercial terms JSON</label><textarea id="commercial_terms" name="commercial_terms" rows={5} defaultValue="{}" /></div>
          <button className="button" type="submit">Create for due diligence</button>
        </form>
      </section>
    </Screen>
  );
}
