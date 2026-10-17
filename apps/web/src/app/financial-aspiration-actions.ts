"use server";

import { redirect } from "next/navigation";
import { getAccessToken } from "@/lib/auth/session";
import { financialWellbeingApi } from "@/lib/api/financial-wellbeing";
import { OpfinApiError } from "@/lib/api/errors";
import type { FinancialAspiration } from "@/lib/financial-wellbeing/types";

function value(form: FormData, name: string): string {
  const input = form.get(name);
  return typeof input === "string" ? input.trim() : "";
}

function failure(error: unknown): never {
  const kind = error instanceof OpfinApiError ? error.kind : "validation";
  const message = error instanceof Error ? error.message : "Unable to update your goal.";
  redirect(`/ahead?error=${encodeURIComponent(kind)}&message=${encodeURIComponent(message)}`);
}

export async function createAspirationAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    const amountText = value(formData, "target_amount_minor");
    const amount = amountText === "" ? null : Number(amountText);
    if (amount !== null && (!Number.isSafeInteger(amount) || amount < 0)) {
      throw new Error("Enter a valid target, or leave it blank.");
    }
    const category = value(formData, "category") as FinancialAspiration["category"];
    await financialWellbeingApi.createAspiration({
      title: value(formData, "title"),
      category,
      target_amount_minor: amount,
      target_date: value(formData, "target_date") || null
    }, token);
  } catch (error) {
    failure(error);
  }
  redirect("/ahead?status=created");
}

export async function updateAspirationAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    const reference = value(formData, "reference");
    if (!/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/i.test(reference)) {
      throw new Error("The selected goal is invalid.");
    }
    const requested = value(formData, "intent");
    if (requested === "priority") {
      await financialWellbeingApi.updateAspiration(reference, { is_priority: true }, token);
    } else if (requested === "pause" || requested === "resume" || requested === "archive") {
      const status = requested === "resume" ? "active" : requested === "archive" ? "archived" : "paused";
      await financialWellbeingApi.updateAspiration(reference, { status }, token);
    } else {
      throw new Error("The requested change is not supported.");
    }
  } catch (error) {
    failure(error);
  }
  redirect("/ahead?status=updated");
}
