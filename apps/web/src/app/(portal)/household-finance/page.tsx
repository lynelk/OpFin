import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { longRangeApi } from "@/lib/api/long-range";
import { OpfinApiError } from "@/lib/api/errors";
import { saveHouseholdFocusedAction } from "@/app/focused-long-range-actions";

function amount(value: unknown): number { return typeof value === "number" ? value : Number(value ?? 0); }

export default async function HouseholdFinancePage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};
  try {
    const workspace = await longRangeApi.overview(token);
    const household = workspace.household;
    return (
      <Screen title="Household" description="Add only the household numbers that help OpFin understand commitments, resilience and shared planning. Dependants are never silently made borrowers or guarantors.">
        {params.status ? <StateNotice state="success" message="Household picture saved." /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to save your household picture."} /> : null}
        <section className="panel">
          <form action={saveHouseholdFocusedAction} className="form-grid">
            <div className="field"><label htmlFor="household_size">People in household</label><input id="household_size" name="household_size" type="number" min="1" max="50" defaultValue={amount(household?.household_size) || 1} required /></div>
            <div className="field"><label htmlFor="monthly_income_minor">Monthly household income (UGX)</label><input id="monthly_income_minor" name="monthly_income_minor" type="number" min="0" defaultValue={amount(household?.monthly_income_minor)} /></div>
            <div className="field"><label htmlFor="monthly_fixed_costs_minor">Monthly fixed costs (UGX)</label><input id="monthly_fixed_costs_minor" name="monthly_fixed_costs_minor" type="number" min="0" defaultValue={amount(household?.monthly_fixed_costs_minor)} /></div>
            <div className="field"><label htmlFor="emergency_target_minor">Emergency savings target (UGX)</label><input id="emergency_target_minor" name="emergency_target_minor" type="number" min="0" defaultValue={amount(household?.emergency_target_minor)} /></div>
            <button className="button" type="submit">Save household picture</button>
          </form>
        </section>
        <section className="panel"><Link href="/ecosystem">Back to connected financial life</Link></section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Household" description="Manage household financial context."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load household context."} /></Screen>;
  }
}
