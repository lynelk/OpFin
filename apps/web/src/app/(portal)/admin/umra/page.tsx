import Link from "next/link";
import {
  applyTermVariationAction,
  evaluateNplAction,
  proposeTermVariationAction,
  recordUmraApprovalAction,
  retryCrbSubmissionAction
} from "@/app/umra-actions";
import { DataTable } from "@/components/DataTable";
import { Screen, StateNotice } from "@/components/Screen";
import { governanceApi } from "@/lib/api/governance";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

function badge(value: string) {
  const ok = ["submitted", "applied", "customer_consented", "umra_approved"].includes(value.toLowerCase());
  return <span className={"badge " + (ok ? "ok" : "warn")}>{value.replaceAll("_", " ")}</span>;
}

export default async function UmraControlDesk({
  searchParams
}: {
  searchParams?: Promise<{ error?: string; message?: string; status?: string }>;
}) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const [creditReference, npl, variations] = await Promise.all([
      governanceApi.umraCreditReferences(token),
      governanceApi.umraNplControls(token),
      governanceApi.umraVariations(token)
    ]);

    const crbSummary = creditReference.data.summary;

    return (
      <Screen
        title="UMRA control desk"
        description="Operational controls for credit-information exchange, NPL recovery ceilings and governed credit-term variations."
        action={<Link className="button secondary" href="/admin/compliance">Generate UMRA books & reports</Link>}
      >
        {params?.status ? <StateNotice state="success" message="UMRA control action completed." /> : null}
        {params?.message ? <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} /> : null}

        <div className="grid grid-3 compass-grid">
          <article className="panel"><p className="muted">CRB pending</p><div className="stat">{crbSummary.pending ?? 0}</div></article>
          <article className="panel"><p className="muted">CRB failed</p><div className="stat">{crbSummary.failed ?? 0}</div></article>
          <article className="panel"><p className="muted">CRB submitted</p><div className="stat">{crbSummary.submitted ?? 0}</div></article>
          <article className="panel"><p className="muted">Positive records</p><div className="stat">{crbSummary.positive ?? 0}</div></article>
          <article className="panel"><p className="muted">Negative records</p><div className="stat">{crbSummary.negative ?? 0}</div></article>
          <article className="panel"><p className="muted">NPL cap mode</p><div className="stat stat-text">{npl.data.enforcement_mode}</div></article>
        </div>

        <section className="panel">
          <div className="split-heading">
            <div>
              <h2>Credit-information exchange</h2>
              <p className="muted">Positive and negative borrower records are validation-hashed, queued and submitted through the configured credit-reference reporting endpoint.</p>
            </div>
          </div>
          {creditReference.data.submissions.length === 0 ? <StateNotice state="empty" message="No credit-reference submissions have been staged." /> : (
            <DataTable
              rows={creditReference.data.submissions}
              getKey={(row) => row.id}
              columns={[
                { label: "Loan", render: (row) => row.loan_id ?? "—" },
                { label: "Event", render: (row) => row.event_type.replaceAll("_", " ") },
                { label: "Type", render: (row) => badge(row.information_type) },
                { label: "Status", render: (row) => badge(row.status) },
                { label: "Reporting date", render: (row) => row.reporting_date },
                { label: "Provider ref", render: (row) => row.provider_reference ?? "—" },
                { label: "Evidence", render: (row) => <code>{row.payload_hash.slice(0, 12)}…</code> },
                {
                  label: "Action",
                  render: (row) => row.status === "submitted" ? "—" : (
                    <form action={retryCrbSubmissionAction}>
                      <input type="hidden" name="submission_id" value={row.id} />
                      <button className="button secondary" type="submit">Retry now</button>
                    </form>
                  )
                }
              ]}
            />
          )}
        </section>

        <section className="panel">
          <div className="split-heading">
            <div>
              <h2>Non-performing-loan recovery controls</h2>
              <p className="muted">The control freezes principal owing when a loan becomes non-performing, tracks the penalty-interest ceiling and limits recoveries when enforcement mode is enabled.</p>
            </div>
          </div>
          {npl.data.controls.length === 0 ? <StateNotice state="empty" message="No non-performing-loan controls are currently recorded." /> : (
            <DataTable
              rows={npl.data.controls}
              getKey={(row) => row.id}
              columns={[
                { label: "Loan", render: (row) => row.loan_id },
                { label: "NPL since", render: (row) => row.non_performing_at ? new Date(row.non_performing_at).toLocaleDateString() : "Not NPL" },
                { label: "Principal at NPL", render: (row) => formatUgx(row.principal_at_npl_minor) },
                { label: "Default penalty cap", render: (row) => formatUgx(row.default_penalty_cap_minor) },
                { label: "Interest recovery cap", render: (row) => formatUgx(row.recoverable_interest_cap_minor) },
                { label: "Total recovery cap", render: (row) => formatUgx(row.total_recoverable_cap_minor) },
                { label: "Recovered after NPL", render: (row) => formatUgx(row.total_recovered_since_npl_minor) },
                {
                  label: "Re-evaluate",
                  render: (row) => (
                    <form action={evaluateNplAction}>
                      <input type="hidden" name="loan_id" value={row.loan_id} />
                      <button className="button secondary" type="submit">Evaluate</button>
                    </form>
                  )
                }
              ]}
            />
          )}
        </section>

        <div className="grid grid-2">
          <section className="panel">
            <h2>Propose a governed term variation</h2>
            <p className="muted">Nothing takes effect without customer consent. If the proposed change includes an interest rate, prior UMRA approval evidence is also mandatory.</p>
            <form action={proposeTermVariationAction} className="form-grid">
              <div className="field">
                <label htmlFor="loan_id">Loan ID</label>
                <input id="loan_id" name="loan_id" inputMode="numeric" required />
              </div>
              <div className="field">
                <label htmlFor="proposed_changes">Proposed changes (JSON)</label>
                <textarea
                  id="proposed_changes"
                  name="proposed_changes"
                  rows={5}
                  defaultValue={'{"duration_days":120}'}
                  required
                />
                <small>Allowed keys: interest_rate_percent, fees_minor, duration_days, repayment_frequency, credit_limit_minor, payment_date.</small>
              </div>
              <div className="field">
                <label htmlFor="reason">Reason</label>
                <textarea id="reason" name="reason" rows={3} required />
              </div>
              <button className="button" type="submit">Record proposal</button>
            </form>
          </section>

          <section className="panel">
            <h2>Variation gate</h2>
            <p>No accepted offer is overwritten. Variations are separate versioned evidence records.</p>
            <ul>
              <li>All term variations require explicit customer consent.</li>
              <li>Interest-rate changes require prior recorded UMRA approval.</li>
              <li>Applied variations retain the original offer and disclosure hash for audit.</li>
              <li>Operations cannot mark a variation effective before the required gates are present.</li>
            </ul>
          </section>
        </div>

        <section className="panel">
          <h2>Credit-term variation register</h2>
          {variations.data.variations.length === 0 ? <StateNotice state="empty" message="No credit-term variations have been proposed." /> : (
            <div className="case-list">
              {variations.data.variations.map((variation) => (
                <article className="case-card" key={variation.id}>
                  <div className="case-card-head">
                    <div>
                      {badge(variation.status)}
                      <h3>Loan {variation.loan_id}</h3>
                    </div>
                    <code>Variation {variation.id}</code>
                  </div>
                  <p>{variation.reason}</p>
                  <pre>{JSON.stringify(variation.proposed_changes, null, 2)}</pre>
                  {variation.requires_umra_approval && !variation.umra_approved_at ? (
                    <form action={recordUmraApprovalAction} className="form-grid">
                      <input type="hidden" name="variation_id" value={variation.id} />
                      <div className="field"><label htmlFor={"umra-ref-" + variation.id}>UMRA approval reference</label><input id={"umra-ref-" + variation.id} name="approval_reference" required /></div>
                      <div className="field"><label htmlFor={"umra-hash-" + variation.id}>Approval evidence SHA-256</label><input id={"umra-hash-" + variation.id} name="approval_document_hash" minLength={64} maxLength={64} required /></div>
                      <button className="button secondary" type="submit">Record prior UMRA approval</button>
                    </form>
                  ) : null}
                  {!variation.customer_consented_at ? <p className="muted">Waiting for customer consent.</p> : null}
                  {variation.customer_consented_at && (!variation.requires_umra_approval || variation.umra_approved_at) && !variation.applied_at ? (
                    <form action={applyTermVariationAction}>
                      <input type="hidden" name="variation_id" value={variation.id} />
                      <button className="button" type="submit">Mark variation effective</button>
                    </form>
                  ) : null}
                </article>
              ))}
            </div>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load UMRA controls.";
    return <Screen title="UMRA control desk" description="Regulatory operational controls."><StateNotice state={state} message={message} /></Screen>;
  }
}
