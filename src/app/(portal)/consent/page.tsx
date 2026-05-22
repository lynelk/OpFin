import { createConsentAction, revokeConsentAction } from "@/app/actions";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

export default async function ConsentPage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string; status?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const dashboard = await opfinApi.demoDashboard(token);
    const consent = dashboard.data.consent;

    return (
      <Screen title="Consent management" description="Consent UI shell for CRB, mobile money, and employer-linked permissions.">
        <div className="grid grid-2">
          <section className="panel">
            <h2>Current consent state</h2>
            <span className="badge warn">{consent?.status ?? "not granted"}</span>
            <p className="muted">Mock investor-demo consent for credit processing only.</p>
          </section>
          <section className="panel">
            <h2>Consent actions</h2>
            {params?.status ? <StateNotice state="success" message={`Sandbox consent ${params.status}.`} /> : null}
            {params?.message ? <StateNotice state={params.error === "forbidden" ? "forbidden" : "server"} message={params.message} /> : null}
            <div className="auth-actions">
              <form action={createConsentAction}>
                <button className="button" type="submit">Create sandbox consent</button>
              </form>
              <form action={revokeConsentAction}>
                <button className="button secondary" type="submit">Revoke sandbox consent</button>
              </form>
            </div>
          </section>
        </div>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load consent state.";

    return (
      <Screen title="Consent management" description="Consent UI shell for CRB, mobile money, and employer-linked permissions.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
