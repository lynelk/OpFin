import { updateLoanApplicationStatusAction } from "@/app/actions";
import { DataTable } from "@/components/DataTable";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function CreditReviewPage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string; status?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const snapshot = await opfinApi.investorDemoAdminSnapshot(token);

    return (
      <Screen title="Credit application review" description="Review queue using known loan application fields. Status updates use the documented admin endpoint.">
        {params?.status ? <StateNotice state="success" message="Application review status updated." /> : null}
        {params?.message ? <StateNotice state={params.error === "forbidden" ? "forbidden" : "server"} message={params.message} /> : null}
        <section className="panel">
          <DataTable
            rows={snapshot.data.applications}
            getKey={(row) => row.id}
            columns={[
              { label: "Application", render: (row) => `#${row.id}` },
              { label: "Product", render: (row) => row.loan_product?.name ?? "Not available" },
              { label: "Amount", render: (row) => formatUgx(row.amount) },
              { label: "Reason", render: (row) => row.reason ?? "Not available" },
              { label: "Status", render: (row) => <span className="badge warn">{row.status}</span> },
              {
                label: "Action",
                render: (row) => (
                  <form action={updateLoanApplicationStatusAction} className="inline-form">
                    <input type="hidden" name="application_id" value={row.id} />
                    <input type="hidden" name="status" value="Approved" />
                    <button className="button secondary" type="submit">Approve</button>
                  </form>
                )
              }
            ]}
          />
          <StateNotice state="sandbox" message="Investor-demo snapshot data includes mock decisioning and sandbox disbursement records." />
        </section>
        <div className="grid grid-3">
          <section className="panel"><h2>Decisions</h2><div className="stat">{snapshot.data.decisions.length}</div></section>
          <section className="panel"><h2>Loans</h2><div className="stat">{snapshot.data.loans.length}</div></section>
          <section className="panel"><h2>Ledger entries</h2><div className="stat">{snapshot.data.ledger_entries.length}</div></section>
        </div>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load investor-demo admin snapshot.";

    return (
      <Screen title="Credit application review" description="Review queue using known loan application fields. Status updates use the documented admin endpoint.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
