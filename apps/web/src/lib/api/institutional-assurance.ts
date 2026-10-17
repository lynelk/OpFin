import { classifyStatus, OpfinApiError } from "./errors";

export type AssuranceReconciliation = {
  id: number; institution_id: number; ecl_run_id: number; as_of_date: string;
  currency: string; content_hash: string; status: string; control_results: string | Record<string, unknown>;
};
export type AssuranceReview = { id: number; reconciliation_id: number; capacity: string; reviewed_by: number; report_reference: string };
export type SharedClassification = {
  id: number; credit_offer_id: number; contract_form: string;
  opfin_share_bps: number; status: string; contract_reference: string;
};
export type AssuranceRegister = {
  reconciliations: AssuranceReconciliation[]; reviews: AssuranceReview[];
  classifications: SharedClassification[]; classification_reviews: AssuranceReview[];
  required_review_capacities: string[]; shared_origination_enabled: false;
  external_evidence_requirement: "deferred_for_nonproduction_modelling" | "required_for_live_release";
};

export async function assuranceRequest<T>(
  path: string, token?: string, body?: Record<string, unknown>,
): Promise<T> {
  if (!token) throw new OpfinApiError("unauthorized", "Sign in to review institutional financial evidence.");
  const base = process.env.NEXT_PUBLIC_OPFIN_API_URL;
  if (!base) throw new OpfinApiError("server", "OpFin API is not configured.");
  const response = await fetch(`${base}/admin/institutional-assurance${path}`, {
    method: body ? "POST" : "GET",
    headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` },
    ...(body ? { body: JSON.stringify(body) } : {}),
    cache: "no-store",
  });
  const result = await response.json();
  if (!response.ok) throw new OpfinApiError(classifyStatus(response.status),
    result.message ?? "Institutional assurance evidence was not accepted.",
    response.status, result.errors ?? {});
  return result.data as T;
}
