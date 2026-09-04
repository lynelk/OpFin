import { revalidatePath } from "next/cache";
import { Screen, StateNotice } from "@/components/Screen";
import { experienceApi } from "@/lib/api/experience";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

async function createRule(formData: FormData) {
  "use server";
  const token = await getAccessToken();
  const ruleType = String(formData.get("rule_type") ?? "scheduled_save");
  const amountMinor = Number(formData.get("amount_minor") ?? 0);
  const name = String(formData.get("name") ?? "Savings rule").trim();

  await experienceApi.createMoneyAutopilotRule({
    name,
    rule_type: ruleType,
    trigger_config: ruleType === "scheduled_save" ? { cadence: "monthly" } : { source: "confirmed_income_event" },
    action_config: { type: "savings_contribution", amount_minor: amountMinor },
    max_amount_minor: amountMinor,
    currency: "UGX"
  }, token);
  revalidatePath("/money-autopilot");
}

async function updateRule(formData: FormData) {
  "use server";
  const token = await getAccessToken();
  const id = Number(formData.get("rule_id"));
  const status = String(formData.get("status")) as "active" | "paused" | "retired";
  await experienceApi.setMoneyAutopilotRuleStatus(id, status, token);
  revalidatePath("/money-autopilot");
}

export default async function MoneyAutopilotPage() {
  const token = await getAccessToken();

  try {
    const workspace = await experienceApi.moneyAutopilot(token);
    return (
      <Screen
        title="Money Autopilot"
        description="Set explicit rules once and let OpFin evaluate them on schedule. External money movement remains provider-gated, reversible where possible and fully auditable."
      >
        <section className="panel compass-next-action">
          <p className="eyebrow">USER-AUTHORISED AUTOMATION</p>
          <h2>Automate routine progress, not your judgment.</h2>
          <p className="muted">{workspace.guardrail}</p>
        </section>

        <section className="panel">
          <h2>Create an automation rule</h2>
          <p className="muted">Start with a capped savings rule. OpFin records your consent, evaluates the rule and waits for the approved provider or trigger before financial execution.</p>
          <form action={createRule} className="experience-form-row" style={{ marginTop: 18 }}>
            <div className="field">
              <label htmlFor="autopilot-name">Rule name</label>
              <input id="autopilot-name" name="name" required placeholder="Emergency fund top-up" />
            </div>
            <div className="field">
              <label htmlFor="autopilot-type">Rule</label>
              <select id="autopilot-type" name="rule_type" defaultValue="scheduled_save">
                <option value="scheduled_save">Scheduled saving</option>
                <option value="income_split">Save from confirmed income</option>
                <option value="goal_topup">Goal top-up</option>
              </select>
            </div>
            <div className="field">
              <label htmlFor="autopilot-amount">Maximum amount, UGX</label>
              <input id="autopilot-amount" name="amount_minor" type="number" min="1000" step="1000" required />
            </div>
            <button className="button" type="submit">Create rule</button>
          </form>
        </section>

        <section className="panel">
          <div className="case-card-head">
            <div><h2>Your rules</h2><p className="muted">Pause or retire a rule without deleting its audit history.</p></div>
            <span className="badge">{workspace.rules.length}</span>
          </div>
          {workspace.rules.length === 0 ? <StateNotice state="empty" message="No Money Autopilot rules yet." /> : (
            <div className="autopilot-rule-grid">
              {workspace.rules.map((rule) => {
                const amount = typeof rule.action_config.amount_minor === "number" ? rule.action_config.amount_minor : rule.max_amount_minor;
                const targetStatus = rule.status === "active" ? "paused" : "active";
                return (
                  <article className="experience-card" key={rule.id}>
                    <div className="case-card-head"><strong>{rule.name}</strong><span className={`badge ${rule.status === "active" ? "ok" : "warn"}`}>{rule.status}</span></div>
                    <p className="muted">{rule.rule_type.replaceAll("_", " ")}</p>
                    <div className="stat">{amount ? formatUgx(Number(amount)) : "Rule-based"}</div>
                    <p className="muted">Last evaluated: {rule.last_evaluated_at ? new Date(rule.last_evaluated_at).toLocaleString("en-UG") : "Not yet"}</p>
                    {rule.status !== "retired" ? (
                      <form action={updateRule}>
                        <input type="hidden" name="rule_id" value={rule.id} />
                        <input type="hidden" name="status" value={targetStatus} />
                        <button className="button secondary" type="submit">{targetStatus === "paused" ? "Pause" : "Resume"}</button>
                      </form>
                    ) : null}
                  </article>
                );
              })}
            </div>
          )}
        </section>

        <section className="panel">
          <h2>Recent evaluations</h2>
          {workspace.recent_executions.length === 0 ? <p className="muted">No evaluations recorded yet.</p> : (
            <div className="case-list">
              {workspace.recent_executions.map((execution) => (
                <article className="case-card" key={execution.id}>
                  <div className="case-card-head"><strong>{execution.action_type.replaceAll("_", " ")}</strong><span className="badge">{execution.status.replaceAll("_", " ")}</span></div>
                  <p>{execution.amount_minor ? formatUgx(execution.amount_minor) : "No amount"}</p>
                  <p className="muted">Evaluated {new Date(execution.evaluated_at).toLocaleString("en-UG")}. Evaluation is not proof of financial settlement.</p>
                </article>
              ))}
            </div>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Money Autopilot" description="User-controlled financial automation."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load Money Autopilot."} /></Screen>;
  }
}
