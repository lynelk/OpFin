"use server";

import { getAccessToken } from "@/lib/auth/session";
import { redirect } from "next/navigation";

const apiBase = process.env.OPFIN_API_URL || process.env.NEXT_PUBLIC_OPFIN_API_URL;

async function call(path: string, method: string, body?: object): Promise<{ data?: Record<string, unknown>; message?: string }> {
  const token = await getAccessToken();
  if (!token || !apiBase || process.env.NEXT_PUBLIC_USE_MOCK_API === "true") {
    throw new Error("Sign in to a configured OpFin environment before creating an organisation.");
  }
  const response = await fetch(apiBase.replace(/\/$/, "") + path, {
    method,
    headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` },
    ...(body ? { body: JSON.stringify(body) } : {}),
    cache: "no-store"
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(typeof payload.message === "string" ? payload.message : "Organisation request failed.");
  return payload;
}

export async function createOrganisation(form: FormData): Promise<void> {
  const name = String(form.get("name") || "").trim();
  const type = String(form.get("type") || "");
  if (name.length < 2 || name.length > 160 || !["business", "sacco", "investment_fund", "partner", "investment_club", "savings_group"].includes(type)) {
    redirect("/setup/organisation?error=invalid");
  }
  let id: number | undefined;
  try {
    const result = await call("/financial-spaces", "POST", { name, type, country: "UG", currency: "UGX" });
    const space = result.data?.space as { id?: number } | undefined;
    id = space?.id;
    if (!id) throw new Error("No organisation ID returned.");
  } catch {
    redirect("/setup/organisation?error=create");
  }
  redirect(`/setup/organisation?space=${id}`);
}

export async function saveOrganisationStage(form: FormData): Promise<void> {
  const id = Number(form.get("space"));
  const stage = String(form.get("stage") || "profile");
  const next = String(form.get("next") || "kyb");
  const submitted = form.get("submit") === "yes";
  if (!Number.isSafeInteger(id) || id < 1 || !["profile", "kyb", "regulatory", "products", "integration", "certification"].includes(stage)) {
    redirect("/setup/organisation?error=invalid");
  }
  const requirements = {
    organisation_name: String(form.get("organisation_name") || "").slice(0, 160),
    registration_number: String(form.get("registration_number") || "").slice(0, 100),
    contact_email: String(form.get("contact_email") || "").slice(0, 254),
    website: String(form.get("website") || "").slice(0, 254)
  };
  try {
    await call(`/financial-spaces/${id}/organisation-onboarding`, "PUT", {
      stage, requirements, evidence: {}, submit: submitted
    });
  } catch {
    redirect(`/setup/organisation?space=${id}&error=save`);
  }
  redirect(`/setup/organisation?space=${id}&stage=${encodeURIComponent(next)}&saved=1`);
}
