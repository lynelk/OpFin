"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";

type Result = { ok: boolean; message: string };
type ApiPayload = { success?: boolean; message?: string; data?: { verification_token?: string; access_token?: string; user?: { role?: string; name?: string } } };
const API_URL = process.env.OPFIN_API_URL || process.env.NEXT_PUBLIC_OPFIN_API_URL;
const verifiedCookie = "opfin_signup_verified";
const SIGNUP_POLICY_VERSION = "onboarding-web-v1";

function normalisePhone(raw: string): string | null {
  const phone = raw.replace(/[\s()-]/g, "");
  if (/^07\d{8}$/.test(phone)) return "256" + phone.slice(1);
  if (/^\+2567\d{8}$/.test(phone)) return phone.slice(1);
  if (/^2567\d{8}$/.test(phone)) return phone;
  return null;
}

async function post(path: string, data: object): Promise<{ ok: boolean; payload: ApiPayload }> {
  if (!API_URL || process.env.NEXT_PUBLIC_USE_MOCK_API === "true") {
    return { ok: false, payload: { message: "Registration requires a configured live OpFin API." } };
  }
  try {
    const response = await fetch(API_URL.replace(/\/$/, "") + path, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(data),
      cache: "no-store"
    });
    const payload = await response.json().catch(() => ({}));
    return { ok: response.ok && payload.success === true, payload: payload as ApiPayload };
  } catch {
    return { ok: false, payload: { message: "Unable to connect to OpFin. Try again later." } };
  }
}

export async function startSignup(phoneInput: string): Promise<Result> {
  const phone = normalisePhone(phoneInput);
  if (!phone) return { ok: false, message: "Enter a valid Ugandan mobile phone number." };
  (await cookies()).delete(verifiedCookie);
  const response = await post("/generate-otp", { phone, purpose: "REGISTRATION", otp_channel: "sms" });
  return { ok: response.ok, message: response.ok ? "Verification code sent." : response.payload.message || "Could not send a code." };
}

export async function verifySignup(phoneInput: string, otp: string): Promise<Result> {
  const phone = normalisePhone(phoneInput);
  if (!phone || !/^\d{6}$/.test(otp)) return { ok: false, message: "Enter a valid phone and six-digit code." };
  const response = await post("/verify-otp", { phone, otp });
  const token = response.payload?.data?.verification_token;
  if (!response.ok || typeof token !== "string" || !/^[a-f0-9]{64}$/i.test(token)) {
    return { ok: false, message: response.payload.message || "Verification failed." };
  }
  (await cookies()).set(verifiedCookie, JSON.stringify({ phone, token }), {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "strict",
    path: "/signup",
    maxAge: 600
  });
  return { ok: true, message: "Phone verified." };
}

export async function finishSignup(formData: FormData): Promise<Result> {
  const record = (await cookies()).get(verifiedCookie)?.value;
  if (!record) return { ok: false, message: "Verify your phone before continuing." };
  let verified: { phone: string; token: string };
  try { verified = JSON.parse(record); }
  catch { return { ok: false, message: "Phone verification expired. Start again." }; }
  const firstName = String(formData.get("first_name") || "").trim();
  const lastName = String(formData.get("last_name") || "").trim();
  const pin = String(formData.get("pin") || "");
  const confirmation = String(formData.get("pin_confirmation") || "");
  const accepted = formData.get("terms_accepted") === "yes";
  if (!firstName || !lastName || firstName.length > 80 || lastName.length > 80) return { ok: false, message: "Enter your first and last names." };
  if (!/^\d{6}$/.test(pin) || pin !== confirmation) return { ok: false, message: "Enter matching six-digit PINs." };
  if (!accepted) return { ok: false, message: "Accept the terms and privacy notice to continue." };
  const response = await post("/register", {
    phone: verified.phone, verification_token: verified.token,
    first_name: firstName, last_name: lastName,
    pin, pin_confirmation: confirmation,
    terms_accepted: true, terms_policy_version: SIGNUP_POLICY_VERSION, acquisition_channel: "web"
  });
  if (!response.ok) return { ok: false, message: response.payload.message || "Registration could not be completed." };
  const data = response.payload.data;
  if (!data?.access_token || data?.user?.role !== "customer") return { ok: false, message: "Registration response was incomplete. Contact support." };
  const store = await cookies();
  store.delete(verifiedCookie);
  const sessionOptions = { httpOnly: true, secure: process.env.NODE_ENV === "production", sameSite: "lax" as const, path: "/", maxAge: 60 * 60 * 8 };
  store.set("opfin_access_token", data.access_token, sessionOptions);
  store.set("opfin_role", data.user.role, sessionOptions);
  store.set("opfin_name", encodeURIComponent(data.user.name || firstName), sessionOptions);
  redirect("/dashboard");
}
