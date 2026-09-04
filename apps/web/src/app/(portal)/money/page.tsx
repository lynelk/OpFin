import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { financialWellbeingApi } from "@/lib/api/financial-wellbeing";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";
import {
  createBudgetAction,
  recordCashFlowEntryAction,
  recordFinancialAccountAction,
  updateCashFlowCategoryAction
} from "@/app/financial-wellbeing-actions";

function money(value: number | null): string {
  return value === null ? "Not available" : formatUgx(value);
}

export default async function MoneyPlanPage({
  searchParams
}: Readonly<{
  searchParams?: Promise<{ status?: string; error?: string; message?: string }>;
}>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};

  try {
    const [compassResponse, accountsResponse, categoriesResponse] = await Promise.all([
      financialWellbeingApi.compass(token),
      financialWellbeingApi.accounts(token),
      financialWellbeingApi.categories(token)
    ]);
    const compass = compassResponse.data;
    const position = compass.position;
    const accounts = accountsResponse.data.accounts;
    const categories = categoriesResponse.data.categories;
    const today = new Date().toISOString().slice(0, 10);

    return (
      <Screen
        title="Money Plan"
        description="See recorded cash, commitments, budgets and actual spending in one explainable view."
      >
        {params.status ? <StateNotice state="success" message="Your money plan was updated." /> : null}
        {params.error ? (
          <StateNotice
            state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"}
            message={params.message ?? "Unable to update your money plan."}
          />
        ) : null}

        <section className="panel">
          <div className="case-card-head">
            <div>
              <p className="eyebrow">Financial position</p>
              <h2>What is safe to use today</h2>
            </div>
            <Link className="button secondary" href="/calendar">Open calendar</Link>
          </div>
          <div className="grid grid-3">
            <div>
              <span className="muted">Recorded available money</span>
              <div className="stat">{money(position.available_money_minor)}</div>
              <small className="muted">Confidence: {position.available_money_confidence?.replaceAll("_", " ") ?? "No balance source"}</small>
            </div>
            <div>
              <span className="muted">Committed next 30 days</span>
              <div className="stat">{formatUgx(position.committed_money_minor)}</div>
              <small className="muted">Confirmed and scheduled obligations only.</small>
            </div>
            <div>
              <span className="muted">Safe to spend</span>
              <div className="stat">{money(position.safe_to_spend_minor)}</div>
              <small className="muted">{position.safe_to_spend_explanation}</small>
            </div>
          </div>
          <div className="grid grid-3">
            <div><strong>Debt obligations</strong><p>{formatUgx(position.debt_obligations_minor)}</p></div>
            <div><strong>Confirmed savings</strong><p>{formatUgx(position.current_savings_minor)}</p></div>
            <div><strong>Upcoming obligations</strong><p>{formatUgx(position.upcoming_obligations_minor)}</p></div>
          </div>
        </section>

        <div className="grid grid-2">
          <section className="panel">
            <h2>Current balances</h2>
            <p className="muted">Only record a balance you actually know. User-entered balances stay labelled as user reported.</p>
            {accounts.length === 0 ? <p className="muted">No current balance has been recorded yet.</p> : (
              <div className="case-list">
                {accounts.map((account) => (
                  <article className="case-card" key={account.id}>
                    <div className="case-card-head">
                      <strong>{account.display_name}</strong>
                      <span className="badge">{account.confidence.replaceAll("_", " ")}</span>
                    </div>
                    <div className="stat">{formatUgx(account.balance_minor)}</div>
                    <p className="muted">{account.account_type.replaceAll("_", " ")}</p>
                  </article>
                ))}
              </div>
            )}
            <form action={recordFinancialAccountAction} className="form-grid">
              <div className="field">
                <label htmlFor="display_name">Balance source</label>
                <input id="display_name" name="display_name" required maxLength={120} placeholder="e.g. Primary mobile money" />
              </div>
              <div className="grid grid-2">
                <div className="field">
                  <label htmlFor="account_type">Type</label>
                  <select id="account_type" name="account_type" defaultValue="mobile_money">
                    <option value="mobile_money">Mobile money</option>
                    <option value="bank">Bank</option>
                    <option value="cash">Cash</option>
                    <option value="other">Other</option>
                  </select>
                </div>
                <div className="field">
                  <label htmlFor="balance_minor">Current balance (UGX)</label>
                  <input id="balance_minor" name="balance_minor" type="number" min="0" step="1" required />
                </div>
              </div>
              <button className="button" type="submit">Record current balance</button>
            </form>
          </section>

          <section className="panel">
            <h2>This month&apos;s cash flow</h2>
            <div className="grid grid-3">
              <div><span className="muted">Income</span><div className="stat">{formatUgx(compass.cash_flow.income_minor)}</div></div>
              <div><span className="muted">Spent</span><div className="stat">{formatUgx(compass.cash_flow.expense_minor)}</div></div>
              <div><span className="muted">Net</span><div className="stat">{formatUgx(compass.cash_flow.net_minor)}</div></div>
            </div>
            <form action={recordCashFlowEntryAction} className="form-grid">
              <div className="grid grid-2">
                <div className="field">
                  <label htmlFor="direction">Entry type</label>
                  <select id="direction" name="direction" defaultValue="expense">
                    <option value="expense">Expense</option>
                    <option value="income">Income</option>
                  </select>
                </div>
                <div className="field">
                  <label htmlFor="amount_minor">Amount (UGX)</label>
                  <input id="amount_minor" name="amount_minor" type="number" min="0" step="1" required />
                </div>
              </div>
              <div className="field">
                <label htmlFor="description">Description</label>
                <input id="description" name="description" maxLength={255} placeholder="e.g. Supermarket groceries" />
              </div>
              <div className="grid grid-2">
                <div className="field">
                  <label htmlFor="category">Category</label>
                  <select id="category" name="category" defaultValue="">
                    <option value="">Auto-categorise</option>
                    {categories.map((category) => <option value={category} key={category}>{category}</option>)}
                  </select>
                </div>
                <div className="field">
                  <label htmlFor="occurred_at">Date</label>
                  <input id="occurred_at" name="occurred_at" type="date" defaultValue={today} required />
                </div>
              </div>
              <button className="button" type="submit">Record entry</button>
            </form>
          </section>
        </div>

        <section className="panel">
          <h2>Monthly budgets</h2>
          <div className="grid grid-3">
            {compass.budgets.length === 0 ? <p className="muted">No category budgets have been set yet.</p> : compass.budgets.map((budget) => (
              <article className="case-card" key={budget.id}>
                <div className="case-card-head">
                  <strong>{budget.category}</strong>
                  <span className="badge">{budget.status.replaceAll("_", " ")}</span>
                </div>
                <p>{formatUgx(budget.actual_minor)} of {formatUgx(budget.monthly_limit_minor)}</p>
                <p className="muted">{budget.utilization_percent}% used · {formatUgx(budget.remaining_minor)} remaining</p>
              </article>
            ))}
          </div>
          <form action={createBudgetAction} className="form-grid">
            <div className="grid grid-3">
              <div className="field">
                <label htmlFor="budget_category">Category</label>
                <select id="budget_category" name="category" required defaultValue="Food">
                  {categories.map((category) => <option value={category} key={category}>{category}</option>)}
                </select>
              </div>
              <div className="field">
                <label htmlFor="monthly_limit_minor">Monthly limit (UGX)</label>
                <input id="monthly_limit_minor" name="monthly_limit_minor" type="number" min="0" step="1" required />
              </div>
              <div className="field">
                <label htmlFor="effective_from">Starts</label>
                <input id="effective_from" name="effective_from" type="date" defaultValue={`${today.slice(0, 8)}01`} required />
              </div>
            </div>
            <button className="button" type="submit">Set budget</button>
          </form>
        </section>

        <section className="panel">
          <h2>Recent recorded cash flow</h2>
          {compass.cash_flow.entries.length === 0 ? <p className="muted">No entries recorded this month.</p> : (
            <div className="case-list">
              {compass.cash_flow.entries.slice(0, 12).map((entry) => (
                <article className="case-card" key={entry.id}>
                  <div className="case-card-head">
                    <strong>{entry.description || entry.category}</strong>
                    <span className="badge">{entry.direction}</span>
                  </div>
                  <p>{formatUgx(entry.amount_minor)} · {entry.category}</p>
                  <p className="muted">{new Date(entry.occurred_at).toLocaleDateString("en-UG")}</p>
                  {entry.direction === "expense" ? (
                    <form action={updateCashFlowCategoryAction} className="grid grid-2">
                      <input type="hidden" name="entry_id" value={entry.id} />
                      <div className="field">
                        <label htmlFor={`entry-category-${entry.id}`}>Correct category</label>
                        <select id={`entry-category-${entry.id}`} name="category" defaultValue={entry.category}>
                          {categories.map((category) => <option value={category} key={category}>{category}</option>)}
                        </select>
                      </div>
                      <button className="button secondary" type="submit">Save category</button>
                    </form>
                  ) : null}
                </article>
              ))}
            </div>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load your money plan.";
    return (
      <Screen title="Money Plan" description="Your financial position will appear here when the service is available.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
