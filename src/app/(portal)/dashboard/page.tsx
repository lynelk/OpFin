import Link from "next/link";
import { Screen } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";
import { formatUgx } from "@/lib/format";

export default async function DashboardPage() {
  const [profile, balance, applications] = await Promise.all([
    opfinApi.profile(),
    opfinApi.loanBalance(1),
    opfinApi.loanApplications(1)
  ]);

  return (
    <Screen
      title="Customer dashboard"
      description="A working customer home for verified identity, credit applications, and current loan position."
      action={<Link className="button" href="/loans/apply">Start application</Link>}
    >
      <div className="grid grid-3">
        <section className="panel">
          <h2>KYC status</h2>
          <span className="badge ok">{profile.data.user.nin_status ?? "Unknown"}</span>
          <p className="muted">Phone {profile.data.user.phone}</p>
        </section>
        <section className="panel">
          <h2>Outstanding balance</h2>
          <div className="stat">{formatUgx(balance.data.outstandingAmount)}</div>
        </section>
        <section className="panel">
          <h2>Applications</h2>
          <div className="stat">{applications.data.length}</div>
        </section>
      </div>
    </Screen>
  );
}
