import Link from "next/link";
import { institutionalEclAction } from "@/app/institutional-ecl-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { eclRequest, type EclRegister } from "@/lib/api/institutional-ecl";
import { lendingPlatformRequest, type LendingConfiguration } from "@/lib/api/lending-platform";
import { getAccessToken } from "@/lib/auth/session";

type Params = { institution_id?: string; error?: string; saved?: string };
function Field({ label, name, type = "text", value, required = true }: {
  label: string; name: string; type?: string; value?: string; required?: boolean
}) {
  return <label className="field">{label}<input name={name} type={type} defaultValue={value} required={required} /></label>;
}
function Action({ kind, id, institution, label }: { kind: string; id: number; institution: number; label: string }) {
  return <form action={institutionalEclAction}><input type="hidden" name="institution_id" value={institution} />
    <input type="hidden" name="kind" value={kind} /><input type="hidden" name="record_id" value={id} />
    <button className="button" type="submit">{label}</button></form>;
}

export default async function InstitutionalEclPage({ searchParams }: { searchParams?: Promise<Params> }) {
  const params = await searchParams;
  const token = await getAccessToken();
  let lenders: LendingConfiguration;
  try { lenders = await lendingPlatformRequest<LendingConfiguration>("", token); }
  catch (error) {
    return <Screen title="Institutional provisioning" description="Lender-specific expected credit-loss controls.">
      <StateNotice state="server" message={error instanceof Error ? error.message : "Unable to load lenders."} />
    </Screen>;
  }
  const institutionId = Number(params?.institution_id ?? 0);
  let register: EclRegister | null = null;
  let error: string | null = params?.error ?? null;
  if (institutionId > 0) {
    try { register = await eclRequest<EclRegister>(`?institution_id=${institutionId}`, token); }
    catch (e) { error = e instanceof Error ? e.message : "Could not load institutional ECL records."; }
  }
  const hidden = <input type="hidden" name="institution_id" value={institutionId} />;
  return <Screen title="Institutional provisioning" description="Record the actual risk owner. Assess partner portfolios without booking them as OpFin assets; only verified OpFin-owned exposure may post through the existing impairment service.">
    <section className="panel"><h2>Choose legal lending institution</h2><form method="get" className="form-grid">
      <label className="field">Lender<select name="institution_id" defaultValue={institutionId || ""} required>
        <option value="">Select lender</option>{lenders.institutions.map(lender =>
          <option value={lender.id} key={lender.id}>{lender.id} · {lender.name}</option>)}
      </select></label><button className="button">Load portfolio</button></form>
      <p><Link href="/admin/lending-platform">Manage lender legal identity and capital mandates</Link></p></section>
    {error ? <StateNotice state="validation" message={error} /> : null}
    {params?.saved ? <StateNotice state="success" message="Action recorded. Refresh the relevant evidence and verify its state." /> : null}
    {register ? <>
      <section className="panel"><h2>ECL policies</h2><p>All model parameters require documented source evidence and review by a different platform administrator. Stage thresholds are backstops, not sufficient evidence of IFRS 9 compliance.</p>
        <table className="table"><thead><tr><th>Version</th><th>Effective / currency</th><th>Status</th><th>Review</th></tr></thead>
          <tbody>{register.policies.map(policy => <tr key={policy.id}><td>{policy.version}</td><td>{policy.effective_from} · {policy.currency}</td><td>{policy.status}</td>
            <td>{policy.status === "draft" ? <Action kind="policy-approve" institution={institutionId} id={policy.id} label="Approve policy" /> : "Approved"}</td></tr>)}</tbody></table>
        <details><summary>Draft institution ECL policy</summary><form action={institutionalEclAction} className="form-grid">
          {hidden}<input type="hidden" name="kind" value="policy" />
          <Field label="Policy version" name="version" /><Field label="Effective from" name="effective_from" type="date" />
          <Field label="Currency (ISO 4217)" name="currency" value="UGX" /><Field label="Stage 2 DPD backstop" name="stage2_dpd" type="number" value="30" />
          <Field label="Stage 3 DPD backstop" name="stage3_dpd" type="number" value="90" />
          <Field label="Discount basis points (approved model approximation)" name="discount_bps" type="number" />
          <Field label="Model calibration, governance and methodology evidence" name="methodology_reference" />
          {[0, 1].map((i) => <fieldset key={i}><legend>Forward-looking scenario {i + 1}</legend>
            <Field label="Weight (basis points)" name={`weight_bps_${i}`} type="number" />
            <Field label="12-month default PD (basis points)" name={`pd_12m_bps_${i}`} type="number" />
            <Field label="Lifetime default PD (basis points)" name={`pd_lifetime_bps_${i}`} type="number" />
            <Field label="Impaired-loan PD (basis points)" name={`pd_impaired_bps_${i}`} type="number" />
            <Field label="Loss given default (basis points)" name={`lgd_bps_${i}`} type="number" />
          </fieldset>)}
          <p>Scenario weights must total 10,000 bps. No lender PD/LGD assumptions are pre-filled.</p>
          <button className="button">Create pending policy</button>
        </form></details>
      </section>
      <section className="panel"><h2>Contractual loan risk ownership</h2>
        <table className="table"><thead><tr><th>Loan</th><th>Owner</th><th>OpFin risk share</th><th>Status</th><th>Review</th></tr></thead>
          <tbody>{register.ownerships.map(item => <tr key={item.id}><td>{item.loan_id}</td><td>{item.owner_type}</td><td>{item.opfin_share_bps} bps</td><td>{item.status}</td>
            <td>{item.status === "draft" ? <Action kind="ownership-approve" id={item.id} institution={institutionId} label="Approve ownership" /> : "Approved"}</td></tr>)}</tbody></table>
        <details><summary>Attest existing managed loan risk owner</summary><form action={institutionalEclAction} className="form-grid">
          {hidden}<input type="hidden" name="kind" value="ownership" />
          <Field label="OpFin loan ID" name="loan_id" type="number" />
          <label className="field">Legal credit risk owner<select name="owner_type"><option value="partner">Institution/partner</option><option value="opfin">OpFin</option><option value="shared">Contractually shared</option></select></label>
          <Field label="OpFin exposure share, basis points (0–10,000)" name="opfin_share_bps" type="number" />
          <Field label="Executed agreement / risk participation reference" name="agreement_reference" />
          <button className="button">Submit ownership for review</button>
        </form></details>
      </section>
      <section className="panel"><h2>Reviewed institutional CSV loan books</h2>
        <p>Use the canonical six-column CSV format. Each batch is staged against an independently checked loan count and total outstanding principal, then approved by a different administrator. No batch imports create OpFin loans or accounting receivables.</p>
        <p><code>external_loan_reference,principal_outstanding_minor,days_past_due,sicr,credit_impaired,contractual_maturity_date</code></p>
        <p>Use 1 or 0 for the two risk flags and YYYY-MM-DD for maturity. Maximum 2,000 loans or 2 MiB per file. For the reporting date, aggregate the complete set of approved batches in the stated control totals.</p>
        <table className="table"><thead><tr><th>Cut-off / reference</th><th>Loans</th><th>Principal</th><th>Status</th><th>Review</th></tr></thead>
          <tbody>{register.import_batches.map(batch => <tr key={batch.id}><td>{batch.as_of_date} · {batch.source_evidence}</td>
            <td>{batch.loan_count}</td><td>{batch.currency} {batch.principal_total_minor.toLocaleString("en-GB")}</td><td>{batch.status}</td>
            <td>{batch.status === "staged" ? <Action kind="batch-approve" institution={institutionId} id={batch.id} label="Approve entire batch" /> : "Reviewed"}</td></tr>)}</tbody></table>
        <details><summary>Upload institution-issued loan book CSV</summary>
          <form action={institutionalEclAction} className="form-grid" encType="multipart/form-data">{hidden}
            <input type="hidden" name="kind" value="external-csv" />
            <Field label="Reporting date" name="as_of_date" type="date" />
            <Field label="Currency" name="currency" value="UGX" />
            <Field label="MIS source, legal permission and reconciliation reference" name="source_evidence" />
            <Field label="Declared number of loans in this file" name="expected_loan_count" type="number" />
            <Field label="Declared outstanding principal in this file (minor units)" name="expected_principal_minor" type="number" />
            <label className="field">Canonical UTF-8 CSV file<input name="file" type="file" accept=".csv,text/csv" required /></label>
            <button className="button">Stage loan-book file</button>
          </form>
        </details>
      </section>
      <section className="panel"><h2>Institution-issued loans (not OpFin assets)</h2>
        <p>Supply independent source loan observations with a dated control-total statement. Upload and review are separate. For a settled loan, include a zero-balance observation to reconcile movements.</p>
        <table className="table"><thead><tr><th>Loan reference</th><th>As of</th><th>Principal</th><th>Status</th><th>Review</th></tr></thead>
          <tbody>{register.external_snapshots.map(item => <tr key={item.id}><td>{item.external_loan_reference}</td><td>{item.as_of_date}</td><td>{item.principal_outstanding_minor}</td><td>{item.status}</td>
            <td>{item.status === "staged" ? <Action kind="external-approve" id={item.id} institution={institutionId} label="Approve source" /> : "Reviewed"}</td></tr>)}</tbody></table>
        <details><summary>Stage externally issued loan snapshot</summary><form action={institutionalEclAction} className="form-grid">
          {hidden}<input type="hidden" name="kind" value="external" />
          <Field label="Source-system loan reference" name="external_loan_reference" />
          <Field label="Source balance date" name="as_of_date" type="date" />
          <Field label="Currency" name="currency" value="UGX" />
          <Field label="Principal outstanding (minor units)" name="principal_outstanding_minor" type="number" />
          <Field label="Days past due" name="days_past_due" type="number" />
          <label className="field">Significant credit risk increase?<select name="sicr"><option value="false">No</option><option value="true">Yes</option></select></label>
          <label className="field">Credit impaired?<select name="credit_impaired"><option value="false">No</option><option value="true">Yes</option></select></label>
          <Field label="Contractual maturity" name="contractual_maturity_date" type="date" />
          <Field label="MIS and source evidence reference" name="source_evidence" />
          <button className="button">Stage observation</button>
        </form></details>
      </section>
      <section className="panel"><h2>Monthly ECL control and statements</h2><p>A draft requires source loan counts and principal control totals. A different administrator must approve it before any posting. Historical managed-loan snapshots cannot be invented from current live schedules.</p>
        <form action={institutionalEclAction} className="form-grid">{hidden}<input type="hidden" name="kind" value="run" />
          <Field label="Reporting cut-off date" name="as_of_date" type="date" />
          <Field label="External loans in complete extract" name="expected_external_count" type="number" value="0" />
          <Field label="External outstanding principal control total" name="expected_external_principal_minor" type="number" value="0" />
          <Field label="Approved source completeness / reconciliation evidence" name="coverage_reference" />
          <button className="button">Calculate draft ECL</button>
        </form>
        <table className="table"><thead><tr><th>Cut-off</th><th>Exposure</th><th>ECL</th><th>Movement</th><th>State</th><th>Statements / approval</th></tr></thead><tbody>
          {register.runs.map(run => <tr key={run.id}><td>{run.as_of_date} · r{run.revision}</td><td>{run.currency} {run.total_exposure_minor.toLocaleString("en-GB")}</td>
            <td>{run.total_ecl_minor.toLocaleString("en-GB")} (OpFin share {run.platform_ecl_minor.toLocaleString("en-GB")})</td>
            <td>{run.ecl_movement_minor.toLocaleString("en-GB")}</td><td>{run.status}</td><td>
              <Link href={`/api/institutional-ecl/export?run=${run.id}&view=loans`}>Loan CSV</Link>{" · "}
              <Link href={`/api/institutional-ecl/export?run=${run.id}&view=portfolio`}>Portfolio CSV</Link>
              {run.status === "draft" ? <Action kind="run-approve" id={run.id} institution={institutionId} label="Approve report" /> : null}
              {run.status === "approved" ? <Action kind="run-post" id={run.id} institution={institutionId} label="Finalise / post OpFin-owned" /> : null}
            </td></tr>)}</tbody></table>
      </section>
    </> : null}
  </Screen>;
}
