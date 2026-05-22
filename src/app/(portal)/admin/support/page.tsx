import { createSupportCaseAction, updateSupportCaseAction } from "@/app/actions";
import { DataTable } from "@/components/DataTable";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

export default async function SupportPage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string; status?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const response = await opfinApi.supportCases(token);
    const cases = response.data.support_cases;

    return (
      <Screen title="Support cases" description="Customer support intake for payment, KYC, loan, and account issues.">
        {params?.status ? <StateNotice state="success" message="Support case created." /> : null}
        {params?.message ? <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} /> : null}
        <div className="grid grid-2">
          <section className="panel">
            <h2>New case</h2>
            <form action={createSupportCaseAction} className="form-grid">
              <div className="field">
                <label htmlFor="customer_id">Customer ID</label>
                <input id="customer_id" name="customer_id" inputMode="numeric" required />
              </div>
              <div className="field">
                <label htmlFor="category">Category</label>
                <select id="category" name="category" defaultValue="payment">
                  <option value="payment">Payment</option>
                  <option value="kyc">KYC</option>
                  <option value="loan">Loan</option>
                  <option value="account">Account</option>
                </select>
              </div>
              <div className="field">
                <label htmlFor="priority">Priority</label>
                <select id="priority" name="priority" defaultValue="normal">
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
              <div className="field">
                <label htmlFor="subject">Subject</label>
                <input id="subject" name="subject" required />
              </div>
              <div className="field">
                <label htmlFor="description">Description</label>
                <textarea id="description" name="description" rows={4} required />
              </div>
              <button className="button" type="submit">Create case</button>
            </form>
          </section>
          <section className="panel">
            <h2>Recent cases</h2>
            {cases.length === 0 ? <StateNotice state="empty" message="No support cases are available." /> : (
              <DataTable
                rows={cases}
                getKey={(row) => row.id}
                columns={[
                  { label: "Case", render: (row) => row.case_number },
                  { label: "Category", render: (row) => row.category },
                  { label: "Status", render: (row) => <span className="badge warn">{row.status}</span> },
                  { label: "Subject", render: (row) => row.subject },
                  {
                    label: "Resolve",
                    render: (row) => (
                      <form action={updateSupportCaseAction} className="inline-form">
                        <input type="hidden" name="case_id" value={row.id} />
                        <input type="hidden" name="status" value="resolved" />
                        <input type="hidden" name="priority" value={row.priority} />
                        <input type="hidden" name="note" value="Resolved from operations console." />
                        <button className="button secondary" type="submit">Resolve</button>
                      </form>
                    )
                  }
                ]}
              />
            )}
          </section>
        </div>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load support cases.";

    return (
      <Screen title="Support cases" description="Customer support intake for payment, KYC, loan, and account issues.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
