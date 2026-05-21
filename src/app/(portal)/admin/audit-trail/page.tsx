import { DataTable } from "@/components/DataTable";
import { Screen, StateNotice } from "@/components/Screen";
import { mockAuditEvents } from "@/lib/mock-data";

export default function AuditTrailPage() {
  return (
    <Screen title="Audit trail" description="Sandbox-labelled sensitive-action audit events until an admin audit API is documented.">
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
        <StateNotice state="sandbox" message="No documented frontend-readable audit trail endpoint exists yet." />
      </section>
    </Screen>
  );
}
