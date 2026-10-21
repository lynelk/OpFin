"use server";

import { getAccessToken } from "@/lib/auth/session";

export type MerchantShadowState = {
  kind: "idle" | "success" | "error";
  message: string;
  data: null | {
    status: string;
    decision: string;
    execution_authorised: boolean;
    lender_authority_status: string;
    customer_consent_status: string;
    borrower_space_link_status: string;
    quality_reason_codes: string[];
    observation_window_end: string;
    indicators: {
      currency: string;
      netSalesMinor: number;
      transactionCount: number;
      dataCoverageDays: number;
      dataQualityBps: number;
      reconciliationBps: number;
      expectedDownsideGrossCashGenerationMinor: number;
    };
    limitations: string[];
  };
};

const idFields = ["borrowerUserId", "loanProductId", "financialSpaceId"] as const;
const refs = ["tenantId", "consentGrantId", "passportShareId", "featureSnapshotId"] as const;

export async function inspectMerchantShadow(
  _prior: MerchantShadowState,
  form: FormData,
): Promise<MerchantShadowState> {
  const error = (message: string): MerchantShadowState => ({ kind: "error", message, data: null });
  const token = await getAccessToken();
  if (!token) return error("An authenticated administrator session is required.");

  const payload: Record<string, string | number> = {};
  for (const field of idFields) {
    const raw = String(form.get(field) ?? "").trim();
    if (!/^[1-9]\d{0,9}$/.test(raw)) return error("A valid borrower, product and Financial Space ID is required.");
    payload[field] = Number(raw);
  }
  for (const field of refs) {
    const value = String(form.get(field) ?? "").trim();
    if (!/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/.test(value)) {
      return error("Evidence references must be valid opaque IDs.");
    }
    payload[field] = value;
  }

  const base = process.env.NEXT_PUBLIC_OPFIN_API_URL?.replace(/\/$/, "");
  if (!base) return error("The OpFin API endpoint has not been configured.");
  try {
    const response = await fetch(`${base}/admin/business-passport/shadow-review`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
      cache: "no-store",
    });
    const envelope = await response.json() as {
      message?: string;
      data?: MerchantShadowState["data"];
    };
    if (!response.ok) return error(envelope.message ?? "Merchant evidence could not be independently verified.");
    if (!envelope.data || envelope.data.execution_authorised !== false) {
      return error("The evidence service did not provide a safe non-consequential result.");
    }
    return { kind: "success", message: "Merchant evidence inspected for manual shadow review only.", data: envelope.data };
  } catch {
    return error("The merchant evidence service is unavailable. No credit decision has been made.");
  }
}
