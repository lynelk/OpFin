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
  const kind = normalized.includes("validated") || normalized.includes("balanced") || normalized.includes("approved") ? "ok" : "warn";
  return <span className={`badge ${kind}`}>{status.replaceAll("_", " ")}</span>;
}

export default async function CompliancePage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string; status?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const { data } = await governanceApi.dashboard(token);
    const latest = data.integrity.latest_run;

    return (
      <Screen title="Compliance & financial integrity" description="Auto-generated regulatory evidence, continuous ledger assurance, fraud exceptions and auditable digital-channel controls.">
        {params?.status ? <StateNotice state="success" message="Governance action completed and evidence was refreshed." /> : null}
        {params?.message ? <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} /> : null}

        <div className="grid grid-3 compass-grid">
          <article className="panel"><p className="muted">Platform funds status</p><div className="stat stat-text">{data.integrity.platform_balanced ? "Balanced" : "Attention required"}</div>{badge(latest?.status ?? "not_run")}</article>
          <article className="panel"><p className="muted">Critical integrity alerts</p><div className="stat">{data.integrity.open_critical_alerts}</div><p className="muted">Critical findings cannot be silently written off.</p></article>
          <article className="panel"><p className="muted">High integrity alerts</p><div className="stat">{data.integrity.open_high_alerts}</div><p className="muted">Reconciliation, provider or operational exceptions.</p></article>
          <article className="panel"><p className="muted">WhatsApp audit trail</p><div className="stat">{data.whatsapp.audit_hashes_present}</div><p className="muted">{data.whatsapp.messages_24h} messages in the last 24 hours.</p></article>
          <article className="panel"><p className="muted">Verified WhatsApp sessions</p><div className="stat">{data.whatsapp.verified_sessions}</div><p className="muted">OTP-bound sessions expire after 15 minutes.</p></article>
          <article className="panel"><p className="muted">Regulatory evidence packs</p><div className="stat">{data.regulatory_reports.length}</div><p className="muted">Generated, validated and evidence-hashed.</p></article>
        </div>

        <section className="panel compass-grid">
          <div className="journey-card-head">
            <div><h2>Continuous financial integrity</h2><p className="muted">{data.integrity.funds_integrity_rule}</p></div>
            <form action={runFinancialIntegrityAction}><button className="button" type="submit">Run self-audit now</button></form>
          </div>
          {latest ? (
            <div className="grid grid-3">
              <div><div className="stat">{latest.ledger_transactions_checked}</div><span className="muted">Ledger transactions checked</span></div>
              <div><div className="stat">{latest.unbalanced_transactions}</div><span className="muted">Unbalanced transactions</span></div>
              <div><div className="stat">{latest.payment_exceptions}</div><span className="muted">Payment exceptions</span></div>
              <div><div className="stat">{latest.duplicate_references}</div><span className="muted">Duplicate references</span></div>
              <div><div className="stat">{latest.orphan_entries}</div><span className="muted">Orphan entries</span></div>
              <div><div className="stat">{latest.net_ledger_imbalance_minor}</div><span className="muted">Net ledger imbalance</span></div>
            </div>
          ) : <StateNotice state="empty" message="No financial-integrity run has completed yet." />}
        </section>

        <div className="grid grid-2 compass-grid">
          <section className="panel">
            <h2>Generate regulator evidence pack</h2>
            <p className="muted">Reports are generated from system-of-record data, validation-scored and SHA-256 hashed. Officer-controlled filings remain approval-gated.</p>
            <form action={generateRegulatoryReportAction} className="form-grid">
              <div className="field"><label htmlFor="report_type">Regulatory report</label><select id="report_type" name="report_type" defaultValue="umra_digital_credit_supervision">{reportOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div>
              <div className="field"><label htmlFor="period_start">Period start</label><input id="period_start" name="period_start" type="date" required /></div>
              <div className="field"><label htmlFor="period_end">Period end</label><input id="period_end" name="period_end" type="date" required /></div>
              <button className="button" type="submit">Generate & validate</button>
            </form>
          </section>
          <section className="panel">
            <h2>WhatsApp governance</h2>
            <p>WhatsApp is a controlled OpFin channel, not a parallel financial system.</p>
            <div className="chip-row"><span className="badge ok">Signed webhooks</span><span className="badge ok">Replay protection</span><span className="badge ok">OTP session</span><span className="badge ok">Message evidence hashes</span><span className="badge warn">Step-up for money actions</span></div>
            <p className="muted">Customers can securely check status and KYC, manage explicit credit-processing consent and create support cases. Payments, withdrawals, transfers, investment orders and offer acceptance require authenticated step-up confirmation.</p>
          </section>
        </div>

        <section className="panel compass-grid">
          <h2>Regulatory report register</h2>
          {data.regulatory_reports.length === 0 ? <StateNotice state="empty" message="No regulator evidence packs have been generated." /> : (
            <DataTable rows={data.regulatory_reports} getKey={(row) => row.id} columns={[
              { label: "Regulator", render: (row) => row.regulator },
              { label: "Report", render: (row) => row.report_type.replaceAll("_", " ") },
              { label: "Period", render: (row) => `${row.period_start} to ${row.period_end}` },
              { label: "Validation", render: (row) => badge(row.status) },
              { label: "Evidence hash", render: (row) => <code>{row.payload_hash.slice(0, 12)}…</code> },
              { label: "Officer control", render: (row) => row.status === "validated" ? <form action={approveRegulatoryReportAction} className="inline-form"><input type="hidden" name="report_id" value={row.id} /><button className="button secondary" type="submit">Approve for submission</button></form> : badge(row.status) }
            ]} />
          )}
        </section>

        <section className="panel">
          <h2>Open financial-integrity exceptions</h2>
          {data.open_integrity_alerts.length === 0 ? <StateNotice state="success" message="No unresolved financial-integrity exceptions." /> : (
            <div className="case-list">
              {data.open_integrity_alerts.map((alert) => <article className="case-card" key={alert.id}>
                <div className="case-card-head"><div>{badge(alert.severity)}<h3>{alert.type.replaceAll("_", " ")}</h3></div>{alert.reference ? <code>{alert.reference}</code> : null}</div>
                <p>{alert.description}</p>
                <form action={resolveIntegrityAlertAction} className="form-grid"><input type="hidden" name="alert_id" value={alert.id} /><div className="field"><label htmlFor={`resolution-${alert.id}`}>Resolution evidence</label><input id={`resolution-${alert.id}`} name="resolution" placeholder="What was investigated, corrected and verified?" required /></div><button className="button secondary" type="submit">Resolve with evidence</button></form>
              </article>)}
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
