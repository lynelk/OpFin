"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { deleteAccount, deleteOptionalAccountData, type AccountDeletionResult } from "@/lib/api/account";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}
function failure(error: unknown): never {
  // Obligation amounts, references and contacts never enter a redirect URL.
  const kind = error instanceof OpfinApiError ? error.kind : "server";
  redirect(`/account/delete?error=${encodeURIComponent(kind)}`);
}
export async function deleteAccountAction(formData: FormData) {
  if (value(formData, "confirmation") !== "DELETE") redirect("/account/delete?error=validation");
  const token = await getAccessToken();
  let result: AccountDeletionResult;
  try { result = await deleteAccount(value(formData, "pin"), token); }
  catch (error) { return failure(error); }
  if (result.deletion_status !== "completed") redirect("/account/delete?status=blocked");
  const cookieStore = await cookies();
  for (const name of ["opfin_access_token", "opfin_role", "opfin_name", "opfin_demo_consent"]) cookieStore.delete(name);
  redirect("/login?status=account-deleted");
}
export async function deleteOptionalDataAction(formData: FormData) {
  if (value(formData, "confirmation") !== "DELETE_DATA") redirect("/account/delete?error=validation");
  const token = await getAccessToken();
  const categories = formData.getAll("data_categories").filter((item): item is string => typeof item === "string");
  try { await deleteOptionalAccountData(value(formData, "pin"), categories, token); }
  catch (error) { return failure(error); }
  redirect("/account/delete?status=data-deleted");
}
