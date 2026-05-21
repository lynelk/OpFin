import Link from "next/link";
import { Screen } from "@/components/Screen";

export default function LoanDecisionPage() {
  return (
    <Screen
      title="Loan decision result"
      description="Decision state placeholder until the backend exposes formal affordability and decision contracts."
      action={<Link className="button" href="/loans/offer">View offer</Link>}
    >
      <section className="panel">
        <h2>Decision pending</h2>
        <p className="muted">Current backend endpoints expose application status values, not a separate decision payload.</p>
      </section>
    </Screen>
  );
}
