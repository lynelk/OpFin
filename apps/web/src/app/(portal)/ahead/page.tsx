import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { financialWellbeingApi } from "@/lib/api/financial-wellbeing";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";
import { createAspirationAction, updateAspirationAction } from "@/app/financial-aspiration-actions";

export default async function AheadPage({
  searchParams
}: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};
  try {
    const response = await financialWellbeingApi.aspirations(token);
    const goals = response.data.aspirations;
    const active = goals.filter((goal) => goal.status !== "archived");

    return (
      <Screen title="My aspirations" description="Choose what matters to you. Your plans can change as your life changes, and no amount is too small to start planning.">
        {params.status ? <StateNotice state="success" message="Your plan has been updated." /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to update your plan."} /> : null}

        <div className="grid grid-2">
          <section className="panel">
            <p className="eyebrow">OPFIN AHEAD</p>
            <h2>Create a personal goal</h2>
            <p className="muted">These are your own plans, not savings accounts, investment orders or credit applications.</p>
            <form action={createAspirationAction} className="form-grid" style={{ marginTop: 18 }}>
              <div className="field">
                <label htmlFor="goal-title">What would you like to achieve?</label>
                <input id="goal-title" name="title" minLength={2} maxLength={120} placeholder="For example, prepare for school fees" required />
              </div>
              <div className="field">
                <label htmlFor="goal-category">What kind of goal?</label>
                <select id="goal-category" name="category" defaultValue="other">
                  <option value="other">My own goal</option>
                  <option value="stability">Build financial stability</option>
                  <option value="education">Education</option>
                  <option value="family">Family</option>
                  <option value="enterprise">Business or enterprise</option>
                  <option value="protection">Protection</option>
                  <option value="growth">Long-term growth</option>
                </select>
              </div>
              <div className="grid grid-2">
                <div className="field">
                  <label htmlFor="goal-amount">Target (UGX, optional)</label>
                  <input id="goal-amount" name="target_amount_minor" type="number" min="0" step="1" placeholder="Optional" />
                </div>
                <div className="field">
                  <label htmlFor="goal-date">Target date (optional)</label>
                  <input id="goal-date" name="target_date" type="date" />
                </div>
              </div>
              <button className="button" type="submit">Create my goal</button>
            </form>
          </section>

          <section className="panel">
            <p className="eyebrow">MY PROGRESS</p>
            <h2>Choose your next step</h2>
            <p className="muted">Making a plan is a meaningful start. You can revisit, change or pause it at any time. OpFin will never assume that your first recorded goal is your most important one.</p>
            <p style={{ marginTop: 18 }}>Your plans: <strong>{active.length}</strong></p>
            <p className="muted">Financial progress will be shown separately from savings and other verified money movements.</p>
            <Link className="button secondary" href="/money" style={{ marginTop: 18 }}>Review my financial position</Link>
          </section>
        </div>

        <section className="panel" aria-labelledby="aspiration-list-heading">
          <div className="case-card-head">
            <div><h2 id="aspiration-list-heading">Your goals</h2><p className="muted">Private to your personal financial context.</p></div>
            <Link href="/dashboard">Back to Today</Link>
          </div>
          {active.length === 0 ? (
            <p className="placeholder">You haven't recorded a goal yet. Choose something that matters to you and start at your own pace.</p>
          ) : (
            <div className="grid grid-2">
              {active.map((goal) => (
                <article className="case-card" key={goal.reference}>
                  <div className="case-card-head">
                    <strong>{goal.title}</strong>
                    {goal.is_priority ? <span className="badge ok">My current priority</span> : <span className="badge">{goal.status}</span>}
                  </div>
                  <p className="muted">{goal.target_amount_minor === null ? "No amount required" : `Personal target: ${formatUgx(goal.target_amount_minor)}`}</p>
                  {goal.target_date ? <p className="muted">Target date: {goal.target_date}</p> : null}
                  <p className="muted">Planning only. No money has been deposited by creating this goal.</p>
                  <div className="quick-actions" style={{ marginTop: 14 }}>
                    {goal.status === "active" && !goal.is_priority ? (
                      <form action={updateAspirationAction}>
                        <input type="hidden" name="reference" value={goal.reference} />
                        <input type="hidden" name="intent" value="priority" />
                        <button className="button secondary" type="submit">Make my priority</button>
                      </form>
                    ) : null}
                    <form action={updateAspirationAction}>
                      <input type="hidden" name="reference" value={goal.reference} />
                      <input type="hidden" name="intent" value={goal.status === "paused" ? "resume" : "pause"} />
                      <button className="button secondary" type="submit">{goal.status === "paused" ? "Resume" : "Pause"}</button>
                    </form>
                    <form action={updateAspirationAction}>
                      <input type="hidden" name="reference" value={goal.reference} />
                      <input type="hidden" name="intent" value="archive" />
                      <button className="button secondary" type="submit">Archive</button>
                    </form>
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const kind = error instanceof OpfinApiError ? error.kind : "server";
    return (
      <Screen title="My aspirations" description="Your own plans should remain yours.">
        <StateNotice state={kind} message={error instanceof Error ? error.message : "Unable to load personal goals."} />
      </Screen>
    );
  }
}
