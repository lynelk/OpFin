import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { lendingPlatformRequest, type LendingConfiguration } from "@/lib/api/lending-platform";
import { assuranceRequest, type AssuranceRegister } from "@/lib/api/institutional-assurance";
import { institutionalAssuranceAction } from "@/app/institutional-assurance-actions";

type Params = { institution_id?: string; error?: string; saved?: string };
function Field({ label, name, type = "text" }: { label: string; name: string; type?: string }) {
  return <label className="field">{label}<input name={name} type={type} required /></label>;
}
function ReviewForm({ institution, recordId, shared, capacities }: {
  institution: number; recordId: number; shared: boolean; capacities: string[]
}) {
  return <details><summary>Record independent technical review</summary>
    <form action={institutionalAssuranceAction} className="form-grid">
      <input type="hidden" name="institution_id" value={institution} />
      <input type="hidden" name="record_id" value={recordId} />
      <input type="hidden" name="kind" value={shared ? "shared-review" : "sign"} />
      <label className="field">Independent reviewing capacity<select name="capacity" required>
        {capacities.map(role => <option value={role} key={role}>{role.replaceAll("_", " ")}</option>)}
      </select></label>
      <Field label="Genuine signed review document reference" name={shared ? "review_reference" : "report_reference"} />
      <Field label="SHA-256 of the genuine signed report" name={shared ? "review_document_sha256" : "report_sha256"} />
      <label className="field">Reviewer's substantive conclusion<textarea name={shared ? "technical_conclusion" : "signed_statement"} rows={3} required /></label>
      <button className="button">Record authenticated reviewer declaration</button>
    </form>
  </details>;
}
export default async function InstitutionalAssurancePage({ searchParams }: { searchParams?: Promise<Params> }) {
  const params = await searchParams;
  const token = await getAccessToken();
  let lenders: LendingConfiguration;
  try { lenders = await lendingPlatformRequest<LendingConfiguration>("", token); }
  catch (error) { return <Screen title="Institutional finance assurance" description="Source-to-ledger evidence controls.">
    <StateNotice state="server" message={error instanceof Error ? error.message : "Unable to access institutional finance records."} />
  </Screen>; }
  const institution = Number(params?.institution_id ?? 0);
  let register: AssuranceRegister | null = null;
  let error = params?.error ?? null;
  if (institution > 0) {
    try { register = await assuranceRequest<AssuranceRegister>(`?institution_id=${institution}`, token); }
    catch (e) { error = e instanceof Error ? e.message : "The source register is unavailable."; }
  }
  const capacities = register?.required_review_capacities ?? ["treasury", "finance", "risk", "independent_model", "legal"];
  const hidden = <input type="hidden" name="institution_id" value={institution} />;
  return <Screen title="Institutional finance assurance" description="Independent source reconciliation, model validation and contractual recognition. Signing records evidence, never certifies missing documents or automatically enables shared lending.">
    <section className="panel"><h2>Select lender and cut-off</h2><form className="form-grid" method="get">
      <label className="field">Legal lending institution<select name="institution_id" defaultValue={institution || ""} required>
        <option value="">Select an institution</option>
        {lenders.institutions.map(item => <option key={item.id} value={item.id}>{item.id} · {item.name}</option>)}
      </select></label><button className="button">Load assurance evidence</button>
    </form>
      <p><Link href="/admin/institutional-ecl">Open ECL policies, lender loan-book imports and monthly reports</Link></p>
    </section>
    {error ? <StateNotice state="validation" message={error} /> : null}
    {params?.saved ? <StateNotice state="success" message="Evidence operation recorded. Recheck independent source and reviewer state." /> : null}
    {register && <>
      {register.external_evidence_requirement === "deferred_for_nonproduction_modelling" && <section className="panel">
        <h2>Development exception: documentary evidence deferred</h2>
        <p>External institution/provider and lender statements, executed risk-sharing agreements, independently validated IFRS 9 methodology, and actual professional sign-offs have been deferred for synthetic engineering work. They are neither certified nor waived for live lending.</p>
        <p><Link href="/admin/institutional-assurance/preview">Test provisional shared-credit accounting (no live entries)</Link>. The preview never posts cash, loans or journal entries, and the production API refuses it.</p>
      </section>}
      <section className="panel"><h2>External bank, provider and lender-ledger comparison</h2>
        <p>A declared hash is not proof of authenticity. Enter amounts only from actual independent bank/provider and lender records. Variances, omitted providers, stale signed ECL cuts and unmatched balances block approval.</p>
        <table className="table"><thead><tr><th>Cut-off</th><th>Currency</th><th>Control status</th><th>Review</th></tr></thead><tbody>
          {register.reconciliations.map(item => {
            const ctl = typeof item.control_results === "string" ? JSON.parse(item.control_results) : item.control_results;
            return <tr key={item.id}><td>{item.as_of_date} · run {item.ecl_run_id}</td><td>{item.currency}</td>
              <td>{item.status}{(ctl as {balanced?: boolean}).balanced ? " · matched" : " · unresolved variance"}</td>
              <td>{item.status === "draft" ? <form action={institutionalAssuranceAction}>
                {hidden}<input type="hidden" name="kind" value="approve" />
                <input type="hidden" name="record_id" value={item.id} />
                <button className="button" disabled={!(ctl as {balanced?: boolean}).balanced}>Independently check totals</button>
              </form> : <ReviewForm institution={institution} recordId={item.id} shared={false} capacities={capacities} />}</td>
            </tr>;
          })}
        </tbody></table>
        <details><summary>Stage genuine external-source reconciliation</summary>
          <form action={institutionalAssuranceAction} className="form-grid">
            {hidden}<input type="hidden" name="kind" value="stage" />
            <Field label="Signed ECL run ID" name="ecl_run_id" type="number" />
            <Field label="Source statement cut-off" name="as_of_date" type="date" />
            <Field label="Currency (e.g. UGX)" name="currency" />
            <label className="field">All provider bank statements as JSON array
              <textarea name="provider_statements" rows={5} required placeholder={'[{"provider":"provider_code","balance_minor":0,"statement_reference":"ACTUAL-STATEMENT-ID","statement_sha256":"64-character hash"}]'} />
            </label>
            <Field label="Lender funds payable per signed external ledger (minor units)" name="external_payable_minor" type="number" />
            <Field label="Lender gross loan principal (minor units)" name="external_principal_minor" type="number" />
            <Field label="Lender loan count" name="external_loan_count" type="number" />
            <Field label="Original signed lender general ledger reference" name="lender_ledger_reference" />
            <Field label="SHA-256 of the actual signed lender general ledger" name="lender_ledger_sha256" />
            <button className="button">Compare actual source balances with GL</button>
          </form>
        </details>
      </section>
      <section className="panel"><h2>Independent reviewer register</h2>
        <p>All five capacities require separate people with authentic authority and substantive source-backed opinions. Entering a reviewer declaration is not external auditor accreditation.</p>
        <table className="table"><thead><tr><th>Cut-off</th><th>Capacity</th><th>Reviewer ID</th><th>Report</th></tr></thead><tbody>
          {register.reviews.map(item => <tr key={item.id}><td>{item.reconciliation_id}</td><td>{item.capacity}</td>
            <td>{item.reviewed_by}</td><td>{item.report_reference}</td></tr>)}
        </tbody></table>
      </section>
      <section className="panel"><h2>Shared lending: classify legal form before designing journals</h2>
        <p>Direct joint creditor, funded participation, a financial guarantee and a first-loss arrangement are not interchangeable. Even five recorded reviews cannot enable origination until exact cash, loan-asset, fee, ECL, recovery and guarantee journals pass Finance UAT.</p>
        <table className="table"><thead><tr><th>Offer</th><th>Contract</th><th>Platform risk share</th><th>Approval state</th><th>Review</th></tr></thead><tbody>
          {register.classifications.map(item => <tr key={item.id}><td>{item.credit_offer_id}</td><td>{item.contract_form}</td>
            <td>{item.opfin_share_bps} bps</td><td>{item.status}</td>
            <td><ReviewForm institution={institution} recordId={item.id} shared capacities={capacities} /></td></tr>)}
        </tbody></table>
        <details><summary>Propose source-backed contractual classification</summary><form action={institutionalAssuranceAction} className="form-grid">
          {hidden}<input type="hidden" name="kind" value="shared" />
          <Field label="Existing shared-risk credit offer ID" name="credit_offer_id" type="number" />
          <Field label="Reconciled lender/GL cut ID" name="reconciliation_id" type="number" />
          <label className="field">Contractual legal form<select name="contract_form">
            <option value="direct_joint_creditor">Direct co-lender with separate legal debtor rights</option>
            <option value="funded_participation">Funded participation</option>
            <option value="financial_guarantee">Financial guarantee contract</option>
            <option value="first_loss">First-loss or subordinated exposure</option>
          </select></label>
          <Field label="OpFin economic share (basis points)" name="opfin_share_bps" type="number" />
          <Field label="Executed agreement reference" name="contract_reference" />
          <Field label="Executed agreement file SHA-256" name="signed_contract_sha256" />
          <Field label="Documented accounting policy reference" name="accounting_policy_reference" />
          <Field label="Legal/IFRS accounting memorandum SHA-256" name="technical_memo_sha256" />
          <button className="button">Record proposal (no live release)</button>
        </form></details>
      </section>
      <section className="panel"><h2>Release gate</h2>
        <p><strong>Shared origination disabled.</strong> External bank acceptance, lender trial-balance reconciliation, independent IFRS 9 model sign-off and contract-specific ledger UAT are mandatory. Reviewed documents cannot bypass a missing accounting implementation.</p>
      </section>
    </>}
  </Screen>;
}
