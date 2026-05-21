import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

export default async function LoanDecisionPage({ searchParams }: { searchParams?: Promise<{ status?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const profile = await opfinApi.profile(token);
    const decision = await opfinApi.loanDecision(profile.data.user.id, token);

    return (
      <Screen
        title="Loan decision result"
        description="Decision display derived from documented application status while formal affordability contracts remain pending."
        action={<Link className="button" href="/loans/offer">View offer</Link>}
      >
        <section className="panel">
          <h2>{decision.data.status}</h2>
          {params?.status ? <StateNotice state="success" message="Application submitted to the backend or sandbox API." /> : null}
          <p className="muted">{decision.data.message}</p>
          <span className="badge warn">{decision.data.source}</span>
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
