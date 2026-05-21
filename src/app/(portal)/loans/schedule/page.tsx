import { DataTable } from "@/components/DataTable";
import { Screen } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";
import { formatUgx } from "@/lib/format";

export default async function RepaymentSchedulePage() {
  const schedule = await opfinApi.repaymentSchedule(77);

  return (
    <Screen title="Repayment schedule" description="Schedule view based on known loan schedule fields from the backend models.">
      <section className="panel">
        <DataTable
          rows={schedule.data}
          getKey={(row) => row.id}
          columns={[
            { label: "Due date", render: (row) => row.due_date },
            { label: "Principal", render: (row) => formatUgx(row.principal) },
            { label: "Interest", render: (row) => formatUgx(row.interest) },
            { label: "Outstanding", render: (row) => formatUgx(row.total_outstanding) }
          ]}
        />
      </section>
    </Screen>
  );
}
