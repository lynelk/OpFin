import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { longRangeApi } from "@/lib/api/long-range";
import { OpfinApiError } from "@/lib/api/errors";
import { saveMicrobusinessFocusedAction } from "@/app/focused-long-range-actions";

function text(value: unknown): string { return typeof value === "string" ? value : ""; }
function amount(value: unknown): number { return typeof value === "number" ? value : Number(value ?? 0); }

export default async function MicrobusinessPage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};
  try {
    const workspace = await longRangeApi.overview(token);
    const business = workspace.microbusiness;
    return (
      <Screen title="Microbusiness" description="Keep business cash flow separate from household money while giving OpFin only the context needed for relevant business-finance journeys.">
        {params.status ? <StateNotice state="success" message="Business picture saved." /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to save your business picture."} /> : null}
        <section className="panel">
          <form action={saveMicrobusinessFocusedAction} className="form-grid">
            <div className="field"><label htmlFor="business_name">Business name</label><input id="business_name" name="business_name" defaultValue={text(business?.business_name)} required /></div>
            <div className="field"><label htmlFor="business_type">What kind of business?</label><input id="business_type" name="business_type" defaultValue={text(business?.business_type)} placeholder="For example: retail, salon, transport" required /></div>
            <div className="field"><label htmlFor="registration_reference">Registration reference, if any</label><input id="registration_reference" name="registration_reference" defaultValue={text(business?.registration_reference)} /></div>
            <div className="field"><label htmlFor="monthly_revenue_minor">Average monthly revenue (UGX)</label><input id="monthly_revenue_minor" name="monthly_revenue_minor" type="number" min="0" defaultValue={amount(business?.monthly_revenue_minor)} /></div>
            <div className="field"><label htmlFor="monthly_expense_minor">Average monthly expenses (UGX)</label><input id="monthly_expense_minor" name="monthly_expense_minor" type="number" min="0" defaultValue={amount(business?.monthly_expense_minor)} /></div>
            <button className="button" type="submit">Save business picture</button>
          </form>
        </section>
        <section className="panel"><Link href="/ecosystem">Back to connected financial life</Link></section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Microbusiness" description="Manage business financial context."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load business context."} /></Screen>;
  }
}
