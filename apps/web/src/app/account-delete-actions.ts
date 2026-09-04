"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { deleteAccount, type AccountDeletionResult } from "@/lib/api/account";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

export async function deleteAccountAction(formData: FormData) {
  const password = value(formData, "password");
  const confirmation = value(formData, "confirmation");
  if (confirmation !== "DELETE") {
    redirect("/account/delete?error=validation&message=Type%20DELETE%20to%20confirm%20account%20deletion.");
  }

  const token = await getAccessToken();
  let result: AccountDeletionResult;
  try {
    result = await deleteAccount(password, token);
  } catch (error) {
    if (error instanceof OpfinApiError) {
      const params = new URLSearchParams({ error: error.kind, message: error.message });
      redirect(`/account/delete?${params.toString()}`);
    }
    redirect("/account/delete?error=server&message=Account%20deletion%20could%20not%20be%20completed.");
  }

  if (result.deletion_status === "pending_obligations") {
    const params = new URLSearchParams({
      status: "pending",
      case: result.case_number ?? "",
      message: result.message
    });
    redirect(`/account/delete?${params.toString()}`);
  }

  const cookieStore = await cookies();
  for (const name of ["opfin_access_token", "opfin_role", "opfin_name", "opfin_demo_consent"]) {
    cookieStore.delete(name);
  }

  redirect("/login?status=account-deleted");
}
