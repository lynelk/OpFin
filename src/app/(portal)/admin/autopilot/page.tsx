import { Screen, StateNotice } from "@/components/Screen";
import { autopilotApi } from "@/lib/api/autopilot";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function severityClass(severity: string): string {
  return severity === "critical" || severity === "high" ? "warn" : severity === "low" ? "ok" : "";
}

export default async function AutopilotPage() {
  const token = await getAccessToken();

  try {
    const [summary, queue] = await Promise.all([
      autopilotApi.summary(token),
      autopilotApi.workQueue(token)
    ]);

    return (
      <Screen
        title="Platform Autopilot"
        description="OpFin handles routine operational monitoring automatically and presents the remaining exceptions that require human judgment."
      >
        <section className="panel compass-next-action">
          <p className="eyebrow">Autonomous operations</p>
          <div className="case-card-head">
            <div>
              <h2>{summary.autonomy_rate.toFixed(1)}% autonomous queue</h2>
              <p className="muted">The rate reflects currently open automatic versus human-required work items, not a claim that all regulated decisions are automated.</p>
            </div>
            <span className="badge ok">Policy governed</span>
          </div>
        </section>

        <div className="grid grid-3 compass-grid">
          <section className="panel">
            <h2>Needs human attention</h2>
            <div className="stat">{summary.open_exceptions}</div>
            <p className="muted">Ambiguous or high-impact items deliberately remain human-controlled.</p>
          </section>
          <section className="panel">
            <h2>Automatic items</h2>
            <div className="stat">{summary.open_automatic_items}</div>
            <p className="muted">Safe, reversible actions currently tracked by the automation fabric.</p>
          </section>
          <section className="panel">
            <h2>Last platform scan</h2>
            <div className="stat stat-text">{summary.last_run?.completed_at ? new Date(summary.last_run.completed_at).toLocaleString("en-UG") : "Not run yet"}</div>
            <p className="muted">Scheduled scans observe KYC, consent, payments, reconciliation, support and hardship.</p>
          </section>
        </div>

        <div className="grid grid-2">
          <section className="panel">
            <h2>Exceptions by domain</h2>
            {summary.by_domain.length === 0 ? <p className="muted">No open operational items.</p> : (
              <div className="case-list">
                {summary.by_domain.map((domain) => (
                  <div className="case-card-head" key={domain.domain}>
                    <span>{domain.domain.replaceAll("_", " ")}</span>
                    <strong>{domain.total}</strong>
                  </div>
                ))}
              </div>
            )}
          </section>
          <section className="panel">
            <h2>Control model</h2>
            <div className="case-list">
              <p><strong>A1</strong> Recommend. Human chooses the outcome.</p>
              <p><strong>A2–A3</strong> Execute safe reversible actions under policy.</p>
              <p><strong>A4</strong> Automated financial decisions only where formally authorised and explainable.</p>
              <p><strong>A5</strong> High-impact decisions retain maker-checker or explicit human approval.</p>
            </div>
          </section>
        </div>

        <section className="panel">
          <div className="case-card-head">
            <div>
              <h2>Exception work queue</h2>
              <p className="muted">Highest-severity and nearest-SLA work appears first.</p>
            </div>
            <span className="badge">{queue.items.length} open</span>
          </div>

          {queue.items.length === 0 ? (
            <StateNotice state="success" message="No current human-required exceptions. Platform monitoring continues on schedule." />
          ) : (
            <div className="case-list">
              {queue.items.map((item) => (
                <article className="case-card" key={item.id}>
                  <div className="case-card-head">
                    <div>
                      <div className="chip-row">
                        <span className={`badge ${severityClass(item.severity)}`}>{item.severity}</span>
                        <span className="badge">{item.domain}</span>
                        <span className="badge">{item.automation_tier}</span>
                      </div>
                      <h3>{item.title}</h3>
                    </div>
                    <span className="muted">#{item.id}</span>
                  </div>
                  {item.description ? <p>{item.description}</p> : null}
                  {item.recommended_action ? <p className="muted"><strong>Recommended:</strong> {item.recommended_action}</p> : null}
                  <p className="muted">Due {item.due_at ? new Date(item.due_at).toLocaleString("en-UG") : "according to operational SLA"}</p>
                </article>
              ))}
            </div>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load Platform Autopilot.";
    return (
      <Screen title="Platform Autopilot" description="Autonomous operations monitoring and exception management.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
