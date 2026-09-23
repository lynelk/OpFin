"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";

const COOKIE_OPTIONS = {
  httpOnly: true,
  sameSite: "lax" as const,
  secure: process.env.NODE_ENV === "production",
  path: "/",
  maxAge: 10 * 60
};

function value(formData: FormData, key: string): string {
  return formData.get(key)?.toString().trim() ?? "";
}

async function apiRequest<T>(path: string, body: Record<string, unknown>): Promise<T> {
  const baseUrl = process.env.NEXT_PUBLIC_OPFIN_API_URL;
  if (!baseUrl) throw new Error("OpFin API base URL is not configured.");

  const response = await fetch(baseUrl + path, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify(body),
    cache: "no-store"
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok || payload.success !== true) {
    throw new Error(payload.message ?? "Partner activation request failed.");
  }

  return payload.data as T;
}

function fail(step: string, error: unknown): never {
  const message = error instanceof Error ? error.message : "Partner activation failed.";
  redirect(
    "/programme-partner/activate?step=" +
      encodeURIComponent(step) +
      "&error=" +
      encodeURIComponent(message)
  );
}

export async function requestProgrammePartnerOtpAction(formData: FormData) {
  const invitationToken = value(formData, "invitation_token");
  const phone = value(formData, "phone");
  if (!invitationToken || !phone) {
    fail("request", new Error("Invitation token and phone number are required."));
  }

  try {
    await apiRequest("/generate-otp", { phone });
    const store = await cookies();
    store.set("opfin_partner_invitation_token", invitationToken, COOKIE_OPTIONS);
    store.set("opfin_partner_activation_phone", phone, COOKIE_OPTIONS);
  } catch (error) {
    fail("request", error);
  }

  redirect("/programme-partner/activate?step=verify");
}

export async function verifyProgrammePartnerOtpAction(formData: FormData) {
  const store = await cookies();
  const phone = store.get("opfin_partner_activation_phone")?.value;
  const otp = value(formData, "otp");
  if (!phone || !otp) {
    fail("request", new Error("Start the activation again."));
  }

  try {
    const data = await apiRequest<{ verification_token: string }>("/verify-otp", {
      phone,
      otp
    });
    store.set(
      "opfin_partner_phone_verification_token",
      data.verification_token,
      COOKIE_OPTIONS
    );
  } catch (error) {
    fail("verify", error);
  }

  redirect("/programme-partner/activate?step=activate");
}

export async function activateProgrammePartnerAction(formData: FormData) {
  const store = await cookies();
  const invitationToken = store.get("opfin_partner_invitation_token")?.value;
  const phone = store.get("opfin_partner_activation_phone")?.value;
  const verificationToken =
    store.get("opfin_partner_phone_verification_token")?.value;

  if (!invitationToken || !phone || !verificationToken) {
    fail("request", new Error("The activation session expired. Start again."));
  }

  try {
    await apiRequest("/programme-partner/invitations/accept", {
      token: invitationToken,
      phone,
      verification_token: verificationToken,
      name: value(formData, "name"),
      first_name: value(formData, "first_name") || undefined,
      last_name: value(formData, "last_name") || undefined,
      email: value(formData, "email") || undefined,
      pin: value(formData, "pin"),
      pin_confirmation: value(formData, "pin_confirmation"),
      preferred_language: value(formData, "preferred_language") || "en",
      terms_accepted: formData.get("terms_accepted") === "on"
    });
  } catch (error) {
    fail("activate", error);
  }

  store.delete("opfin_partner_invitation_token");
  store.delete("opfin_partner_activation_phone");
  store.delete("opfin_partner_phone_verification_token");

  redirect("/login?status=partner-activated");
}
