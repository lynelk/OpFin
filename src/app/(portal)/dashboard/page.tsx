import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function DashboardPage() {
  const token = await getAccessToken();

  try {
    const [demo, profile] = await Promise.all([
      opfinApi.demoDashboard(token),
      opfinApi.profile(token)
    ]);
    const userId = profile.data.user.id;
    const balance = await opfinApi.loanBalance(userId, token);
    const latest = demo.data.latest_application;

    return (
      <Screen
        title="Customer dashboard"
        description="A working customer home for verified identity, credit applications, and current loan position."
        action={<Link className="button" href="/loans/apply">Start application</Link>}
      >
        <div className="grid grid-3">
          <section className="panel">
            <h2>KYC status</h2>
            <span className="badge ok">{demo.data.kyc.status ?? "Unknown"}</span>
            <p className="muted">Phone {profile.data.user.phone}</p>
          </section>
          <section className="panel">
            <h2>Consent</h2>
            <span className="badge warn">{demo.data.consent?.status ?? "not granted"}</span>
            <p className="muted">Credit processing consent for demo decisioning.</p>
          </section>
          <section className="panel">
            <h2>Outstanding balance</h2>
            <div className="stat">{formatUgx(balance.data.outstandingAmount)}</div>
          </section>
          <section className="panel">
            <h2>Latest decision</h2>
            <div className="stat">{latest?.demo_decision?.status ?? "None"}</div>
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
