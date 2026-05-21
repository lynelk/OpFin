import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function DashboardPage() {
  const token = await getAccessToken();

  try {
    const profile = await opfinApi.profile(token);
    const userId = profile.data.user.id;
    const [balance, applications] = await Promise.all([
      opfinApi.loanBalance(userId, token),
      opfinApi.loanApplications(userId, token)
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
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load customer dashboard.";

    return (
      <Screen title="Customer dashboard" description="A working customer home for verified identity, credit applications, and current loan position.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
