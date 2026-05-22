import { DataTable } from "@/components/DataTable";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

export default async function AuditTrailPage() {
  const token = await getAccessToken();

  try {
    const snapshot = await opfinApi.investorDemoAdminSnapshot(token);

    return (
      <Screen title="Audit trail" description="Investor-demo audit trail with mock integration labels.">
        <section className="panel">
          <DataTable
            rows={snapshot.data.audit_trail}
            getKey={(row) => row.id}
            columns={[
              { label: "Event", render: (row) => row.event },
              { label: "Mock", render: (row) => row.metadata?.mock_integration ? "Yes" : "No" },
              { label: "Created", render: (row) => row.created_at }
            ]}
          />
          <StateNotice state="sandbox" message="Mock integrations are explicitly labelled in audit metadata." />
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load audit trail.";

    return (
      <Screen title="Audit trail" description="Investor-demo audit trail with mock integration labels.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
