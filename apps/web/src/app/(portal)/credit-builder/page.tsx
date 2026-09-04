import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { OpfinApiError } from "@/lib/api/errors";
import { v5P0Api } from "@/lib/api/v5-p0";
import { formatUgx } from "@/lib/format";
import { saveCreditBuilderAction } from "@/app/v5-p0-actions";

function actionsText(actions: string | string[] | null | undefined): string {
  if (Array.isArray(actions)) return actions.join("\n");
  if (!actions) return "";
  try {
    const parsed = JSON.parse(actions);
    return Array.isArray(parsed) ? parsed.join("\n") : String(actions);
  } catch {
    return actions;
  }
}

export default async function CreditBuilderPage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};
  try {
    const response = await v5P0Api.creditBuilder(token);
    const data = response.data;
    return (
      <Screen title="Credit Builder" description="Build a practical credit-improvement plan from confirmed OpFin repayment behaviour, without inventing a bureau score.">
        {params.status ? <StateNotice state="success" message="Credit Builder plan saved." /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to save your plan."} /> : null}
        <section className="panel">
          <div className="grid grid-3">
            <div><span className="muted">Outstanding debt</span><div className="stat">{formatUgx(data.factors.outstanding_debt_minor)}</div></div>
            <div><span className="muted">Overdue instalments</span><div className="stat">{data.factors.overdue_instalments}</div></div>
            <div><span className="muted">Repayment signal</span><div className="stat">{data.factors.on_time_signal}</div></div>
          </div>
          <p className="muted">{data.explanation}</p>
        </section>
        <section className="panel">
          <h2>Your improvement plan</h2>
          <form action={saveCreditBuilderAction} className="form-grid">
            <div className="field"><label htmlFor="goal">Goal</label><input id="goal" name="goal" maxLength={255} defaultValue={data.plan?.goal ?? ""} placeholder="e.g. Maintain on-time repayments for six months" /></div>
            <div className="grid grid-2">
              <div className="field"><label htmlFor="target_score">Target score, if you have a verified baseline</label><input id="target_score" name="target_score" type="number" min="0" max="100" defaultValue={data.plan?.target_score ?? ""} /></div>
              <div className="field"><label htmlFor="review_due_at">Review date</label><input id="review_due_at" name="review_due_at" type="date" defaultValue={data.plan?.review_due_at?.slice(0, 10) ?? ""} /></div>
            </div>
            <div className="field"><label htmlFor="actions">Actions, one per line</label><textarea id="actions" name="actions" rows={5} defaultValue={actionsText(data.plan?.actions)} placeholder={"Pay each instalment before its due date\nKeep borrowing within affordability limits"} /></div>
            <button className="button" type="submit">Save Credit Builder plan</button>
          </form>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Credit Builder" description="Your confirmed repayment signals and improvement plan will appear here."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load Credit Builder."} /></Screen>;
  }
}
