"use server";

import { redirect } from "next/navigation";
import { opfinApi } from "@/lib/api/client";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

export async function createCustomerSupportCaseAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await opfinApi.createCustomerSupportCase({
      category: value(formData, "category"),
      subject: value(formData, "subject"),
      description: value(formData, "description"),
      related_type: value(formData, "related_type") || undefined,
      related_reference: value(formData, "related_reference") || undefined
    }, token);
  } catch (error) {
    const kind = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Support case creation failed";
    redirect(`/support?error=${encodeURIComponent(kind)}&message=${encodeURIComponent(message)}`);
  }

  redirect("/support?status=created");
}
