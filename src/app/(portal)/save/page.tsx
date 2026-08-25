import { JourneyCard } from "@/components/JourneyCard";
import { Screen, StateNotice } from "@/components/Screen";
import { saveProtectionApi } from "@/lib/api/save-protection";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function SavePage() {
  const token = await getAccessToken();

  try {
    const [goalsResult, policiesResult] = await Promise.allSettled([
      saveProtectionApi.savingsGoals(token),
      saveProtectionApi.protectionPolicies(token)
    ]);
    const goals = goalsResult.status === "fulfilled" ? goalsResult.value.data.goals : [];
    const policies = policiesResult.status === "fulfilled" ? policiesResult.value.data.policies : [];
    const confirmed = goals.reduce((sum, goal) => sum + goal.confirmed_balance_minor, 0);
    const available = goals.reduce((sum, goal) => sum + goal.available_balance_minor, 0);
    const activePolicies = policies.filter((policy) => policy.status === "active").length;

    return (
      <Screen
        title="Save & Protect"
        description="Build a resilience buffer with partner-held savings, then add clearly disclosed protection issued by the named insurer or underwriter."
      >
        <div className="grid grid-3">
          <JourneyCard
            title="Savings goals"
            description={goals.length ? `${goals.length} goal${goals.length === 1 ? "" : "s"}. Confirmed partner position: ${formatUgx(confirmed)}.` : "Create a goal using an approved partner-held savings product."}
            href="/savings"
            action={goals.length ? "Manage savings" : "Create savings goal"}
            status="pilot"
          />
          <JourneyCard
            title="Available savings"
            description={`Confirmed position currently available for an eligible withdrawal: ${formatUgx(available)}. Pending collections are deliberately excluded.`}
            href="/savings"
            action="Review positions"
            status="pilot"
          />
          <JourneyCard
            title="Protection"
            description={policies.length ? `${policies.length} policy record${policies.length === 1 ? "" : "s"}, with ${activePolicies} active cover${activePolicies === 1 ? "" : "s"}.` : "Compare approved protection products and review the insurer before enrolling."}
            href="/insurance"
            action={policies.length ? "Manage protection" : "Explore protection"}
            status="pilot"
          />
        </div>

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>Money and risk boundaries</h2>
          <p className="muted">
            OpFin manages the journey and product records. CPay executes collections and payouts. Savings become part of your confirmed position only after partner evidence is recorded, and protection becomes active only after the insurer or underwriter issues cover. Automatic savings debits remain disabled until a certified mandate contract is available.
          </p>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load Save & Protect.";

    return (
      <Screen title="Save & Protect" description="Build resilience with partner-held savings and clearly issued protection.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
