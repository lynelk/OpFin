"use server";

import { redirect } from "next/navigation";
import { financialWellbeingApi } from "@/lib/api/financial-wellbeing";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

function amount(formData: FormData, key: string): number {
  const parsed = Number(value(formData, key));
  if (!Number.isFinite(parsed) || parsed < 0) throw new Error("Enter a valid non-negative amount.");
  return Math.round(parsed);
}

function fail(error: unknown, destination: string): never {
  const kind = error instanceof OpfinApiError ? error.kind : "validation";
  const message = error instanceof Error ? error.message : "Unable to save your financial plan.";
  redirect(`${destination}?error=${encodeURIComponent(kind)}&message=${encodeURIComponent(message)}`);
}

export async function recordFinancialAccountAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await financialWellbeingApi.createAccount({
      display_name: value(formData, "display_name"),
      account_type: value(formData, "account_type"),
      balance_minor: amount(formData, "balance_minor"),
      currency: "UGX",
      observed_at: new Date().toISOString()
    }, token);
  } catch (error) {
    fail(error, "/money");
  }
  redirect("/money?status=balance-recorded");
}

export async function createBudgetAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await financialWellbeingApi.createBudget({
      category: value(formData, "category"),
      monthly_limit_minor: amount(formData, "monthly_limit_minor"),
      effective_from: value(formData, "effective_from"),
      alert_threshold_percent: 80,
      currency: "UGX"
    }, token);
  } catch (error) {
    fail(error, "/money");
  }
  redirect("/money?status=budget-created");
}

export async function recordCashFlowEntryAction(formData: FormData) {
  const token = await getAccessToken();
  const direction = value(formData, "direction") === "income" ? "income" : "expense";
  try {
    await financialWellbeingApi.createEntry({
      direction,
      amount_minor: amount(formData, "amount_minor"),
      description: value(formData, "description") || undefined,
      category: value(formData, "category") || undefined,
      occurred_at: value(formData, "occurred_at") || new Date().toISOString(),
      currency: "UGX"
    }, token);
  } catch (error) {
    fail(error, "/money");
  }
  redirect("/money?status=entry-recorded");
}

export async function updateCashFlowCategoryAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await financialWellbeingApi.updateEntryCategory(
      Number(value(formData, "entry_id")),
      value(formData, "category"),
      token
    );
  } catch (error) {
    fail(error, "/money");
  }
  redirect("/money?status=category-updated");
}

export async function createFinancialCalendarEventAction(formData: FormData) {
  const token = await getAccessToken();
  const direction = value(formData, "direction") === "income" ? "income" : "expense";
  const certainty = value(formData, "certainty") === "estimated" ? "estimated" : "scheduled";
  const recurrenceValue = value(formData, "recurrence");
  const recurrence = recurrenceValue === "weekly" || recurrenceValue === "monthly" ? recurrenceValue : undefined;
  try {
    await financialWellbeingApi.createCalendarEvent({
      title: value(formData, "title"),
      event_type: value(formData, "event_type"),
      direction,
      amount_minor: amount(formData, "amount_minor"),
      scheduled_for: value(formData, "scheduled_for"),
      certainty,
      recurrence,
      category: value(formData, "category") || undefined,
      notes: value(formData, "notes") || undefined,
      currency: "UGX"
    }, token);
  } catch (error) {
    fail(error, "/calendar");
  }
  redirect("/calendar?status=event-created");
}
