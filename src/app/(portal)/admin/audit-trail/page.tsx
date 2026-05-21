import { DataTable } from "@/components/DataTable";
import { Screen } from "@/components/Screen";
import { mockAuditEvents } from "@/lib/mock-data";

export default function AuditTrailPage() {
  return (
    <Screen title="Audit trail" description="Placeholder for sensitive-action audit events.">
      <section className="panel">
        <DataTable
          rows={mockAuditEvents}
          getKey={(row) => row.id}
          columns={[
            { label: "Event", render: (row) => row.event },
            { label: "Actor", render: (row) => row.actor },
            { label: "Created", render: (row) => row.created_at }
          ]}
        />
      </section>
    </Screen>
  );
}
