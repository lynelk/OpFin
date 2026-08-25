import { randomUUID } from "node:crypto";
import Link from "next/link";
import {
  contributeSavingsAction,
  pauseSavingsGoalAction,
  resumeSavingsGoalAction,
  updateSavingsScheduleAction,
  withdrawSavingsAction
} from "@/app/save-protection-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { saveProtectionApi } from "@/lib/api/save-protection";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

function statusMessage(status?: string): string | undefined {
  switch (status) {
    case "created": return "Savings goal created. No savings balance exists until a contribution is collected and confirmed by the disclosed partner.";
    case "schedule-updated": return "Savings reminder schedule updated. Automatic debit is still disabled until a certified mandate contract is available.";
    case "paused": return "Savings goal paused. Existing confirmed partner-held savings remain visible and eligible withdrawals still follow product terms.";
    case "resumed": return "Savings goal resumed.";
    case "contribution-pending": return "Contribution collection was initiated through the payment rail. It will not increase the confirmed savings position until partner evidence is recorded.";
    case "withdrawal-requested": return "Withdrawal requested. The amount is reserved from the available position while the savings partner completes its release process; CPay payout follows partner release.";
    default: return undefined;
  }
}

export default async function SavingsGoalPage({
  params,
  searchParams
}: {
  params: Promise<{ goalId: string }>;
  searchParams?: Promise<{ status?: string; error?: string; message?: string }>;
}) {
  const route = await params;
  const query = await searchParams;
  const goalId = Number(route.goalId);
  const token = await getAccessToken();

  if (!Number.isInteger(goalId) || goalId <= 0) {
    return (
      <Screen title="Savings goal" description="Review a partner-held savings goal.">
        <StateNotice state="validation" message="The selected savings goal is invalid." />
        <Link className="button secondary" href="/savings">Back to savings</Link>
      </Screen>
    );
  }

  try {
    const response = await saveProtectionApi.savingsGoal(goalId, token);
    const goal = response.data.goal;
    const movements = response.data.movements ?? [];
    const feedback = query?.message ?? statusMessage(query?.status);
    const canContribute = goal.status === "active";
    const canWithdraw = ["active", "completed"].includes(goal.status) && goal.available_balance_minor > 0;
    const contributionKey = `save-contribution-${randomUUID()}`;
    const withdrawalKey = `save-withdrawal-${randomUUID()}`;

    return (
      <Screen
        title={goal.name}
        description="Track the confirmed partner-held position separately from collections, withdrawal reservations and payment-rail activity."
        action={<Link className="button secondary" href="/savings">Back to savings</Link>}
      >
        {feedback ? <StateNotice state={query?.error ? (query.error === "validation" ? "validation" : "server") : "success"} message={feedback} /> : null}

        <div className="grid grid-3" style={{ marginTop: feedback ? 16 : 0 }}>
          <section className="panel">
            <p className="eyebrow">Confirmed partner position</p>
            <div className="stat">{formatUgx(goal.confirmed_balance_minor)}</div>
            <p className="muted">Only partner-confirmed contributions less completed withdrawals.</p>
          </section>
          <section className="panel">
            <p className="eyebrow">Available now</p>
            <div className="stat">{formatUgx(goal.available_balance_minor)}</div>
            <p className="muted">Confirmed position less withdrawals already reserved for release.</p>
          </section>
          <section className="panel">
            <p className="eyebrow">Reserved withdrawals</p>
            <div className="stat">{formatUgx(goal.reserved_withdrawal_minor)}</div>
            <p className="muted">Requested or released amounts that are not yet completed payouts.</p>
          </section>
        </div>

        <div className="grid grid-2" style={{ marginTop: 16 }}>
          <section className="panel">
            <div className="case-card-head">
              <div>
                <p className="eyebrow">Goal details</p>
                <h2>{goal.goal_reference}</h2>
              </div>
              <span className={`badge ${goal.status === "active" || goal.status === "completed" ? "ok" : "warn"}`}>{goal.status}</span>
            </div>
            <table className="table">
              <tbody>
                <tr><th>Product</th><td>{goal.product.name}</td></tr>
                <tr><th>Custody</th><td>{goal.product.partner_name} · partner held</td></tr>
                <tr><th>Target</th><td>{goal.target_amount_minor ? formatUgx(goal.target_amount_minor) : "No fixed target"}</td></tr>
                <tr><th>Target date</th><td>{goal.target_date ?? "No fixed date"}</td></tr>
                <tr><th>Planned contribution</th><td>{goal.scheduled_amount_minor ? formatUgx(goal.scheduled_amount_minor) : "Not set"}</td></tr>
                <tr><th>Reminder frequency</th><td>{goal.contribution_frequency ?? "Not set"}</td></tr>
                <tr><th>Automatic debit</th><td>{goal.autopilot_enabled ? "Enabled" : "Disabled until certified mandate support"}</td></tr>
                <tr><th>Withdrawal notice</th><td>{goal.product.notice_days} day{goal.product.notice_days === 1 ? "" : "s"}</td></tr>
                <tr><th>Initial lock</th><td>{goal.product.lock_days} day{goal.product.lock_days === 1 ? "" : "s"}</td></tr>
              </tbody>
            </table>
            {goal.product.terms_url ? <a className="button secondary" href={goal.product.terms_url}>Read controlled product terms</a> : null}
          </section>

          <section className="panel">
            <h2>Contribution reminder</h2>
            <form action={updateSavingsScheduleAction} className="form-grid">
              <input type="hidden" name="goal_id" value={goal.id} />
              <div className="field">
                <label htmlFor="scheduled_amount_minor">Planned contribution (UGX)</label>
                <input id="scheduled_amount_minor" name="scheduled_amount_minor" type="number" min={1} step={1} defaultValue={goal.scheduled_amount_minor ?? ""} />
              </div>
              <div className="field">
                <label htmlFor="contribution_frequency">Reminder frequency</label>
                <select id="contribution_frequency" name="contribution_frequency" defaultValue={goal.contribution_frequency ?? ""}>
                  <option value="">No reminder</option>
                  <option value="weekly">Weekly</option>
                  <option value="fortnightly">Fortnightly</option>
                  <option value="monthly">Monthly</option>
                  <option value="payday">Payday</option>
                </select>
              </div>
              <p className="muted">This schedule does not authorize recurring debits. Contributions are initiated explicitly until a certified mandate contract is enabled.</p>
              <button className="button" type="submit">Update reminder</button>
            </form>
            <div style={{ marginTop: 12 }}>
              {goal.status === "active" ? (
                <form action={pauseSavingsGoalAction} className="inline-form">
                  <input type="hidden" name="goal_id" value={goal.id} />
                  <button className="button secondary" type="submit">Pause goal</button>
                </form>
              ) : goal.status === "paused" ? (
                <form action={resumeSavingsGoalAction} className="inline-form">
                  <input type="hidden" name="goal_id" value={goal.id} />
                  <button className="button secondary" type="submit">Resume goal</button>
                </form>
              ) : null}
            </div>
          </section>
        </div>

        <div className="grid grid-2" style={{ marginTop: 16 }}>
          <section className="panel">
            <h2>Add to this goal</h2>
            {canContribute ? (
              <form action={contributeSavingsAction} className="form-grid">
                <input type="hidden" name="goal_id" value={goal.id} />
                <input type="hidden" name="idempotency_key" value={contributionKey} />
                <div className="field">
                  <label htmlFor="amount_minor">Contribution amount (UGX)</label>
                  <input id="amount_minor" name="amount_minor" type="number" min={goal.product.minimum_contribution_minor || 1} max={goal.product.maximum_contribution_minor ?? undefined} step={1} required />
                </div>
                <p className="muted">CPay initiates the collection. Payment success alone is not a savings confirmation; the partner must confirm the position before it appears in the confirmed balance.</p>
                <button className="button" type="submit">Initiate contribution</button>
              </form>
            ) : (
              <StateNotice state="empty" message={`Contributions are unavailable while this goal is ${goal.status}.`} />
            )}
          </section>

          <section className="panel">
            <h2>Withdraw confirmed savings</h2>
            {canWithdraw ? (
              <form action={withdrawSavingsAction} className="form-grid">
                <input type="hidden" name="goal_id" value={goal.id} />
                <input type="hidden" name="idempotency_key" value={withdrawalKey} />
                <div className="field">
                  <label htmlFor="withdraw_amount_minor">Withdrawal amount (UGX)</label>
                  <input id="withdraw_amount_minor" name="amount_minor" type="number" min={Math.max(1, goal.product.minimum_withdrawal_minor)} max={goal.available_balance_minor} step={1} required />
                </div>
                <p className="muted">Requesting a withdrawal reserves it from the available position. The savings partner releases the funds under the disclosed notice and lock rules, then CPay executes the payout.</p>
                <button className="button" type="submit">Request withdrawal</button>
              </form>
            ) : (
              <StateNotice state="empty" message="There is no eligible confirmed amount available for withdrawal from this goal." />
            )}
          </section>
        </div>

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>Movement history</h2>
          {movements.length ? (
            <table className="table">
              <thead>
                <tr>
                  <th>Reference</th>
                  <th>Type</th>
                  <th>Amount</th>
                  <th>Product state</th>
                  <th>Payment rail</th>
                  <th>Partner evidence</th>
                </tr>
              </thead>
              <tbody>
                {movements.map((movement) => (
                  <tr key={movement.id}>
                    <td>{movement.movement_reference}</td>
                    <td>{movement.movement_type}</td>
                    <td>{formatUgx(movement.amount_minor)}</td>
                    <td><span className={`badge ${movement.status === "confirmed" || movement.status === "paid" ? "ok" : "warn"}`}>{movement.status}</span></td>
                    <td>{movement.mobile_money_transaction ? `${movement.mobile_money_transaction.provider}: ${movement.mobile_money_transaction.status}` : "Not started / not linked"}</td>
                    <td>{movement.partner_reference ?? (movement.partner_confirmed_at ? "Confirmed" : "Awaiting partner")}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <StateNotice state="empty" message="No savings movements have been recorded for this goal." />
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load savings goal.";

    return (
      <Screen title="Savings goal" description="Review a partner-held savings goal.">
        <StateNotice state={state} message={message} />
        <Link className="button secondary" href="/savings">Back to savings</Link>
      </Screen>
    );
  }
}
