import { updateLoanApplicationStatusAction } from "@/app/actions";
import { DataTable } from "@/components/DataTable";
import { Screen, StateNotice } from "@/components/Screen";
import { mockApplications } from "@/lib/mock-data";
import { formatUgx } from "@/lib/format";

export default async function CreditReviewPage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string; status?: string }> }) {
  const params = await searchParams;

  return (
    <Screen title="Credit application review" description="Review queue using known loan application fields. Status updates use the documented admin endpoint.">
      {params?.status ? <StateNotice state="success" message="Application review status updated." /> : null}
      {params?.message ? <StateNotice state={params.error === "forbidden" ? "forbidden" : "server"} message={params.message} /> : null}
      <section className="panel">
        <DataTable
          rows={mockApplications}
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
        <StateNotice state="sandbox" message="The backend does not document an all-applications admin list yet, so the queue data remains sandbox-labelled." />
      </section>
    </Screen>
  );
}
