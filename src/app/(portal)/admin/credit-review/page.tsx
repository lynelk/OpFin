import { DataTable } from "@/components/DataTable";
import { Screen } from "@/components/Screen";
import { mockApplications } from "@/lib/mock-data";
import { formatUgx } from "@/lib/format";

export default function CreditReviewPage() {
  return (
    <Screen title="Credit application review" description="Review queue using known loan application fields and mock data.">
      <section className="panel">
        <DataTable
          rows={mockApplications}
          getKey={(row) => row.id}
          columns={[
            { label: "Application", render: (row) => `#${row.id}` },
            { label: "Product", render: (row) => row.loan_product?.name ?? "Not available" },
            { label: "Amount", render: (row) => formatUgx(row.amount) },
            { label: "Reason", render: (row) => row.reason ?? "Not available" },
            { label: "Status", render: (row) => <span className="badge warn">{row.status}</span> }
          ]}
        />
      </section>
    </Screen>
  );
}
