import type { ApiEnvelope } from "../types";
import type { ProtectionClaim, ProtectionPolicy, ProtectionPremiumPayment, ProtectionProduct, SavingsMovement, SavingsProduct } from "../save-protection/types";
import type {
  AdminProtectionProduct,
  AdminSavingsProduct,
  ProtectionProductDraftInput,
  SaveProtectionWorkQueue,
  SavingsProductDraftInput
} from "../save-protection/operations-types";
import { classifyStatus, OpfinApiError } from "./errors";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;
const USE_MOCKS = process.env.NEXT_PUBLIC_USE_MOCK_API === "true";

type RequestOptions = RequestInit & {
  token?: string;
  bodyJson?: unknown;
};

async function request<T>(path: string, init: RequestOptions = {}): Promise<ApiEnvelope<T>> {
  if (USE_MOCKS) {
    return mockRequest<T>(path, init);
  }

  if (!API_BASE_URL) {
    throw new OpfinApiError(
      "server",
      "OpFin API base URL is not configured. Set NEXT_PUBLIC_OPFIN_API_URL or explicitly enable mock mode."
    );
  }

  let response: Response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      ...init,
      body: init.bodyJson === undefined ? init.body : JSON.stringify(init.bodyJson),
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(init.token ? { Authorization: `Bearer ${init.token}` } : {}),
        ...init.headers
      },
      cache: "no-store"
    });
  } catch (error) {
    throw new OpfinApiError(
      "network",
      error instanceof Error ? error.message : "OpFin API is unreachable"
    );
  }

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new OpfinApiError(
      classifyStatus(response.status),
      typeof payload.message === "string" ? payload.message : `OpFin API request failed: ${response.status}`,
      response.status,
      typeof payload.errors === "object" && payload.errors ? payload.errors : {}
    );
  }

  return payload as ApiEnvelope<T>;
}

function envelope<T>(data: T, message: string): ApiEnvelope<T> {
  return { success: true, message, data };
}

function mockRequest<T>(path: string, init: RequestOptions): Promise<ApiEnvelope<T>> {
  if (path === "/admin/save-protection/work-queue") {
    return Promise.resolve(envelope({
      counts: {
        savings_contributions: 0,
        savings_withdrawals: 0,
        protection_premiums: 0,
        protection_policies: 0,
        protection_claims: 0
      },
      savings_contributions: [],
      savings_withdrawals: [],
      protection_premiums: [],
      protection_policies: [],
      protection_claims: [],
      scope: "institution",
      institution_id: 1
    } as T, "Sandbox Save & Protection work queue loaded."));
  }
  if (path === "/admin/savings-products" && init.method === "POST") {
    const body = (init.bodyJson ?? {}) as SavingsProductDraftInput;
    return Promise.resolve(envelope({
      product: {
        ...body,
        id: Date.now(),
        status: "draft",
        created_by: 1,
        approved_by: null,
        approved_at: null,
        approval_evidence: null,
        minimum_contribution_minor: body.minimum_contribution_minor ?? 0,
        minimum_withdrawal_minor: body.minimum_withdrawal_minor ?? 0,
        notice_days: body.notice_days ?? 0,
        lock_days: body.lock_days ?? 0
      }
    } as T, "Sandbox savings product draft created."));
  }
  if (path === "/admin/savings-products") {
    return Promise.resolve(envelope({ products: [] } as T, "Sandbox savings product catalogue loaded."));
  }
  if (/^\/admin\/savings-products\/\d+\/activate$/.test(path)) {
    return Promise.resolve(envelope({ product: { id: 1, status: "active" } } as T, "Sandbox savings product activated."));
  }
  if (/^\/admin\/savings-movements\/\d+\/confirm-contribution$/.test(path) || /^\/admin\/savings-movements\/\d+\/release-withdrawal$/.test(path) || /^\/admin\/savings-movements\/\d+\/retry-payout$/.test(path)) {
    return Promise.resolve(envelope({ movement: { id: 1, status: "confirmed" } } as T, "Sandbox savings operation completed."));
  }
  if (path === "/admin/protection-products" && init.method === "POST") {
    const body = (init.bodyJson ?? {}) as ProtectionProductDraftInput;
    return Promise.resolve(envelope({
      product: {
        ...body,
        id: Date.now(),
        status: "draft",
        created_by: 1,
        approved_by: null,
        approved_at: null,
        approval_evidence: null,
        disclosure_hash: "sandbox-admin-protection".padEnd(64, "0")
      },
      disclosure_hash: "sandbox-admin-protection".padEnd(64, "0")
    } as T, "Sandbox protection product draft created."));
  }
  if (path === "/admin/protection-products") {
    return Promise.resolve(envelope({ products: [] } as T, "Sandbox protection product catalogue loaded."));
  }
  if (/^\/admin\/protection-products\/\d+\/activate$/.test(path)) {
    return Promise.resolve(envelope({ product: { id: 1, status: "active" }, disclosure_hash: "a".repeat(64) } as T, "Sandbox protection product activated."));
  }
  if (/^\/admin\/protection-premiums\/\d+\/confirm$/.test(path)) {
    return Promise.resolve(envelope({ premium_payment: { id: 1, status: "confirmed" } } as T, "Sandbox premium settlement confirmed."));
  }
  if (/^\/admin\/protection-policies\/\d+\/issue$/.test(path)) {
    return Promise.resolve(envelope({ policy: { id: 1, status: "active" } } as T, "Sandbox policy issuance recorded."));
  }
  if (/^\/admin\/protection-claims\/\d+$/.test(path)) {
    return Promise.resolve(envelope({ claim: { id: 1, status: "partner_review" } } as T, "Sandbox claim state updated."));
  }

  return Promise.resolve(envelope({} as T, "No Save & Protection operations mock contract is defined for this endpoint."));
}

export const saveProtectionOperationsApi = {
  workQueue: (token?: string) => request<SaveProtectionWorkQueue>("/admin/save-protection/work-queue", { token }),
  savingsProducts: (token?: string) => request<{ products: AdminSavingsProduct[] }>("/admin/savings-products", { token }),
  createSavingsProduct: (payload: SavingsProductDraftInput, token?: string) =>
    request<{ product: AdminSavingsProduct }>("/admin/savings-products", { method: "POST", bodyJson: payload, token }),
  activateSavingsProduct: (
    productId: number,
    payload: { approval_reference: string; approval_evidence_hash: string; approval_note: string },
    token?: string
  ) => request<{ product: AdminSavingsProduct }>(`/admin/savings-products/${productId}/activate`, { method: "POST", bodyJson: payload, token }),
  confirmSavingsContribution: (
    movementId: number,
    payload: { partner_reference: string; partner_evidence_hash: string },
    token?: string
  ) => request<{ movement: SavingsMovement }>(`/admin/savings-movements/${movementId}/confirm-contribution`, { method: "POST", bodyJson: payload, token }),
  releaseSavingsWithdrawal: (
    movementId: number,
    payload: { partner_reference: string; partner_evidence_hash: string },
    token?: string
  ) => request<{ movement: SavingsMovement }>(`/admin/savings-movements/${movementId}/release-withdrawal`, { method: "POST", bodyJson: payload, token }),
  retrySavingsWithdrawalPayout: (movementId: number, token?: string) =>
    request<{ movement: SavingsMovement }>(`/admin/savings-movements/${movementId}/retry-payout`, { method: "POST", token }),

  protectionProducts: (token?: string) => request<{ products: AdminProtectionProduct[] }>("/admin/protection-products", { token }),
  createProtectionProduct: (payload: ProtectionProductDraftInput, token?: string) =>
    request<{ product: AdminProtectionProduct; disclosure_hash: string }>("/admin/protection-products", { method: "POST", bodyJson: payload, token }),
  activateProtectionProduct: (
    productId: number,
    payload: { approval_reference: string; approval_evidence_hash: string; approval_note: string },
    token?: string
  ) => request<{ product: AdminProtectionProduct; disclosure_hash: string }>(`/admin/protection-products/${productId}/activate`, { method: "POST", bodyJson: payload, token }),
  confirmProtectionPremium: (
    paymentId: number,
    payload: { partner_reference: string; partner_evidence_hash: string },
    token?: string
  ) => request<{ premium_payment: ProtectionPremiumPayment }>(`/admin/protection-premiums/${paymentId}/confirm`, { method: "POST", bodyJson: payload, token }),
  issueProtectionPolicy: (
    policyId: number,
    payload: { external_policy_number: string; partner_reference: string; cover_start_date: string; cover_end_date: string },
    token?: string
  ) => request<{ policy: ProtectionPolicy }>(`/admin/protection-policies/${policyId}/issue`, { method: "POST", bodyJson: payload, token }),
  updateProtectionClaim: (
    claimId: number,
    payload: { status: string; partner_claim_reference?: string; decision_reason?: string; approved_amount_minor?: number },
    token?: string
  ) => request<{ claim: ProtectionClaim }>(`/admin/protection-claims/${claimId}`, { method: "PATCH", bodyJson: payload, token })
};

export type { AdminProtectionProduct, AdminSavingsProduct, ProtectionProduct, SavingsProduct };
