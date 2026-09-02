"use server";

import { redirect } from "next/navigation";
import { getAccessToken } from "@/lib/auth/session";
import { OpfinApiError } from "@/lib/api/errors";
import { submitSimpleCreditApplication } from "@/lib/api/simple-credit";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

function fail(error: unknown): never {
  const kind = error instanceof OpfinApiError ? error.kind : "server";
  const message = error instanceof Error ? error.message : "Unable to submit your request.";
  redirect(`/loans/apply?error=${encodeURIComponent(kind)}&message=${encodeURIComponent(message)}`);
}

export async function submitSimpleLoanApplicationAction(formData: FormData) {
  const token = await getAccessToken();
  let applicationId: number | undefined;

  try {
    const amountMinor = Number(value(formData, "amount"));
    if (!Number.isFinite(amountMinor) || amountMinor <= 0) throw new Error("Enter the amount you need.");
    const reason = value(formData, "reason");
    if (!reason) throw new Error("Tell OpFin what you need the money for.");

    const response = await submitSimpleCreditApplication({ amount_minor: amountMinor, reason }, token);
    applicationId = response.data.application.id;
  } catch (error) {
    fail(error);
  }

  redirect(applicationId ? `/loans/decision?application=${applicationId}&status=submitted` : "/loans/decision?status=submitted");
}
