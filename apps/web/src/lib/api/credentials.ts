import { classifyStatus, OpfinApiError } from "./errors";

type JsonRecord = Record<string, unknown>;

/** Same canonical phone form as the mobile app: 256 followed by nine digits. Anything else is sent as typed. */
export function normalisePhone(raw: string): string {
  const compact = raw.replace(/[\s\-()]/g, "");
  const international = /^\+?256(\d{9})$/.exec(compact);
  if (international) return `256${international[1]}`;
  const local = /^0(\d{9})$/.exec(compact);
  if (local) return `256${local[1]}`;
  return raw.trim();
}

/** Staff sign in with email; everyone else with phone. */
export function identityFields(identifier: string): { email: string } | { phone: string } {
  const value = identifier.trim();
  return value.includes("@") ? { email: value.toLowerCase() } : { phone: normalisePhone(value) };
}

function object(value: unknown): value is JsonRecord {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

async function post(path: string, body: JsonRecord, token?: string): Promise<JsonRecord> {
  const base = process.env.NEXT_PUBLIC_OPFIN_API_URL;
  if (!base) throw new OpfinApiError("server", "OpFin API base URL is not configured.");
  let response: Response;
  try {
    response = await fetch(`${base.replace(/\/$/, "")}${path}`, {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: JSON.stringify(body), cache: "no-store", redirect: "error", signal: AbortSignal.timeout(30000)
    });
  } catch {
    throw new OpfinApiError("network", "OpFin could not be reached. Check your connection and try again.");
  }
  const payload: unknown = await response.json().catch(() => null);
  if (!object(payload)) throw new OpfinApiError("server", "OpFin did not confirm the request. Try again.");
  if (!response.ok || payload.success !== true) {
    // Field messages are plain-language validation text, never secrets.
    const errors: Record<string, string[]> = {};
    if (object(payload.errors)) {
      for (const [key, value] of Object.entries(payload.errors)) {
        if (Array.isArray(value)) errors[key] = value.filter((item): item is string => typeof item === "string");
      }
    }
    const message = typeof payload.message === "string" && payload.message !== "" ? payload.message : "The request could not be completed.";
    throw new OpfinApiError(classifyStatus(response.status), message, response.status, errors);
  }
  return payload;
}

export async function changePassword(token: string | undefined, currentPassword: string, password: string, confirmation: string): Promise<void> {
  if (!token) throw new OpfinApiError("unauthorized", "Sign in again to change your password.", 401);
  await post("/account/password", { current_password: currentPassword, password, password_confirmation: confirmation }, token);
}

export async function requestResetCode(identifier: string): Promise<void> {
  const identity = identityFields(identifier);
  await post("/generate-otp", "email" in identity ? { channel: "email", ...identity } : { channel: "sms", ...identity });
}

export async function resetPassword(identifier: string, code: string, password: string, confirmation: string): Promise<void> {
  await post("/reset-password", { ...identityFields(identifier), otp: code.trim(), password, password_confirmation: confirmation });
}
