"use server";

import { redirect } from "next/navigation";
import { getAccessToken } from "@/lib/auth/session";
import { lendingPlatformRequest } from "@/lib/api/lending-platform";

const text = (form: FormData, key: string) => String(form.get(key) ?? "").trim();
const number = (form: FormData, key: string) => text(form, key) ? Number(text(form, key)) : null;

export async function saveLendingConfiguration(form: FormData) {
  const kind = text(form, "kind");
  let path: string;
  let method = "POST";
  let body: Record<string, unknown>;
  try {
    switch (kind) {
      case "institution":
        path = "/institutions";
        body = { id: number(form, "id"), name: text(form, "name"), address: text(form, "address") || null, phone: text(form, "phone") || null, email: text(form, "email") || null, status: text(form, "status"), lender_relationship: text(form, "lender_relationship"), country: text(form, "country").toUpperCase(), regulator_code: text(form, "regulator_code") || null, licence_class: text(form, "licence_class") || null, authority_basis: text(form, "authority_basis"), authority_reference: text(form, "authority_reference") || null, authority_valid_until: text(form, "authority_valid_until") || null, rate_change_approval_required: form.get("rate_change_approval_required") === "on" };
        break;
      case "product":
        path = "/products";
        body = { id: number(form, "id"), institution_id: number(form, "institution_id"), name: text(form, "name"), status: text(form, "status"), country: text(form, "country").toUpperCase(), currency: text(form, "currency").toUpperCase(), product_category: text(form, "product_category"), min_amount_minor: number(form, "min_amount_minor"), max_amount_minor: number(form, "max_amount_minor"), funding_pool_id: number(form, "funding_pool_id"), borrower_purposes: text(form, "borrower_purposes").split(",").map((item) => item.trim()).filter(Boolean) };
        break;
      case "term":
        path = `/products/${number(form, "loan_product_id")}/terms`;
        body = { duration: number(form, "duration"), interest_rate: number(form, "interest_rate"), interest_type: text(form, "interest_type"), interest_cycle: text(form, "interest_cycle"), repayment_frequency: text(form, "repayment_frequency"), status: text(form, "status") };
        break;
      case "strategy":
        path = "/strategy";
        body = { mode: text(form, "mode"), reason: text(form, "reason"), max_affiliated_loan_minor: number(form, "max_affiliated_loan_minor"), effective_from: new Date().toISOString(), effective_to: text(form, "effective_to") || null };
        break;
      case "distribution":
        path = "/distribution";
        body = { channel: text(form, "channel"), country: text(form, "country").toUpperCase(), product_category: text(form, "product_category"), institution_id: number(form, "institution_id"), loan_product_id: number(form, "loan_product_id"), partner_product_id: number(form, "partner_product_id"), availability: text(form, "availability"), min_duration_days: number(form, "min_duration_days"), max_apr_percent: number(form, "max_apr_percent"), reason: text(form, "reason"), source_reference: text(form, "source_reference"), source_url: text(form, "source_url") || null, effective_from: new Date().toISOString(), effective_to: text(form, "effective_to") || null };
        break;
      case "access":
        path = `/access/${number(form, "user_id")}`;
        method = "PATCH";
        body = { enabled: text(form, "enabled") === "true" };
        break;
      default: throw new Error("Choose a valid lending configuration action.");
    }
    await lendingPlatformRequest(path, await getAccessToken(), body, method);
  } catch (error) {
    const message = error instanceof Error ? error.message : "Unable to save configuration.";
    redirect(`/admin/lending-platform?error=${encodeURIComponent(message)}`);
  }
  redirect("/admin/lending-platform?saved=true");
}
