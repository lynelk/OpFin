import { Screen } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";
import { formatUgx } from "@/lib/format";

export default async function LoanAccountPage() {
  const applications = await opfinApi.loanApplications(1);
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
          <p className="muted">No active loan in mock data.</p>
        )}
      </section>
    </Screen>
  );
}
