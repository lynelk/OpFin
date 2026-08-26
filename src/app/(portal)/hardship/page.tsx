import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { OpfinApiError } from "@/lib/api/errors";
import { v5P0Api } from "@/lib/api/v5-p0";
import { formatUgx } from "@/lib/format";
import { openHardshipAction } from "@/app/v5-p0-actions";

export default async function HardshipPage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};
  try {
    const response = await v5P0Api.hardship(token);
    const cases = response.data;
    return (
      <Screen title="Financial Shock & Hardship" description="Tell OpFin when your financial position changes so relief can be assessed transparently instead of pushing you deeper into debt.">
        {params.status ? <StateNotice state="success" message="Hardship request submitted for review." /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to submit hardship request."} /> : null}
        <section className="panel">
          <h2>Request assistance</h2>
          <form action={openHardshipAction} className="form-grid">
            <div className="field"><label htmlFor="reason">What changed?</label><textarea id="reason" name="reason" required rows={4} placeholder="Describe the income loss, emergency or other financial shock." /></div>
            <div className="grid grid-3">
              <div className="field"><label htmlFor="monthly_income_minor">Monthly income (UGX)</label><input id="monthly_income_minor" name="monthly_income_minor" type="number" min="0" required /></div>
              <div className="field"><label htmlFor="essential_expenses_minor">Essential expenses (UGX)</label><input id="essential_expenses_minor" name="essential_expenses_minor" type="number" min="0" required /></div>
              <div className="field"><label htmlFor="debt_commitments_minor">Debt commitments (UGX)</label><input id="debt_commitments_minor" name="debt_commitments_minor" type="number" min="0" required /></div>
            </div>
            <div className="field"><label htmlFor="requested_relief">Requested relief, one item per line</label><textarea id="requested_relief" name="requested_relief" rows={4} placeholder={"Payment-date change\nTemporary repayment reduction"} /></div>
            <button className="button" type="submit">Submit for review</button>
          </form>
        </section>
        <section className="panel">
          <h2>Your hardship cases</h2>
          {cases.length === 0 ? <p className="muted">You have no hardship cases.</p> : (
            <div className="case-list">{cases.map((item) => (
              <article className="case-card" key={item.id}>
                <div className="case-card-head"><strong>Case #{item.id}</strong><span className="badge">{item.status}</span></div>
                <p>{item.reason}</p>
                <p className="muted">Income {formatUgx(item.monthly_income_minor)} · essentials {formatUgx(item.essential_expenses_minor)} · debt {formatUgx(item.debt_commitments_minor)}</p>
              </article>
            ))}</div>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Financial Shock & Hardship" description="Hardship support will appear here when the service is available."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load hardship support."} /></Screen>;
  }
}
