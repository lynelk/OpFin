"use server";

import { redirect } from "next/navigation";
import { assuranceRequest } from "@/lib/api/institutional-assurance";
import { getAccessToken } from "@/lib/auth/session";

const str = (form: FormData, name: string) => String(form.get(name) ?? "").trim();
const n = (form: FormData, name: string) => {
  const value = Number(str(form, name));
  if (!Number.isSafeInteger(value) || value < 0) throw new Error(`Invalid numeric value: ${name}`);
  return value;
};

export async function institutionalAssuranceAction(form: FormData) {
  const institution = n(form, "institution_id");
  const kind = str(form, "kind");
  let route = "";
  let payload: Record<string, unknown> = {};
  try {
    if (institution < 1) throw new Error("Select a lending institution.");
    switch (kind) {
      case "stage": {
        route = "/reconciliations";
        const source = JSON.parse(str(form, "provider_statements")) as unknown;
        if (!Array.isArray(source) || source.length < 1) throw new Error("Original provider statements must be a JSON array.");
        payload = {
          institution_id: institution, ecl_run_id: n(form, "ecl_run_id"),
          as_of_date: str(form, "as_of_date"), currency: str(form, "currency").toUpperCase(),
          provider_statements: source,
          external_payable_minor: n(form, "external_payable_minor"),
          external_principal_minor: n(form, "external_principal_minor"),
          external_loan_count: n(form, "external_loan_count"),
          lender_ledger_reference: str(form, "lender_ledger_reference"),
          lender_ledger_sha256: str(form, "lender_ledger_sha256"),
        };
        break;
      }
      case "approve":
        route = `/reconciliations/${n(form, "record_id")}/approve`;
        break;
      case "sign":
        route = `/reconciliations/${n(form, "record_id")}/sign`;
        payload = {
          capacity: str(form, "capacity"), report_reference: str(form, "report_reference"),
          report_sha256: str(form, "report_sha256"), signed_statement: str(form, "signed_statement"),
        };
        break;
      case "shared":
        route = "/shared-classifications";
        payload = {
          credit_offer_id: n(form, "credit_offer_id"), reconciliation_id: n(form, "reconciliation_id"),
          contract_form: str(form, "contract_form"),
          opfin_share_bps: n(form, "opfin_share_bps"),
          contract_reference: str(form, "contract_reference"),
          signed_contract_sha256: str(form, "signed_contract_sha256"),
          accounting_policy_reference: str(form, "accounting_policy_reference"),
          technical_memo_sha256: str(form, "technical_memo_sha256"),
        };
        break;
      case "shared-review":
        route = `/shared-classifications/${n(form, "record_id")}/review`;
        payload = {
          capacity: str(form, "capacity"), review_reference: str(form, "review_reference"),
          review_document_sha256: str(form, "review_document_sha256"),
          technical_conclusion: str(form, "technical_conclusion"),
        };
        break;
      default:
        throw new Error("Unknown institutional assurance operation.");
    }
    await assuranceRequest(route, await getAccessToken(), payload);
  } catch (error) {
    const message = error instanceof Error ? error.message : "Institutional assurance action rejected.";
    redirect(`/admin/institutional-assurance?institution_id=${institution}&error=${encodeURIComponent(message)}`);
  }
  redirect(`/admin/institutional-assurance?institution_id=${institution}&saved=true`);
}
