import { createReconciliationRunAction } from "@/app/actions";
import { DataTable } from "@/components/DataTable";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

export default async function ReconciliationPage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string; status?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const response = await opfinApi.reconciliationRuns(token);
    const runs = response.data.runs;

    return (
      <Screen title="Reconciliation" description="Mobile money reconciliation runs and exception intake.">
        {params?.status ? <StateNotice state="success" message="Reconciliation run created." /> : null}
        {params?.message ? <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} /> : null}
        <div className="grid grid-2">
          <section className="panel">
            <h2>New run</h2>
            <form action={createReconciliationRunAction} className="form-grid">
              <div className="field">
                <label htmlFor="provider">Provider</label>
                <select id="provider" name="provider" defaultValue="mtn">
                  <option value="mtn">MTN Mobile Money</option>
                  <option value="airtel">Airtel Money</option>
                </select>
              </div>
              <div className="field">
                <label htmlFor="business_date">Business date</label>
                <input id="business_date" name="business_date" type="date" required />
              </div>
              <button className="button" type="submit">Create run</button>
            </form>
          </section>
          <section className="panel">
            <h2>Recent runs</h2>
            {runs.length === 0 ? <StateNotice state="empty" message="No reconciliation runs are available." /> : (
              <DataTable
                rows={runs}
                getKey={(row) => row.id}
                columns={[
                  { label: "Provider", render: (row) => row.provider },
                  { label: "Date", render: (row) => row.business_date },
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
    const message = error instanceof Error ? error.message : "Unable to load reconciliation runs.";

    return (
      <Screen title="Reconciliation" description="Mobile money reconciliation runs and exception intake.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
