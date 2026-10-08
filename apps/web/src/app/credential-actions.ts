"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { changePassword, requestResetCode, resetPassword } from "@/lib/api/credentials";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { homeForRole } from "@/lib/access";
import type { UserRole } from "@/lib/types";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw : "";
}

// Only plain-language messages travel in the URL: never a code, password or account detail.
function fail(path: string, error: unknown, extra: Record<string, string> = {}): never {
  const message = error instanceof OpfinApiError
    ? Object.values(error.errors).flat()[0] ?? error.message
    : "The request could not be completed. Try again.";
  redirect(`${path}?${new URLSearchParams({ error: "1", message, ...extra }).toString()}`);
}

export async function changePasswordAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await changePassword(token, value(formData, "current_password"), value(formData, "password"), value(formData, "password_confirmation"));
  } catch (error) {
    return fail("/account/change-password", error);
  }
  const cookieStore = await cookies();
  cookieStore.delete("opfin_password_change_required");
  const role = (cookieStore.get("opfin_role")?.value ?? "customer") as UserRole;
  redirect(`${homeForRole(role)}?password=changed`);
}

export async function requestResetCodeAction(formData: FormData) {
  const identifier = value(formData, "identifier").trim();
  if (identifier === "") redirect("/forgot-password?error=1&message=" + encodeURIComponent("Enter your email or phone number."));
  try {
    await requestResetCode(identifier);
  } catch (error) {
    return fail("/forgot-password", error);
  }
  // The identifier is kept only in a short-lived, HTTP-only cookie, not in the URL.
  const cookieStore = await cookies();
  cookieStore.set("opfin_reset_identifier", identifier, { httpOnly: true, sameSite: "lax", secure: process.env.NODE_ENV === "production", path: "/", maxAge: 600 });
  redirect("/reset-password");
}

export async function resetPasswordAction(formData: FormData) {
  const cookieStore = await cookies();
  const identifier = value(formData, "identifier").trim() || cookieStore.get("opfin_reset_identifier")?.value || "";
  if (identifier === "") redirect("/forgot-password?error=1&message=" + encodeURIComponent("Start again and enter your email or phone number."));
  try {
    await resetPassword(identifier, value(formData, "code"), value(formData, "password"), value(formData, "password_confirmation"));
  } catch (error) {
    return fail("/reset-password", error);
  }
  cookieStore.delete("opfin_reset_identifier");
  redirect("/login?status=password-reset");
}
