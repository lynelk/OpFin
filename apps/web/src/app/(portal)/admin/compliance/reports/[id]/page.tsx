import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { governanceApi } from "@/lib/api/governance";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

export default async function RegulatoryReportDetailPage({
  params
}: Readonly<{ params: Promise<{ id: string }> }>) {
  const { id } = await params;
  const token = await getAccessToken();

  try {
    const { data } = await governanceApi.report(Number(id), token);
    const report = data.report;

    return (
      <Screen
        title={`${report.regulator}: ${report.report_type.replaceAll("_", " ")}`}
        description={`Evidence pack for ${report.period_start} to ${report.period_end}. Payload hash ${report.payload_hash}.`}
        action={<Link className="button secondary" href="/admin/compliance">Back to compliance</Link>}
      >
        <div className="grid grid-3">
          <article className="panel"><p className="muted">Status</p><div className="stat stat-text">{report.status.replaceAll("_", " ")}</div></article>
          <article className="panel"><p className="muted">Regulator</p><div className="stat stat-text">{report.regulator}</div></article>
          <article className="panel"><p className="muted">Generated</p><div className="stat stat-text">{new Date(report.generated_at).toLocaleString("en-GB")}</div></article>
        </div>

        <section className="panel">
          <h2>Validation evidence</h2>
          <pre style={{ whiteSpace: "pre-wrap", overflowWrap: "anywhere" }}>
            {JSON.stringify(report.validation_results, null, 2)}
          </pre>
        </section>

        <section className="panel">
          <h2>Books, registers and report payload</h2>
          <p className="muted">This is the system-of-record evidence used for officer review. External filing remains approval-gated.</p>
          <pre style={{ whiteSpace: "pre-wrap", overflowWrap: "anywhere" }}>
            {JSON.stringify(report.payload, null, 2)}
          </pre>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load regulatory report.";
    return (
      <Screen title="Regulatory report" description="UMRA and other regulator evidence pack.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
