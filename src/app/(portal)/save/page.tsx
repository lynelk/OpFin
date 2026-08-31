import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { saveProtectionApi } from "@/lib/api/save-protection";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function SavePage() {
  const token = await getAccessToken();

  try {
    const goalsResponse = await saveProtectionApi.savingsGoals(token);
    const goals = goalsResponse.data.goals;
    const confirmed = goals.reduce((sum, goal) => sum + goal.confirmed_balance_minor, 0);
    const available = goals.reduce((sum, goal) => sum + goal.available_balance_minor, 0);
    const firstGoal = goals[0];

    return (
      <Screen
        title="Save"
        description="Build financial resilience through clear savings goals and confirmed partner-held positions. Protection remains a separate journey so saving never feels bundled with insurance."
      >
        <section className="panel compass-next-action">
          <p className="eyebrow">BUILD RESILIENCE FIRST</p>
          <h2>{firstGoal ? "Keep moving your savings goal forward" : "Start with one goal that matters"}</h2>
          <p className="muted">
            {firstGoal
              ? `${firstGoal.name}: ${formatUgx(firstGoal.confirmed_balance_minor)} confirmed toward your target. Pending collections are deliberately excluded.`
              : "Create a goal using an approved partner-held savings product. OpFin will show confirmed and pending money separately."}
          </p>
          <Link className="button compass-action" href="/savings">{firstGoal ? "Manage savings" : "Create savings goal"}</Link>
        </section>

        <div className="grid grid-3">
          <section className="panel">
            <h2>Confirmed savings</h2>
            <div className="stat">{formatUgx(confirmed)}</div>
            <p className="muted">Partner-confirmed savings positions only.</p>
          </section>
          <section className="panel">
            <h2>Available to withdraw</h2>
            <div className="stat">{formatUgx(available)}</div>
            <p className="muted">Subject to the rules of the underlying savings product.</p>
          </section>
          <section className="panel">
            <h2>Active goals</h2>
            <div className="stat">{goals.length}</div>
            <p className="muted">Keep the number of prominent goals small enough to stay actionable.</p>
          </section>
        </div>

        <section className="panel">
          <div className="case-card-head">
            <div>
              <h2>Automate only when you choose</h2>
              <p className="muted">Money Autopilot can support routine saving, but provider execution, mandates and settlement remain separate from the rule you create.</p>
            </div>
            <Link href="/money-autopilot">Manage Autopilot</Link>
          </div>
        </section>

        <section className="panel">
          <h2>Money boundary</h2>
          <p className="muted">
            OpFin manages goals and instructions. CPay executes collections and payouts. Savings become part of your confirmed position only after partner evidence is recorded, and automatic debits remain disabled until the required certified mandate exists.
          </p>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load Save.";

    return (
      <Screen title="Save" description="Build resilience with partner-held savings and clear confirmation states.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
