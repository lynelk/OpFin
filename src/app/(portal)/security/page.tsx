import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { OpfinApiError } from "@/lib/api/errors";
import { v5P0Api } from "@/lib/api/v5-p0";
import { updateSecurityCentreAction } from "@/app/v5-p0-actions";

export default async function SecurityCentrePage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};

  try {
    const response = await v5P0Api.security(token);
    const { controls, events } = response.data;
    return (
      <Screen title="Security Centre" description="Control transaction freezes and alerts, and review recent security activity on your OpFin account.">
        {params.status ? <StateNotice state="success" message="Security controls updated." /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to update security controls."} /> : null}

        <section className="panel">
          <h2>Account controls</h2>
          <form action={updateSecurityCentreAction} className="form-grid">
            <div className="grid grid-3">
              <div className="field">
                <label htmlFor="transactions_frozen">Transactions</label>
                <select id="transactions_frozen" name="transactions_frozen" defaultValue={String(Boolean(controls.transactions_frozen))}>
                  <option value="false">Allowed</option>
                  <option value="true">Frozen</option>
                </select>
              </div>
              <div className="field">
                <label htmlFor="login_alerts">Login alerts</label>
                <select id="login_alerts" name="login_alerts" defaultValue={String(Boolean(controls.login_alerts))}>
                  <option value="true">Enabled</option>
                  <option value="false">Disabled</option>
                </select>
              </div>
              <div className="field">
                <label htmlFor="payment_alerts">Payment alerts</label>
                <select id="payment_alerts" name="payment_alerts" defaultValue={String(Boolean(controls.payment_alerts))}>
                  <option value="true">Enabled</option>
                  <option value="false">Disabled</option>
                </select>
              </div>
            </div>
            <button className="button" type="submit">Save security controls</button>
          </form>
        </section>

        <section className="panel">
          <h2>Recent security events</h2>
          {events.length === 0 ? <p className="muted">No recent security events are recorded.</p> : (
            <div className="case-list">
              {events.map((event) => (
                <article className="case-card" key={event.id}>
                  <div className="case-card-head"><strong>{event.event_type.replaceAll("_", " ")}</strong><span className="badge">{event.severity}</span></div>
                  <p className="muted">Source: {event.source.replaceAll("_", " ")} · {new Date(event.occurred_at).toLocaleString("en-UG")}</p>
                </article>
              ))}
            </div>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Security Centre" description="Your account protection controls will appear here."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load Security Centre."} /></Screen>;
  }
}
