import { DataTable } from "@/components/DataTable";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function RepaymentSchedulePage() {
  const token = await getAccessToken();

  try {
    const profile = await opfinApi.profile(token);
    const applications = await opfinApi.loanApplications(profile.data.user.id, token);
    const activeLoan = applications.data.find((application) => application.loan)?.loan;

    if (!activeLoan) {
      return (
        <Screen title="Repayment schedule" description="Schedule view based on known loan schedule fields from the backend models.">
          <StateNotice state="empty" message="No active loan account is available for a repayment schedule." />
        </Screen>
      );
    }

    const schedule = await opfinApi.repaymentSchedule();

    return (
      <Screen title="Repayment schedule" description="Schedule view based on known loan schedule fields from the backend models.">
        <section className="panel">
          <StateNotice state="sandbox" message={schedule.message} />
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
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load repayment schedule.";

    return (
      <Screen title="Repayment schedule" description="Schedule view based on known loan schedule fields from the backend models.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
