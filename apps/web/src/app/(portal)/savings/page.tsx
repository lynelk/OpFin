import Link from "next/link";
import { createSavingsGoalAction } from "@/app/save-protection-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { saveProtectionApi } from "@/lib/api/save-protection";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function SavingsPage({
  searchParams
}: {
  searchParams?: Promise<{ error?: string; message?: string }>;
}) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const [productsResponse, goalsResponse] = await Promise.all([
      saveProtectionApi.savingsProducts("UG", token),
      saveProtectionApi.savingsGoals(token)
    ]);
    const products = productsResponse.data.products;
    const goals = goalsResponse.data.goals;

    return (
      <Screen
        title="Savings"
        description="Create goals using approved partner-held products. Only partner-confirmed contributions count toward the displayed savings position."
        action={<Link className="button secondary" href="/save">Back to Save & Protect</Link>}
      >
        {params?.message ? <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} /> : null}

        <StateNotice state="success" message={productsResponse.data.custody_notice} />

        <div className="grid grid-2" style={{ marginTop: 16 }}>
          <section className="panel">
            <h2>Your goals</h2>
            {goals.length ? (
              <div className="case-list">
                {goals.map((goal) => (
                  <div className="case-card" key={goal.id}>
                    <div className="case-card-head">
                      <div>
                        <p className="eyebrow">{goal.product.name}</p>
                        <h3>{goal.name}</h3>
                      </div>
                      <span className={`badge ${goal.status === "active" || goal.status === "completed" ? "ok" : "warn"}`}>{goal.status}</span>
                    </div>
                    <table className="table">
                      <tbody>
                        <tr><th>Confirmed</th><td>{formatUgx(goal.confirmed_balance_minor)}</td></tr>
                        <tr><th>Available</th><td>{formatUgx(goal.available_balance_minor)}</td></tr>
                        <tr><th>Reserved withdrawal</th><td>{formatUgx(goal.reserved_withdrawal_minor)}</td></tr>
                        <tr><th>Target</th><td>{goal.target_amount_minor ? formatUgx(goal.target_amount_minor) : "No fixed target"}</td></tr>
                      </tbody>
                    </table>
                    <Link className="button secondary" href={`/savings/${goal.id}`}>Open goal</Link>
                  </div>
                ))}
              </div>
            ) : (
              <StateNotice state="empty" message="You do not have a savings goal yet. Choose an approved product and create one below." />
            )}
          </section>

          <section className="panel">
            <h2>Create a savings goal</h2>
            {products.length ? (
              <form action={createSavingsGoalAction} className="form-grid">
                <div className="field">
                  <label htmlFor="savings_product_id">Savings product</label>
                  <select id="savings_product_id" name="savings_product_id" required defaultValue="">
                    <option value="" disabled>Select approved product</option>
                    {products.map((product) => (
                      <option key={product.id} value={product.id}>
                        {product.name} · {product.partner_name}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="field">
                  <label htmlFor="name">Goal name</label>
                  <input id="name" name="name" minLength={2} maxLength={120} required placeholder="Emergency fund" />
                </div>
                <div className="field">
                  <label htmlFor="target_amount_minor">Target amount (UGX, optional)</label>
                  <input id="target_amount_minor" name="target_amount_minor" type="number" min={1} step={1} inputMode="numeric" />
                </div>
                <div className="field">
                  <label htmlFor="target_date">Target date (optional)</label>
                  <input id="target_date" name="target_date" type="date" />
                </div>
                <div className="field">
                  <label htmlFor="scheduled_amount_minor">Planned contribution (UGX, optional)</label>
                  <input id="scheduled_amount_minor" name="scheduled_amount_minor" type="number" min={1} step={1} inputMode="numeric" />
                </div>
                <div className="field">
                  <label htmlFor="contribution_frequency">Reminder frequency</label>
                  <select id="contribution_frequency" name="contribution_frequency" defaultValue="">
                    <option value="">No reminder schedule</option>
                    <option value="weekly">Weekly</option>
                    <option value="fortnightly">Fortnightly</option>
                    <option value="monthly">Monthly</option>
                    <option value="payday">Payday</option>
                  </select>
                </div>
                <p className="muted">A schedule is a reminder only. Automatic debit remains disabled until an approved recurring-payment mandate is available.</p>
                <button className="button" type="submit">Create savings goal</button>
              </form>
            ) : (
              <StateNotice state="empty" message="No approved savings product is currently available for your country." />
            )}
          </section>
        </div>

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>Approved product catalogue</h2>
          {products.length ? (
            <div className="grid grid-2">
              {products.map((product) => (
                <div className="case-card" key={product.id}>
                  <div className="case-card-head">
                    <div>
                      <p className="eyebrow">{product.product_type}</p>
                      <h3>{product.name}</h3>
                    </div>
                    <span className="badge ok">partner held</span>
                  </div>
                  <p className="muted">Held by {product.partner_name}. Minimum contribution {formatUgx(product.minimum_contribution_minor)}.</p>
                  <table className="table">
                    <tbody>
                      <tr><th>Maximum contribution</th><td>{product.maximum_contribution_minor ? formatUgx(product.maximum_contribution_minor) : "Product terms apply"}</td></tr>
                      <tr><th>Minimum withdrawal</th><td>{formatUgx(product.minimum_withdrawal_minor)}</td></tr>
                      <tr><th>Withdrawal notice</th><td>{product.notice_days} day{product.notice_days === 1 ? "" : "s"}</td></tr>
                      <tr><th>Initial lock</th><td>{product.lock_days} day{product.lock_days === 1 ? "" : "s"}</td></tr>
                    </tbody>
                  </table>
                  {product.disclosures?.length ? (
                    <div>
                      <p className="eyebrow">Key disclosures</p>
                      <ul>{product.disclosures.map((item) => <li key={item}>{item}</li>)}</ul>
                    </div>
                  ) : null}
                  {product.terms_url ? <a className="button secondary" href={product.terms_url}>Read controlled terms</a> : null}
                </div>
              ))}
            </div>
          ) : (
            <StateNotice state="empty" message="No approved savings products are currently published." />
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load savings.";

    return (
      <Screen title="Savings" description="Manage approved partner-held savings goals.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
