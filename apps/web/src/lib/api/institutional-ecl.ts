import { classifyStatus, OpfinApiError } from "./errors";

export type EclPolicy = { id: number; institution_id: number; version: string; currency: string; status: string; effective_from: string; created_by: number };
export type EclOwnership = { id: number; loan_id: number; owner_type: string; opfin_share_bps: number; status: string };
export type EclSnapshot = { id: number; external_loan_reference: string; as_of_date: string; status: string; principal_outstanding_minor: number };
export type EclRun = { id: number; institution_id: number; as_of_date: string; revision: number; status: string; currency: string; total_exposure_minor: number; total_ecl_minor: number; platform_ecl_minor: number; ecl_movement_minor: number; coverage_reference: string };
export type EclBatch = { id: number; institution_id: number; as_of_date: string; currency: string; source_evidence: string; loan_count: number; principal_total_minor: number; status: string };
export type EclOfferOwner = { id: number; credit_offer_id: number; institution_id: number; owner_type: string; opfin_share_bps: number; status: string };
export type EclCorrection = { id: number; external_snapshot_id: number; external_loan_reference: string; revision: number; status: string; principal_outstanding_minor: number; reason: string };
export type EclFunding = { id: number; mobile_money_transaction_id: number; currency: string; amount_minor: number; provider: string; evidence_reference: string; status: string; ledger_transaction_id?: number };
export type EclRegister = { funding_receipts: EclFunding[]; source_corrections: EclCorrection[]; offer_ownerships: EclOfferOwner[]; policies: EclPolicy[]; ownerships: EclOwnership[]; import_batches: EclBatch[]; external_snapshots: EclSnapshot[]; runs: EclRun[] };

export async function eclRequest<T>(path: string, token?: string, body?: Record<string, unknown>): Promise<T> {
  if (!token) throw new OpfinApiError("unauthorized", "Sign in to use institutional provisioning.");
  const base = process.env.NEXT_PUBLIC_OPFIN_API_URL;
  if (!base) throw new OpfinApiError("server", "OpFin API is not configured.");
  const response = await fetch(`${base}/admin/institutional-ecl${path}`, {
    method: body ? "POST" : "GET",
    headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` },
    ...(body ? { body: JSON.stringify(body) } : {}),
    cache: "no-store",
  });
  const result = await response.json();
  if (!response.ok) throw new OpfinApiError(classifyStatus(response.status), result.message ?? "Provisioning request failed.", response.status, result.errors ?? {});
  return result.data as T;
}
