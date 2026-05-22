import { createComplianceReportAction } from "@/app/actions";
import { DataTable } from "@/components/DataTable";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

export default async function CompliancePage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string; status?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const response = await opfinApi.complianceReports(token);
    const reports = response.data.reports;

    return (
      <Screen title="Compliance reports" description="Generated regulatory and operational report records.">
        {params?.status ? <StateNotice state="success" message="Compliance report created." /> : null}
        {params?.message ? <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} /> : null}
        <div className="grid grid-2">
          <section className="panel">
            <h2>New report</h2>
            <form action={createComplianceReportAction} className="form-grid">
              <div className="field">
                <label htmlFor="report_type">Report type</label>
                <select id="report_type" name="report_type" defaultValue="monthly_credit_register">
                  <option value="monthly_credit_register">Monthly credit register</option>
                  <option value="kyc_register">KYC register</option>
                  <option value="consent_register">Consent register</option>
                  <option value="mobile_money_settlement">Mobile money settlement</option>
                </select>
              </div>
              <div className="field">
                <label htmlFor="period_start">Period start</label>
                <input id="period_start" name="period_start" type="date" required />
              </div>
              <div className="field">
                <label htmlFor="period_end">Period end</label>
                <input id="period_end" name="period_end" type="date" required />
              </div>
              <button className="button" type="submit">Generate record</button>
            </form>
          </section>
          <section className="panel">
            <h2>Recent reports</h2>
            {reports.length === 0 ? <StateNotice state="empty" message="No compliance reports are available." /> : (
              <DataTable
                rows={reports}
                getKey={(row) => row.id}
                columns={[
                  { label: "Type", render: (row) => row.report_type },
                  { label: "Period", render: (row) => `${row.period_start} to ${row.period_end}` },
                  { label: "Status", render: (row) => <span className="badge warn">{row.status}</span> }
                ]}
              />
            )}
          </section>
        </div>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load compliance reports.";

    return (
      <Screen title="Compliance reports" description="Generated regulatory and operational report records.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
