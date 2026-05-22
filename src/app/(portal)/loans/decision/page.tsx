import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

export default async function LoanDecisionPage({ searchParams }: { searchParams?: Promise<{ application?: string; status?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const applicationId = Number(params?.application);
    const dashboard = await opfinApi.demoDashboard(token);
    const latestApplication = dashboard.data.latest_application;
    const decision = applicationId
      ? await opfinApi.demoDecision(applicationId, token)
      : { data: { decision: latestApplication?.demo_decision ?? null } };

    return (
      <Screen
        title="Loan decision result"
        description="Mock affordability and decisioning result for the investor demo."
        action={<Link className="button" href={`/loans/offer${applicationId ? `?application=${applicationId}` : ""}`}>View offer</Link>}
      >
        <section className="panel">
          <h2>{decision.data.decision?.status ?? "No decision"}</h2>
          {params?.status ? <StateNotice state="success" message="Application submitted to the backend or sandbox API." /> : null}
          {decision.data.decision ? (
            <>
              <p className="muted">{decision.data.decision.decision_summary}</p>
              <div className="chip-row">
                {decision.data.decision.reason_codes.map((code) => <span className="badge warn" key={code}>{code}</span>)}
              </div>
            </>
          ) : (
            <StateNotice state="empty" message="Submit an application to generate a mock decision." />
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load loan decision state.";

    return (
      <Screen title="Loan decision result" description="Decision display derived from documented application status while formal affordability contracts remain pending.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
