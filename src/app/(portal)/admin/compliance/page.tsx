import {
  approveRegulatoryReportAction,
  generateRegulatoryReportAction,
  resolveIntegrityAlertAction,
  runFinancialIntegrityAction
} from "@/app/governance-actions";
import { DataTable } from "@/components/DataTable";
import { Screen, StateNotice } from "@/components/Screen";
import { governanceApi } from "@/lib/api/governance";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

const reportOptions = [
  ["fia_annual_compliance", "FIA annual AML/CFT compliance"],
  ["fia_large_cash_transactions", "FIA large cash transaction register"],
  ["fia_suspicious_activity_register", "FIA suspicious activity candidate register"],
  ["pdpo_annual_compliance", "PDPO annual privacy compliance"],
  ["umra_digital_credit_supervision", "UMRA digital credit supervision"],
  ["consumer_protection_complaints", "UMRA consumer protection complaints"],
  ["payment_integrity_oversight", "BoU payment integrity oversight"]
] as const;

function badge(status: string) {
  const normalized = status.toLowerCase();
  const kind = normalized.includes("critical") || normalized.includes("failed") ? "danger" : normalized.includes("validated") || normalized.includes("balanced") || normalized.includes("approved") ? "success" : "warn";
  return <span className={`badge ${kind}`}>{status.replaceAll("_", " ")}</span>;
}

export default async function CompliancePage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string; status?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const response = await governanceApi.dashboard(token);
    const data = response.data;
    const latest = data.integrity.latest_run;

    return (
      <Screen title="Compliance & financial integrity" description="Auto-generated regulatory evidence, continuous ledger assurance, fraud exceptions and auditable digital-channel controls.">
        {params?.status ? <StateNotice state="success" message="Governance action completed and evidence was refreshed." /> : null}
        {params?.message ? <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} /> : null}

        <div className="metric-grid">
          <article className="metric-card">
            <span>Platform funds status</span>
            <strong>{data.integrity.platform_balanced ? "Balanced" : "Attention required"}</strong>
            {badge(latest?.status ?? "not_run")}
          </article>
          <article className="metric-card">
            <span>Critical integrity alerts</span>
            <strong>{data.integrity.open_critical_alerts}</strong>
            <small>Any critical alert requires investigation before closure.</small>
          </article>
          <article className="metric-card">
            <span>High integrity alerts</span>
            <strong>{data.integrity.open_high_alerts}</strong>
            <small>Reconciliation, provider or operational exceptions.</small>
          </article>
          <article className="metric-card">
            <span>WhatsApp audit trail</span>
            <strong>{data.whatsapp.audit_hashes_present}</strong>
            <small>{data.whatsapp.messages_24h} messages in the last 24 hours.</small>
          </article>
        </div>

        <section className="panel">
          <div className="panel-heading-row">
            <div>
              <h2>Continuous financial integrity</h2>
              <p>{data.integrity.funds_integrity_rule}</p>
            </div>
            <form action={runFinancialIntegrityAction}>
              <button className="button" type="submit">Run self-audit now</button>
            </form>
          </div>
          {latest ? (
            <div className="grid grid-4 compact-grid">
              <div><strong>{latest.ledger_transactions_checked}</strong><span>Ledger transactions checked</span></div>
              <div><strong>{latest.unbalanced_transactions}</strong><span>Unbalanced transactions</span></div>
              <div><strong>{latest.payment_exceptions}</strong><span>Payment exceptions</span></div>
              <div><strong>{latest.net_ledger_imbalance_minor}</strong><span>Net ledger imbalance</span></div>
            </div>
          ) : <StateNotice state="empty" message="No financial-integrity run has completed yet." />}
        </section>

        <div className="grid grid-2">
          <section className="panel">
            <h2>Generate regulator evidence pack</h2>
            <p>Reports are generated from system-of-record data, validation-scored and SHA-256 hashed. Approval never means the system silently files an STR or other officer-controlled submission.</p>
            <form action={generateRegulatoryReportAction} className="form-grid">
              <div className="field">
                <label htmlFor="report_type">Regulatory report</label>
                <select id="report_type" name="report_type" defaultValue="umra_digital_credit_supervision">
                  {reportOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
              </div>
              <div className="field"><label htmlFor="period_start">Period start</label><input id="period_start" name="period_start" type="date" required /></div>
              <div className="field"><label htmlFor="period_end">Period end</label><input id="period_end" name="period_end" type="date" required /></div>
              <button className="button" type="submit">Generate & validate</button>
            </form>
          </section>

          <section className="panel">
            <h2>WhatsApp governance</h2>
            <div className="grid grid-2 compact-grid">
              <div><strong>{data.whatsapp.verified_sessions}</strong><span>Verified active sessions</span></div>
              <div><strong>{data.whatsapp.messages_24h}</strong><span>Messages in 24h</span></div>
            </div>
            <p>Sessions are OTP-bound, expire after 15 minutes, inbound webhooks are signed and replay-protected, and every message has an evidence hash. Money movement and binding financial commitments require step-up confirmation in OpFin.</p>
          </section>
        </div>

        <section className="panel">
          <h2>Regulatory report register</h2>
          {data.regulatory_reports.length === 0 ? <StateNotice state="empty" message="No regulator evidence packs have been generated." /> : (
            <DataTable
              rows={data.regulatory_reports}
              getKey={(row) => row.id}
              columns={[
                { label: "Regulator", render: (row) => row.regulator },
                { label: "Report", render: (row) => row.report_type.replaceAll("_", " ") },
                { label: "Period", render: (row) => `${row.period_start} to ${row.period_end}` },
                { label: "Validation", render: (row) => badge(row.status) },
                { label: "Evidence hash", render: (row) => <code>{row.payload_hash.slice(0, 12)}…</code> },
                {
                  label: "Officer control",
                  render: (row) => row.status === "validated" ? (
                    <form action={approveRegulatoryReportAction} className="inline-form">
                      <input type="hidden" name="report_id" value={row.id} />
                      <button className="button secondary" type="submit">Approve for submission</button>
                    </form>
                  ) : badge(row.status)
                }
              ]}
            />
          )}
        </section>

        <section className="panel">
          <h2>Open financial-integrity exceptions</h2>
          {data.open_integrity_alerts.length === 0 ? <StateNotice state="success" message="No unresolved financial-integrity exceptions." /> : (
            <div className="stack-list">
              {data.open_integrity_alerts.map((alert) => (
                <article className="stack-item" key={alert.id}>
                  <div>
                    {badge(alert.severity)}
                    <h3>{alert.type.replaceAll("_", " ")}</h3>
                    <p>{alert.description}</p>
                    {alert.reference ? <code>{alert.reference}</code> : null}
                  </div>
                  <form action={resolveIntegrityAlertAction} className="inline-form">
                    <input type="hidden" name="alert_id" value={alert.id} />
                    <input aria-label="Resolution evidence" name="resolution" placeholder="Resolution and evidence" required />
                    <button className="button secondary" type="submit">Resolve with evidence</button>
                  </form>
                </article>
              ))}
            </div>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load governance controls.";
    return <Screen title="Compliance & financial integrity" description="Regulatory reporting and continuous financial assurance."><StateNotice state={state} message={message} /></Screen>;
  }
}
