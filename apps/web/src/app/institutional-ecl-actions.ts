"use server";

import { redirect } from "next/navigation";
import { eclRequest } from "@/lib/api/institutional-ecl";
import { getAccessToken } from "@/lib/auth/session";

const str = (f: FormData, key: string) => String(f.get(key) ?? "").trim();
const n = (f: FormData, key: string) => Number(str(f, key));
const numFields = ["weight_bps", "pd_12m_bps", "pd_lifetime_bps", "pd_impaired_bps", "lgd_bps"] as const;

export async function institutionalEclAction(form: FormData) {
  const kind = str(form, "kind");
  const institutionId = n(form, "institution_id");
  let path = "";
  let body: Record<string, unknown> = {};
  try {
    if (!Number.isSafeInteger(institutionId) || institutionId < 1) throw new Error("Select an institution.");
    switch (kind) {
      case "policy":
        path = "/policies";
        body = {
          institution_id: institutionId, version: str(form, "version"),
          effective_from: str(form, "effective_from"), currency: str(form, "currency").toUpperCase(),
          stage2_dpd: n(form, "stage2_dpd"), stage3_dpd: n(form, "stage3_dpd"),
          discount_bps: n(form, "discount_bps"), methodology_reference: str(form, "methodology_reference"),
          scenarios: [0, 1].map(i => Object.fromEntries(numFields.map(field => [field, n(form, `${field}_${i}`)]))),
        };
        break;
      case "policy-approve": path = `/policies/${n(form, "record_id")}/approve`; break;
      case "ownership":
        path = "/ownerships";
        body = { institution_id: institutionId, loan_id: n(form, "loan_id"),
          owner_type: str(form, "owner_type"), opfin_share_bps: n(form, "opfin_share_bps"),
          agreement_reference: str(form, "agreement_reference") };
        break;
      case "ownership-approve": path = `/ownerships/${n(form, "record_id")}/approve`; break;
      case "external":
        path = "/external-snapshots";
        body = { institution_id: institutionId, external_loan_reference: str(form, "external_loan_reference"),
          as_of_date: str(form, "as_of_date"), currency: str(form, "currency").toUpperCase(),
          principal_outstanding_minor: n(form, "principal_outstanding_minor"), days_past_due: n(form, "days_past_due"),
          sicr: str(form, "sicr") === "true", credit_impaired: str(form, "credit_impaired") === "true",
          contractual_maturity_date: str(form, "contractual_maturity_date"), source_evidence: str(form, "source_evidence") };
        break;
      case "external-approve": path = `/external-snapshots/${n(form, "record_id")}/approve`; break;
      case "batch-approve": path = `/import-batches/${n(form, "record_id")}/approve`; break;
      case "external-csv": {
        const file = form.get("file");
        if (!(file instanceof File) || file.size === 0 || file.size > 2097152)
          throw new Error("Select a CSV under 2 MiB.");
        const base = process.env.NEXT_PUBLIC_OPFIN_API_URL;
        const token = await getAccessToken();
        if (!base || !token) throw new Error("Authenticated OpFin API required.");
        const multipart = new FormData();
        for (const key of ["institution_id", "as_of_date", "currency", "source_evidence", "expected_loan_count", "expected_principal_minor"]) {
          multipart.set(key, str(form, key));
        }
        multipart.set("file", file);
        const response = await fetch(`${base}/admin/institutional-ecl/external-csv`, {
          method: "POST", headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
          body: multipart, cache: "no-store",
        });
        if (!response.ok) {
          const data = await response.json().catch(() => ({}));
          throw new Error(data.message ?? "CSV intake rejected. Check exact source and control totals.");
        }
        redirect(`/admin/institutional-ecl?institution_id=${institutionId}&saved=true`);
      }
      case "run":
        path = "/runs";
        body = { institution_id: institutionId, as_of_date: str(form, "as_of_date"),
          expected_external_count: n(form, "expected_external_count"),
          expected_external_principal_minor: n(form, "expected_external_principal_minor"),
          coverage_reference: str(form, "coverage_reference") };
        break;
      case "run-approve": path = `/runs/${n(form, "record_id")}/approve`; break;
      case "run-post": path = `/runs/${n(form, "record_id")}/post`; break;
      default: throw new Error("Unknown provisioning action.");
    }
    await eclRequest(path, await getAccessToken(), body);
  } catch (error) {
    const message = error instanceof Error ? error.message : "Unable to perform provisioning action.";
    redirect(`/admin/institutional-ecl?institution_id=${institutionId}&error=${encodeURIComponent(message)}`);
  }
  redirect(`/admin/institutional-ecl?institution_id=${institutionId}&saved=true`);
}
