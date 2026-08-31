import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { longRangeApi } from "@/lib/api/long-range";
import { OpfinApiError } from "@/lib/api/errors";
import { formatUgx } from "@/lib/format";
import {
  createParticipatoryListingAction,
  createReferralAction,
  joinCommunityAction,
  linkFinancialAccountAction,
  requestAssetFinanceAction,
  saveHouseholdAction,
  saveMicrobusinessAction
} from "@/app/long-range-actions";

function text(value: unknown): string { return typeof value === "string" ? value : ""; }
function amount(value: unknown): number { return typeof value === "number" ? value : Number(value ?? 0); }

export default async function EcosystemPage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};

  try {
    const workspace = await longRangeApi.overview(token);
    const household = workspace.household;
    const business = workspace.microbusiness;
    const gates = Object.entries(workspace.capabilities).filter(([, capability]) => capability.external_gate);

    return (
      <Screen title="Connected financial life" description="Connect the parts of your financial life that matter, then use them only when a journey genuinely needs them. Provider-confirmed data stays visibly separate from what you entered yourself.">
        {params.status ? <StateNotice state="success" message={`Completed: ${params.status.replaceAll("-", " ")}.`} /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to complete that action."} /> : null}

        <section className="panel compass-next-action">
          <p className="eyebrow">ONE CONNECTED RECORD</p>
          <h2>Use more context without creating more passwords, profiles or competing financial identities.</h2>
          <p className="muted">Household, business, community, asset and participatory-finance journeys reuse your OpFin identity, permissions and Financial Passport. No external balance or membership becomes confirmed merely because it was typed into a form.</p>
        </section>

        <section className="panel">
          <h2>Connect money sources</h2>
          <p className="muted">Linked accounts start as user-declared and become provider-confirmed only after independent verification.</p>
          <div className="grid grid-2">
            <form action={linkFinancialAccountAction} className="form-grid">
              <div className="grid grid-2">
                <div className="field"><label htmlFor="account_type">Account type</label><select id="account_type" name="account_type" defaultValue="mobile_money"><option value="mobile_money">Mobile money</option><option value="bank">Bank</option><option value="sacco">SACCO</option><option value="savings">Savings</option><option value="investment">Investment</option><option value="other">Other</option></select></div>
                <div className="field"><label htmlFor="provider">Provider</label><input id="provider" name="provider" required /></div>
              </div>
              <div className="field"><label htmlFor="identifier">Account or phone identifier</label><input id="identifier" name="identifier" required autoComplete="off" /></div>
              <button className="button" type="submit">Connect for verification</button>
            </form>
            <div className="case-list">
              {workspace.linked_accounts.length === 0 ? <p className="muted">No linked accounts yet.</p> : workspace.linked_accounts.map((account) => (
                <article className="case-card" key={account.id}><div className="case-card-head"><strong>{text(account.provider)}</strong><span className="badge">{text(account.status)}</span></div><p>{text(account.account_type).replaceAll("_", " ")} · {text(account.masked_identifier)}</p><p className="muted">Confidence: {text(account.data_confidence).replaceAll("_", " ")}</p></article>
              ))}
            </div>
          </div>
        </section>

        <section className="panel">
          <h2>Family & household</h2>
          <p className="muted">Add only the household context that helps plan commitments, resilience and shared goals. Dependants are not silently made borrowers or guarantors.</p>
          <form action={saveHouseholdAction} className="form-grid">
            <div className="grid grid-3">
              <div className="field"><label htmlFor="household_size">Household size</label><input id="household_size" name="household_size" type="number" min="1" max="50" defaultValue={amount(household?.household_size) || 1} required /></div>
              <div className="field"><label htmlFor="monthly_income_minor">Monthly household income (UGX)</label><input id="monthly_income_minor" name="monthly_income_minor" type="number" min="0" defaultValue={amount(household?.monthly_income_minor)} /></div>
              <div className="field"><label htmlFor="monthly_fixed_costs_minor">Monthly fixed costs (UGX)</label><input id="monthly_fixed_costs_minor" name="monthly_fixed_costs_minor" type="number" min="0" defaultValue={amount(household?.monthly_fixed_costs_minor)} /></div>
            </div>
            <div className="field"><label htmlFor="emergency_target_minor">Emergency target (UGX)</label><input id="emergency_target_minor" name="emergency_target_minor" type="number" min="0" defaultValue={amount(household?.emergency_target_minor)} /></div>
            <button className="button" type="submit">Save household picture</button>
          </form>
        </section>

        <section className="panel">
          <h2>Microbusiness</h2>
          <p className="muted">Keep household and business cash flow distinguishable while allowing OpFin to understand the complete financial picture.</p>
          <form action={saveMicrobusinessAction} className="form-grid">
            <div className="grid grid-2"><div className="field"><label htmlFor="business_name">Business name</label><input id="business_name" name="business_name" defaultValue={text(business?.business_name)} required /></div><div className="field"><label htmlFor="business_type">Business type</label><input id="business_type" name="business_type" defaultValue={text(business?.business_type)} required /></div></div>
            <div className="grid grid-3"><div className="field"><label htmlFor="registration_reference">Registration reference</label><input id="registration_reference" name="registration_reference" defaultValue={text(business?.registration_reference)} /></div><div className="field"><label htmlFor="monthly_revenue_minor">Monthly revenue (UGX)</label><input id="monthly_revenue_minor" name="monthly_revenue_minor" type="number" min="0" defaultValue={amount(business?.monthly_revenue_minor)} /></div><div className="field"><label htmlFor="monthly_expense_minor">Monthly expenses (UGX)</label><input id="monthly_expense_minor" name="monthly_expense_minor" type="number" min="0" defaultValue={amount(business?.monthly_expense_minor)} /></div></div>
            <button className="button" type="submit">Save business picture</button>
          </form>
        </section>

        <section className="panel">
          <h2>Community finance</h2>
          <div className="grid grid-2">
            <form action={joinCommunityAction} className="form-grid"><div className="field"><label htmlFor="institution_type">Community type</label><select id="institution_type" name="institution_type" defaultValue="sacco"><option value="sacco">SACCO</option><option value="vsla">VSLA</option><option value="cooperative">Cooperative</option><option value="association">Association</option><option value="employer_group">Employer group</option></select></div><div className="field"><label htmlFor="institution_name">Institution name</label><input id="institution_name" name="institution_name" required /></div><div className="field"><label htmlFor="member_reference">Member reference</label><input id="member_reference" name="member_reference" /></div><button className="button" type="submit">Add membership for verification</button></form>
            <div className="case-list">{workspace.community_memberships.length === 0 ? <p className="muted">No community memberships recorded.</p> : workspace.community_memberships.map((membership) => <article className="case-card" key={membership.id}><div className="case-card-head"><strong>{text(membership.institution_name)}</strong><span className="badge">{text(membership.status)}</span></div><p className="muted">{text(membership.institution_type).toUpperCase()} · {text(membership.member_reference) || "Membership reference pending"}</p></article>)}</div>
          </div>
        </section>

        <section className="panel">
          <h2>Asset finance</h2>
          <p className="muted">Request financing for a productive or household asset. Location access is optional and purpose-bound, and no deposit moves before independent approval plus step-up confirmation.</p>
          <form action={requestAssetFinanceAction} className="form-grid">
            <div className="grid grid-3"><div className="field"><label htmlFor="asset_category">Category</label><input id="asset_category" name="asset_category" required /></div><div className="field"><label htmlFor="asset_price_minor">Asset price (UGX)</label><input id="asset_price_minor" name="asset_price_minor" type="number" min="1" required /></div><div className="field"><label htmlFor="deposit_minor">Proposed deposit (UGX)</label><input id="deposit_minor" name="deposit_minor" type="number" min="0" defaultValue="0" /></div></div>
            <div className="field"><label htmlFor="asset_description">Asset description</label><input id="asset_description" name="asset_description" required /></div><div className="field"><label htmlFor="requested_term_months">Requested term (months)</label><input id="requested_term_months" name="requested_term_months" type="number" min="1" max="84" required /></div>
            <label><input type="checkbox" name="geolocation_consent" value="true" /> Allow location data only if needed to service this asset-finance request</label>
            <button className="button" type="submit">Submit for assessment</button>
          </form>
          {workspace.asset_finance.length > 0 ? <div className="case-list">{workspace.asset_finance.map((request) => <article className="case-card" key={request.id}><div className="case-card-head"><strong>{text(request.asset_description)}</strong><span className="badge">{text(request.status)}</span></div><p>{formatUgx(amount(request.asset_price_minor))} · {amount(request.requested_term_months)} months</p></article>)}</div> : null}
        </section>

        <section className="panel">
          <h2>Participatory finance</h2>
          <p className="muted">Create a financing request for independent compliance review. Funding cannot open without lender-of-record, loss, fee and custody disclosures; actual commitments require step-up and settle only after CPay finality.</p>
          <form action={createParticipatoryListingAction} className="form-grid"><div className="grid grid-3"><div className="field"><label htmlFor="purpose">Purpose</label><input id="purpose" name="purpose" required /></div><div className="field"><label htmlFor="target_amount_minor">Target amount (UGX)</label><input id="target_amount_minor" name="target_amount_minor" type="number" min="1000" required /></div><div className="field"><label htmlFor="term_days">Term (days)</label><input id="term_days" name="term_days" type="number" min="1" max="730" required /></div></div><div className="field"><label htmlFor="lender_of_record">Lender of record</label><input id="lender_of_record" name="lender_of_record" /></div><div className="grid grid-3"><div className="field"><label htmlFor="loss_allocation">Loss allocation</label><textarea id="loss_allocation" name="loss_allocation" rows={2} /></div><div className="field"><label htmlFor="fees">Fees</label><textarea id="fees" name="fees" rows={2} /></div><div className="field"><label htmlFor="custody">Custody / settlement</label><textarea id="custody" name="custody" rows={2} /></div></div><button className="button" type="submit">Submit financing request</button></form>
        </section>

        <section className="panel">
          <h2>Referrals & rewards</h2><p className="muted">Rewards are earned only after identity and eligibility checks. A referral event alone never creates money.</p>
          <form action={createReferralAction} className="form-grid"><div className="field"><label htmlFor="referred_user_id">Referred OpFin user ID, if already registered</label><input id="referred_user_id" name="referred_user_id" type="number" min="1" /></div><button className="button" type="submit">Record referral</button></form>
          {workspace.referrals.length > 0 ? <div className="case-list">{workspace.referrals.map((referral) => <article className="case-card" key={referral.id}><div className="case-card-head"><strong>{text(referral.referral_code)}</strong><span className="badge">{text(referral.status)}</span></div><p className="muted">Reward: {formatUgx(amount(referral.reward_minor))}</p></article>)}</div> : null}
        </section>

        <section className="panel"><h2>External activation gates</h2><p className="muted">The software capability exists, but these integrations require the named provider, licence, commercial arrangement or production credential before live use. OpFin does not pretend an API contract is a banking licence.</p><div className="case-list">{gates.map(([code, capability]) => <article className="case-card" key={code}><strong>{code.replaceAll("_", " ")}</strong><p className="muted">{capability.external_gate?.replaceAll("_", " ")}</p></article>)}</div></section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Connected financial life" description="Your connected financial context will appear here when available."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load connected financial life."} /></Screen>;
  }
}
