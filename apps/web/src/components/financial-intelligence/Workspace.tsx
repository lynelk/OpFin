import Link from "next/link";
import { randomUUID } from "node:crypto";
import type { ReactNode } from "react";
import { financialIntelligenceAction } from "@/app/financial-intelligence-actions";
import { intelligenceApi, type Analysis, type Context, type Source } from "@/lib/api/financial-intelligence";
import { destination, displayMetric, label, positiveId, integerInput, type IntelligenceTab } from "@/lib/financial-intelligence/presentation";
import { SubmitButton } from "./SubmitButton";

type Query = Record<string, string | string[] | undefined>;
type Props = { context: Context; query: Query; token: string; tab: IntelligenceTab };
function queryId(query: Query, key: string): number | undefined {
  const raw = query[key]; return typeof raw === "string" ? positiveId(raw) : undefined;
}
function Field({ title, name, value, type = "text", required = true, hint }: { title: string; name: string; value?: string | number; type?: string; required?: boolean; hint?: string }) {
  return <label className="fi-field"><span>{title}</span><input name={name} type={type} defaultValue={value} required={required} autoComplete="off" />{hint ? <small>{hint}</small> : null}</label>;
}
function Hidden({ space, operation }: { space: number; operation: string }) {
  return <><input type="hidden" name="space_id" value={space} /><input type="hidden" name="operation" value={operation} /><input type="hidden" name="instruction_key" value={randomUUID()} /></>;
}
function Empty({ children }: { children: ReactNode }) { return <p className="fi-notice">{children}</p>; }
function Evidence({ value, title = "Source fingerprint" }: { value: string; title?: string }) { return <p className="fi-evidence"><strong>{title}:</strong> <code>{value}</code></p>; }
function PageLinks({ space, tab, query, page, more }: { space: number; tab: IntelligenceTab; query: Query; page: number; more: boolean }) {
  const safe = Object.fromEntries(["source", "import", "case", "report", "statement"].flatMap(key => typeof query[key] === "string" ? [[key, String(queryId(query, key))]] : []));
  return <nav className="fi-actions" aria-label="Result pages">{page > 1 ? <Link href={destination(space, tab, { ...safe, page: page - 1 })}>Previous page</Link> : null}{more ? <Link href={destination(space, tab, { ...safe, page: page + 1 })}>Next page</Link> : null}<span>Page {page}</span></nav>;
}
function MetricTable({ analysis }: { analysis: Analysis }) {
  return <>{Object.entries(analysis.currency_metrics).map(([currency, values]) => <section key={currency} className="panel">
    <h3>{currency} portfolio</h3><p className="muted">Amounts are integer minor units of {currency}. Ratios show percentages. Missing evidence is not zero.</p>
    <div className="fi-table"><table><thead><tr><th scope="col">Measure</th><th scope="col">Observed value</th></tr></thead><tbody>
      {Object.entries(values).map(([key, value]) => <tr key={key}><th scope="row">{label(key)}</th><td>{displayMetric(key, value)}</td></tr>)}
    </tbody></table></div>
  </section>)}<details className="panel"><summary>Metric definitions and evidence limitations</summary><dl>{Object.entries(analysis.definitions).map(([key, value]) => <div key={key}><dt><strong>{label(key)}</strong></dt><dd>{value}</dd></div>)}</dl></details></>;
}
function Records({ rows, empty }: { rows: Array<Record<string, unknown>>; empty: string }) {
  if (!rows.length) return <Empty>{empty}</Empty>;
  const columns = [...new Set(rows.flatMap(row => Object.keys(row)))];
  return <div className="fi-table"><table><thead><tr>{columns.map(column => <th key={column} scope="col">{label(column)}</th>)}</tr></thead><tbody>{rows.map((row, i) => <tr key={i}>{columns.map(column => <td key={column}>{row[column] == null ? "Not available" : typeof row[column] === "object" ? <pre>{JSON.stringify(row[column], null, 2)}</pre> : String(row[column])}</td>)}</tr>)}</tbody></table></div>;
}
function SourceSelector({ space, sources, selected, tab }: { space: number; sources: Source[]; selected?: number; tab: IntelligenceTab }) {
  return <nav className="fi-actions" aria-label="Source population">{sources.map(source => <Link key={source.id} aria-current={source.id === selected ? "page" : undefined} href={destination(space, tab, { source: source.id })}>{source.name}: {source.population}</Link>)}</nav>;
}
function ExportLinks({ space, report }: { space: number; report: number }) {
  const base = `/api/financial-intelligence/spaces/${space}/reports/${report}`;
  return <div className="fi-actions"><a className="button secondary" href={base + "/csv"}>Export CSV</a><a className="button secondary" href={base + "/html"} target="_blank" rel="noreferrer">Print report</a></div>;
}

export async function WorkspaceContent({ context, query, token, tab }: Props) {
  const space = context.space_id;
  const page = queryId(query, "page") ?? 1;
  const can = (permission: string) => context.permissions.includes(permission);
  if (tab === "overview") {
    const data = await intelligenceApi.overview(space, token);
    return <>{!data.sources.length ? <Empty>No source population has been registered. An authorised administrator can register a source on the Imports tab.</Empty> : null}
      {data.sources.map(source => <section key={source.id} className="fi-source"><header><p className="eyebrow">Source {source.id} · {source.status}</p><h2>{source.name}: {source.population}</h2>
        <p>Reporting date: <strong>{source.as_of ?? "No published snapshot"}</strong>{source.stale ? " · Stale source data: refresh before relying on this view." : ""}</p></header>
        {source.analysis ? <><MetricTable analysis={source.analysis} /><Evidence value={source.analysis.source_hash} />
          <details className="panel"><summary>Concentrations</summary><Records rows={source.analysis.concentrations} empty="No concentration observations." /></details>
          <details className="panel"><summary>Observed origination cohorts</summary><p>These are observed snapshot cohorts, not mature lifetime default or loss estimates.</p><Records rows={source.analysis.vintages} empty="No cohort observations." /></details>
          <details className="panel"><summary>Institutional financial observations</summary><p>Only source-referenced accounting inputs are used. Missing costs do not become zero.</p><Records rows={source.analysis.financials} empty="No institutional accounting inputs were supplied." /></details>
        </> : <Empty>Uploads are not management evidence until independently reviewed and published.</Empty>}
      </section>)}</>;
  }
  if (tab === "imports") {
    const data = await intelligenceApi.overview(space, token);
    const sourceId = queryId(query, "source") ?? data.sources[0]?.id;
    const source = data.sources.find(item => item.id === sourceId);
    const imported = queryId(query, "import");
    const detail = imported ? await intelligenceApi.importDetail(space, imported, page, token) : null;
    const imports = sourceId && !detail ? await intelligenceApi.imports(space, sourceId, page, token) : null;
    return <>
      {can("source") ? <details className="panel"><summary>Register an authorised MIS source</summary><form action={financialIntelligenceAction} className="fi-form"><Hidden space={space} operation="source" />
        <Field name="name" title="Source system name" /><Field name="population" title="Exact population covered" hint="For example, all active and closed loans for the named branch. Do not silently change the population on a later import." />
        <input type="hidden" name="country" value={context.country} /><Field name="lawful_basis_reference" title="Processing agreement or lawful-basis reference" /><SubmitButton>Register source</SubmitButton>
      </form></details> : null}
      <SourceSelector space={space} sources={data.sources} selected={sourceId} tab="imports" />
      {source && can("import") ? <details className="panel"><summary>Upload a reconciled CSV snapshot for {source.name}</summary>
        <p>Use source-system exports, not retyped totals. Required amounts are integer minor units. A supplied schedule must reconcile in full; omitting it produces unknown delinquency.</p>
        <details><summary>Required CSV columns</summary><p><strong>Loan file</strong></p><code>loan_ref,borrower_ref,currency,principal_outstanding_minor,original_principal_minor,originated_on</code><p><strong>Complete schedule file</strong></p><code>loan_ref,instalment_ref,due_on,principal_due_minor,interest_due_minor,principal_paid_minor,interest_paid_minor</code></details>
        <form action={financialIntelligenceAction} className="fi-form"><Hidden space={space} operation="portfolio" /><input type="hidden" name="source_id" value={source.id} /><input type="hidden" name="source_name" value={source.name} /><input type="hidden" name="population" value={source.population} />
          <Field title="Reporting date" name="as_of" type="date" /><Field title="Source loan-record count" name="expected_loan_count" type="number" />
          <label className="fi-field">Currency-separated principal control totals<textarea name="control_totals_minor" required rows={3} placeholder={'{"UGX": 1000000, "USD": 25000}'} /><small>Each amount must come from the same source population and reporting date. No currency conversion is performed.</small></label>
          <label className="fi-field">Loans CSV<input name="loans_file" type="file" accept=".csv,text/csv" required /></label>
          <label className="fi-field">Complete repayment schedule CSV<input name="instalments_file" type="file" accept=".csv,text/csv" /><small>Only omit this when the schedule is unavailable. OpFin will not assume those loans are current.</small></label>
          <label className="fi-field">Optional column mapping<textarea name="mapping" rows={3} placeholder={'{"loans":{"loan_ref":"Loan ID"},"instalments":{"loan_ref":"Loan ID"}}'} /><small>Map every required canonical field to an exact source column when headers differ. Leave empty for canonical headers.</small></label>
          <SubmitButton>Validate and stage snapshot</SubmitButton>
        </form></details> : null}
      {detail ? <section className="panel"><h2>Import {detail.import.id}: {detail.import.status}</h2><p>{detail.import.row_count} loan records · Reporting date {detail.import.as_of} · Submitted by member {detail.import.submitted_by}</p><Evidence value={detail.import.source_hash} />
        {detail.import.status === "staged" && can("publish") ? <form action={financialIntelligenceAction} className="fi-form"><Hidden space={space} operation="review" /><input type="hidden" name="import_id" value={detail.import.id} />
          <label className="fi-field">Decision<select name="decision" required><option value="published">Publish after independent source review</option><option value="rejected">Reject this import</option></select></label>
          <label className="fi-field">Review reason<textarea name="reason" required maxLength={1000} rows={3} /></label><p>The submitter cannot approve their own import. Publishing does not authenticate the original MIS.</p><SubmitButton>Record independent decision</SubmitButton></form> : null}
        <MetricTable analysis={detail.analysis} />
        <h3>Source loan observations</h3><div className="fi-table"><table><thead><tr><th scope="col">Loan reference</th><th scope="col">Borrower reference</th><th scope="col">Currency</th><th scope="col">Principal, minor units</th><th scope="col">Days past due</th><th scope="col">Overdue amount</th></tr></thead><tbody>{detail.loans.map(loan => <tr key={loan.loan_ref}><th scope="row">{loan.loan_ref}</th><td>{loan.borrower_ref}</td><td>{loan.currency}</td><td>{displayMetric("amount", loan.principal_outstanding_minor)}</td><td>{displayMetric("days", loan.dpd)}</td><td>{displayMetric("amount", loan.arrears_minor)}</td></tr>)}</tbody></table></div>
        <PageLinks space={space} tab={tab} query={{ ...query, import: String(detail.import.id) }} page={page} more={page * 50 < detail.total} />
      </section> : imports ? <section className="panel"><h2>Import history</h2>{!imports.items.length ? <Empty>No snapshots have been submitted for this population.</Empty> : <div className="fi-table"><table><thead><tr><th scope="col">Import</th><th scope="col">Reporting date</th><th scope="col">Records</th><th scope="col">Status</th></tr></thead><tbody>{imports.items.map(item => <tr key={item.id}><th scope="row"><Link href={destination(space, tab, { source: item.source_id, import: item.id })}>Import {item.id}</Link></th><td>{item.as_of}</td><td>{item.row_count}</td><td>{label(item.status)}</td></tr>)}</tbody></table></div>}<PageLinks space={space} tab={tab} query={{ ...query, source: String(sourceId) }} page={page} more={page * 25 < (imports.total ?? 0)} /></section> : <Empty>Register a source to begin.</Empty>}
    </>;
  }
  if (tab === "cases") {
    const caseId = queryId(query, "case");
    const history = caseId ? await intelligenceApi.caseHistory(space, caseId, page, token) : null;
    const cases = history ? null : await intelligenceApi.cases(space, page, token);
    return <>{history ? <section className="panel"><h2>Case {history.case.id}: {label(history.case.signal)}</h2><p>{history.case.status} · Priority {history.case.priority} · Version {history.case.version}</p><p>Loan {history.case.evidence.loan_ref} · Borrower {history.case.evidence.borrower_ref} · Evidence as of {history.case.evidence.as_of}</p><Empty>{history.case.evidence.warning}</Empty>
      <form action={financialIntelligenceAction} className="fi-form"><Hidden space={space} operation="case" /><input type="hidden" name="case_id" value={history.case.id} /><input type="hidden" name="expected_version" value={history.case.version} />
        <label className="fi-field">Action<select name="case_action" required><option value="note">Record a note</option><option value="start">Start review</option><option value="monitor">Set follow-up</option>{context.role !== "collections" ? <option value="assign">Assign to an authorised member</option> : null}<option value="close">Close with recorded outcome</option><option value="reopen">Reopen case</option></select></label>
        {context.role !== "collections" ? <Field name="assigned_to" title="Assignee member ID, for assignment only" type="number" required={false} hint="The server verifies active membership and case-management permission in this Space." /> : null}
        <Field name="due_on" title="Follow-up date, for assignment or monitoring" type="date" required={false} />
        <label className="fi-field">Outcome, for closure<select name="outcome"><option value="other">Other documented outcome</option><option value="source_corrected">Source record corrected</option><option value="payment_recorded_in_core">Payment recorded in the core</option><option value="hardship_plan">Hardship plan</option><option value="referred">Referred for review</option></select></label>
        <Field name="source_evidence_reference" title="Core payment or correction evidence reference" required={false} />
        <label className="fi-field">Evidence and next action<textarea name="note" rows={4} required maxLength={2000} /><small>Do not enter PINs, passwords or unrelated personal details. A note does not post a payment or prove final settlement.</small></label><SubmitButton>Record case action</SubmitButton>
      </form><h3>Append-only case history</h3><Records rows={history.events} empty="No recorded events." /><PageLinks space={space} tab={tab} query={query} page={page} more={history.events.length === 50} /></section> : <section className="panel"><h2>{context.role === "collections" ? "Your assigned cases" : "Portfolio action queue"}</h2><p>Investigate the source evidence before contacting a customer. Disputed records and hardship require appropriate handling.</p>
      {!cases?.items.length ? <Empty>No cases are available to your role.</Empty> : <div className="fi-table"><table><thead><tr><th scope="col">Case</th><th scope="col">Signal</th><th scope="col">Status</th><th scope="col">Assignee</th><th scope="col">Due</th><th scope="col">Evidence date</th></tr></thead><tbody>{cases.items.map(item => <tr key={item.id}><th scope="row"><Link href={destination(space, tab, { case: item.id })}>Case {item.id}</Link></th><td>{label(item.signal)}</td><td>{label(item.status)}</td><td>{item.assigned_to ?? "Unassigned"}</td><td>{item.due_on ?? "Not scheduled"}</td><td>{item.evidence.as_of}</td></tr>)}</tbody></table></div>}<PageLinks space={space} tab={tab} query={query} page={page} more={page * 50 < (cases?.total ?? 0)} /></section>}</>;
  }
  if (tab === "statements") {
    const statementId = queryId(query, "statement");
    const [issuers, statements] = await Promise.all([intelligenceApi.issuers(space, token), intelligenceApi.statements(space, page, token)]);
    const detail = statementId ? await intelligenceApi.statement(space, statementId, page, token) : null;
    return <><Empty>Financial analysis only. Passing arithmetic does not establish issuer authenticity, account ownership or permission to use a statement for a lending decision. Never submit banking passwords, mobile-money PINs or OTPs.</Empty>
      {issuers.items.length ? <details className="panel"><summary>Upload an original statement</summary><form action={financialIntelligenceAction} className="fi-form"><Hidden space={space} operation="statement" />
        <label className="fi-field">Approved issuing institution<select name="issuer_version_id" required>{issuers.items.map(issuer => <option key={issuer.id} value={issuer.id}>{issuer.legal_name} · {issuer.product_type} · {issuer.country}</option>)}</select></label>
        <p>{issuers.note}</p><Field name="currency" title="Account currency" value="UGX" /><Field name="period_start" title="Period starts" type="date" /><Field name="period_end" title="Period ends" type="date" />
        <Field name="opening_balance_minor" title="Opening balance, integer minor units" required={false} /><Field name="closing_balance_minor" title="Closing balance, integer minor units" required={false} />
        <Field name="account_reference" title="Your account reference" hint="Stored encrypted. It is not an account password." /><Field name="authority_reference" title="Authority or consent reference" hint="For your own account, state that you are the account owner. Organisations must record their authority to process it." /><Field name="authority_expires_at" title="Analysis permission expires" type="date" />
        <label className="fi-check"><input type="checkbox" name="authority_confirmed" value="1" required />I am authorised to submit this account information for financial analysis in this Financial Space.</label>
        <label className="fi-field">Original issuer file<input type="file" name="statement_file" accept=".csv,.pdf,text/csv,application/pdf" required /><small>CSV analysis is supported. PDF originals are stored in quarantine; this candidate does not claim to parse or authenticate them. Maximum combined request size is 25 MB.</small></label>
        <details><summary>CSV column mapping</summary><p>Canonical columns: date, reference, description, direction, amount_minor, balance_minor, counterparty_ref. Direction must be credit or debit. Reference, balance and counterparty are optional.</p><textarea name="mapping" rows={3} aria-label="CSV column mapping" placeholder={'{"date":"Date","description":"Description","direction":"Direction","amount_minor":"Amount"}'} /></details><SubmitButton>Upload for validation</SubmitButton>
      </form></details> : <Empty>No issuer version is currently approved for this jurisdiction. An authorised compliance reviewer must establish the actual issuer and licence evidence; unfamiliar issuers are not automatically fraudulent.</Empty>}
      {detail ? <section className="panel"><h2>Statement {detail.statement.id}</h2><p>{label(detail.statement.status)} · Source authenticity: {detail.source_authenticity} · Account ownership: {detail.statement.account_ownership}</p><Evidence title="Original-file fingerprint" value={detail.statement.file_hash} /><p>Current issuer eligibility: {detail.issuer_eligibility_current ? "Recorded approval is current" : "Review required"}. Analysis permission expires {detail.statement.authority_expires_at}.</p>
        {detail.analysis ? <><h3>{detail.analysis.currency} account movement</h3><p>Financial checks: {label(detail.analysis.financial_checks)}</p><Records rows={[{ credits_minor: detail.analysis.credits_minor, debits_minor: detail.analysis.debits_minor, net_movement_minor: detail.analysis.net_movement_minor, verified_income_minor: detail.analysis.verified_income_minor }]} empty="No analysis." /><Empty>{detail.analysis.interpretation}</Empty>
          <h3>Review findings</h3><Records rows={detail.analysis.findings} empty="No arithmetic review finding in the analysed records. This is not authentication." /><h3>Observed monthly activity</h3><Records rows={detail.analysis.monthly_activity} empty="No monthly observations." />
          <h3>Extracted transactions</h3><Records rows={detail.analysis.transactions} empty="No transactions." /><PageLinks space={space} tab={tab} query={query} page={page} more={page * 50 < detail.analysis.transaction_total} /></> : <Empty>This original remains quarantined and has not been analysed by an approved parser. No income, verification or credit result has been fabricated.</Empty>}
        <form action={financialIntelligenceAction}><Hidden space={space} operation="revoke_statement" /><input type="hidden" name="statement_id" value={detail.statement.id} /><SubmitButton>Withdraw analysis permission</SubmitButton></form><p className="muted">Withdrawal prevents further analysis access. Original evidence remains subject to the approved retention policy.</p>
      </section> : <section className="panel"><h2>Your statement register</h2>{!statements.items.length ? <Empty>No statements have been uploaded to this Space.</Empty> : <div className="fi-table"><table><thead><tr><th scope="col">Statement</th><th scope="col">Period</th><th scope="col">Processing</th><th scope="col">Authority</th></tr></thead><tbody>{statements.items.map(item => <tr key={item.id}><th scope="row">{item.revoked_at ? `Statement ${item.id}` : <Link href={destination(space, tab, { statement: item.id })}>Statement {item.id}</Link>}</th><td>{item.period_start} to {item.period_end}</td><td>{label(item.status)}</td><td>{item.revoked_at ? "Withdrawn" : `Expires ${item.authority_expires_at}`}</td></tr>)}</tbody></table></div>}<PageLinks space={space} tab={tab} query={query} page={page} more={statements.items.length === 25} /></section>}
    </>;
  }
  if (tab === "reports") {
    const reportId = queryId(query, "report");
    const reports = await intelligenceApi.reports(space, page, token);
    const report = reportId ? await intelligenceApi.report(space, reportId, token) : null;
    return <><section className="panel"><h2>Freeze a management report</h2><p>Only independently published snapshots can be issued. Previously issued reports are not rewritten by later corrections.</p><form className="fi-form" action={financialIntelligenceAction}><Hidden space={space} operation="freeze" /><Field name="import_id" title="Published import ID" type="number" /><SubmitButton>Freeze report</SubmitButton></form></section>
      {report ? <section className="panel"><h2>{report.report.title}</h2><p>{report.report.space_name} · As of {report.report.as_of} · Generated {report.report.generated_at}</p><Empty>{report.report.disclaimer}</Empty><MetricTable analysis={report.report.analysis} /><Evidence title="Frozen-report fingerprint" value={report.content_hash} /><ExportLinks space={space} report={report.report_id} />
        {can("share") ? <details><summary>Control sharing with a recipient institution</summary><form className="fi-form" action={financialIntelligenceAction}><Hidden space={space} operation="share" /><input type="hidden" name="report_id" value={report.report_id} /><Field name="recipient_space_id" title="Recipient institutional Space ID" type="number" /><Field name="expires_at" title="Sharing expires" type="date" /><label className="fi-check"><input type="checkbox" name="revoke" value="1" />Revoke the existing sharing mandate instead</label><label className="fi-check"><input type="checkbox" name="confirm_sharing" value="1" required />I authorise this specific report's privacy-filtered portfolio aggregates for the named institution. This does not share customer records or create a lending mandate.</label><SubmitButton>Record sharing instruction</SubmitButton></form></details> : null}</section> : null}
      <section className="panel"><h2>Issued reports</h2>{reports.items.map(item => <p key={item.id}><Link href={destination(space, tab, { report: item.id })}>Report {item.id}</Link> · Import {item.import_id} · {item.created_at}</p>)}{!reports.items.length ? <Empty>No frozen reports have been issued.</Empty> : null}<PageLinks space={space} tab={tab} query={query} page={page} more={page * 25 < (reports.total ?? 0)} /></section></>;
  }
  if (tab === "access") {
    const data = await intelligenceApi.members(space, token);
    return <section className="panel"><h2>Institutional access register</h2><p>Membership, paid entitlement and delegated role are separate checks. Platform administration does not bypass institutional data permissions.</p><Records rows={data.grants} empty="No delegated grants. Existing institutional administrator memberships retain their governed access." />
      <form className="fi-form" action={financialIntelligenceAction}><Hidden space={space} operation="grant" /><label className="fi-field">Active member<select name="user_id" required>{data.members.map(member => <option key={member.user_id} value={member.user_id}>Member {member.user_id} · Membership role {member.role}</option>)}</select></label>
        <label className="fi-field">Financial Intelligence role<select name="role" required><option value="analyst">Analyst: import, investigate and manage cases</option><option value="reviewer">Reviewer: independent publication and investigation</option><option value="collections">Collections: assigned cases only</option><option value="board">Board: aggregate oversight and reports</option><option value="auditor">Auditor: read-only detailed evidence and reports</option></select></label><Field name="expires_at" title="Grant expires" type="date" /><label className="fi-check"><input type="checkbox" name="revoke" value="1" />Revoke this member's delegated grant</label><SubmitButton>Save access decision</SubmitButton></form></section>;
  }
  if (tab === "network") {
    const data = await intelligenceApi.network(space, page, token);
    return <section className="panel"><h2>Permissioned institutional reports</h2><p>Each report has a separate scope, population and reporting date. Do not add overlapping books or different currencies. Small borrower cohorts are suppressed.</p>{data.items.map(item => <article key={item.grant_id}><h3>Sharing mandate {item.grant_id}</h3><p>Access expires {item.expires_at}</p><Records rows={[item.report]} empty="No shared observations." /><Evidence title="Shared-payload fingerprint" value={item.shared_payload_hash} /></article>)}{!data.items.length ? <Empty>No current report-sharing mandate is available.</Empty> : null}<PageLinks space={space} tab={tab} query={query} page={page} more={data.items.length === 25} /></section>;
  }
  if (tab === "compare") {
    const from = queryId(query, "from"); const to = queryId(query, "to");
    const comparison = from && to ? await intelligenceApi.compare(space, from, to, token) : null;
    return <section className="panel"><h2>Portfolio movement</h2><form className="fi-form" method="get"><input type="hidden" name="tab" value="compare" /><Field name="from" title="Earlier published import ID" type="number" value={from} /><Field name="to" title="Later published import ID" type="number" value={to} /><button className="button" type="submit">Compare evidence</button></form><Empty>Both snapshots must represent the same source and population. Disappearance, refinancing and write-off must not be described as cash recovery.</Empty>{comparison ? <Records rows={[comparison]} empty="No comparison." /> : null}</section>;
  }
  const imported = queryId(query, "import");
  const inflow = typeof query.inflow === "string" ? integerInput(query.inflow, 0, 10000) : 1000;
  const outflow = typeof query.outflow === "string" ? integerInput(query.outflow, 0, 10000) : 1000;
  const scenario = imported ? await intelligenceApi.stress(space, { import_id: imported, inflow_reduction_bps: inflow, outflow_increase_bps: outflow }, token) : null;
  return <section className="panel"><h2>Liquidity sensitivity</h2><p>A deterministic scenario using supplied accounting observations, not a prediction or a regulatory liquidity assessment. 100 basis points equals one percentage point.</p><form className="fi-form" method="get"><input type="hidden" name="tab" value="scenario" /><Field name="import" title="Published import ID" type="number" value={imported} /><Field name="inflow" title="Inflow reduction, basis points" type="number" value={inflow} /><Field name="outflow" title="Outflow increase, basis points" type="number" value={outflow} /><button className="button" type="submit">Calculate scenario</button></form>{scenario ? <Records rows={[scenario]} empty="No accounting inputs." /> : null}</section>;
}
