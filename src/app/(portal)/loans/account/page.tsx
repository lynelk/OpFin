import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function LoanAccountPage() {
  const token = await getAccessToken();

  try {
    const profile = await opfinApi.profile(token);
    const applications = await opfinApi.loanApplications(profile.data.user.id, token);
    const active = applications.data.find((application) => application.loan);

    return (
      <Screen title="Loan account" description="Current loan account state from known application and loan fields.">
        <section className="panel">
          <h2>Active loan</h2>
          {active?.loan ? (
            <table className="table">
              <tbody>
                <tr><th>Loan ID</th><td>{active.loan.id}</td></tr>
                <tr><th>Status</th><td>{active.loan.status}</td></tr>
                <tr><th>Outstanding balance</th><td>{formatUgx(active.loan.outstanding_balance)}</td></tr>
                <tr><th>Repayment starts</th><td>{active.loan.repayment_start_date ?? "Not available"}</td></tr>
              </tbody>
            </table>
          ) : (
            <StateNotice state="empty" message="No active loan account is available." />
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load loan account.";

    return (
      <Screen title="Loan account" description="Current loan account state from known application and loan fields.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
