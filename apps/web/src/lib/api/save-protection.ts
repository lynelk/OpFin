import type { ApiEnvelope } from "../types";
import type {
  ProtectionClaimPayload,
  ProtectionEnrollmentPayload,
  ProtectionPoliciesPayload,
  ProtectionPolicy,
  ProtectionPolicyPayload,
  ProtectionPremiumPayload,
  ProtectionProduct,
  ProtectionProductsPayload,
  SavingsGoal,
  SavingsGoalPayload,
  SavingsGoalsPayload,
  SavingsMovement,
  SavingsMovementPayload,
  SavingsProduct,
  SavingsProductsPayload
} from "../save-protection/types";
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

const mockSavingsProduct: SavingsProduct = {
  id: 101,
  code: "SAVE-EMERGENCY",
  name: "Emergency Resilience Savings",
  partner_name: "Sandbox Regulated Savings Partner",
  partner_product_reference: "SANDBOX-SAVE-001",
  country_code: "UG",
  currency: "UGX",
  product_type: "emergency",
  status: "active",
  custody_model: "partner_held",
  minimum_contribution_minor: 1000,
  maximum_contribution_minor: 5000000,
  minimum_withdrawal_minor: 1000,
  notice_days: 0,
  lock_days: 0,
  terms_version: "sandbox-v1",
  terms_url: "https://example.test/savings/terms",
  disclosures: [
    "Savings are held by the disclosed partner, not by an OpFin stored-value wallet.",
    "Collections become part of the savings position only after partner confirmation."
  ]
};

const mockSavingsGoal: SavingsGoal = {
  id: 201,
  goal_reference: "SANDBOX-SAV-GOAL-001",
  name: "Emergency fund",
  status: "active",
  target_amount_minor: 500000,
  target_date: null,
  confirmed_balance_minor: 125000,
  reserved_withdrawal_minor: 0,
  available_balance_minor: 125000,
  scheduled_amount_minor: 25000,
  contribution_frequency: "monthly",
  autopilot_enabled: false,
  product: mockSavingsProduct
};

const mockProtectionProduct: ProtectionProduct = {
  id: 301,
  code: "PROTECT-FAMILY",
  name: "Family Emergency Cover",
  insurer_name: "Sandbox Regulated Insurer",
  underwriter_name: "Sandbox Underwriter",
  country_code: "UG",
  currency: "UGX",
  product_type: "health",
  premium_amount_minor: 10000,
  premium_frequency: "monthly",
  coverage_limit_minor: 500000,
  benefits: ["Emergency medical reimbursement up to the policy limit"],
  exclusions: ["Events outside the issued cover period"],
  disclosure_version: "sandbox-v1",
  disclosure_payload: { decision_authority: "insurer_or_underwriter" },
  terms_url: "https://example.test/protection/terms",
  disclosure_hash: "sandbox-protection-disclosure".padEnd(64, "0")
};

const mockPolicy: ProtectionPolicy = {
  id: 401,
  protection_product_id: mockProtectionProduct.id,
  policy_reference: "SANDBOX-POLICY-001",
  external_policy_number: "SANDBOX-INS-001",
  partner_reference: "SANDBOX-ISSUE-001",
  status: "active",
  premium_amount_minor: mockProtectionProduct.premium_amount_minor,
  premium_frequency: mockProtectionProduct.premium_frequency,
  coverage_limit_minor: mockProtectionProduct.coverage_limit_minor,
  cover_start_date: new Date().toISOString().slice(0, 10),
  cover_end_date: new Date(Date.now() + 365 * 86400000).toISOString().slice(0, 10),
  next_premium_due_date: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
  disclosure_hash: mockProtectionProduct.disclosure_hash,
  enrolled_at: new Date().toISOString(),
  issued_at: new Date().toISOString(),
  product: mockProtectionProduct,
  premium_payments: [],
  claims: []
};

function mockMovement(goalId: number, type: "contribution" | "withdrawal", amountMinor: number): SavingsMovement {
  return {
    id: Date.now(),
    savings_goal_id: goalId,
    movement_reference: `SANDBOX-${type.toUpperCase()}-${Date.now()}`,
    movement_type: type,
    status: type === "contribution" ? "collection_pending" : "withdrawal_requested",
    amount_minor: amountMinor,
    currency: "UGX",
    requested_at: new Date().toISOString()
  };
}

function mockRequest<T>(path: string, init: RequestOptions): Promise<ApiEnvelope<T>> {
  if (path.startsWith("/savings/products")) {
    return Promise.resolve(envelope({
      products: [mockSavingsProduct],
      custody_notice: "Sandbox: savings positions are partner-held and are not an OpFin stored-value wallet."
    } as T, "Sandbox savings products loaded."));
  }
  if (path === "/savings/goals" && init.method === "POST") {
    const body = (init.bodyJson ?? {}) as Record<string, unknown>;
    return Promise.resolve(envelope({
      goal: {
        ...mockSavingsGoal,
        id: Date.now(),
        name: String(body.name ?? "Savings goal"),
        target_amount_minor: typeof body.target_amount_minor === "number" ? body.target_amount_minor : null,
        scheduled_amount_minor: typeof body.scheduled_amount_minor === "number" ? body.scheduled_amount_minor : null,
        contribution_frequency: typeof body.contribution_frequency === "string" ? body.contribution_frequency : null,
        confirmed_balance_minor: 0,
        available_balance_minor: 0
      }
    } as T, "Sandbox savings goal created."));
  }
  if (path === "/savings/goals") {
    return Promise.resolve(envelope({ goals: [mockSavingsGoal] } as T, "Sandbox savings goals loaded."));
  }
  const savingsGoalMatch = path.match(/^\/savings\/goals\/(\d+)$/);
  if (savingsGoalMatch) {
    return Promise.resolve(envelope({ goal: mockSavingsGoal, movements: [] } as T, "Sandbox savings goal loaded."));
  }
  const savingsScheduleMatch = path.match(/^\/savings\/goals\/(\d+)\/schedule$/);
  if (savingsScheduleMatch) {
    const body = (init.bodyJson ?? {}) as Record<string, unknown>;
    return Promise.resolve(envelope({
      goal: {
        ...mockSavingsGoal,
        scheduled_amount_minor: typeof body.scheduled_amount_minor === "number" ? body.scheduled_amount_minor : null,
        contribution_frequency: typeof body.contribution_frequency === "string" ? body.contribution_frequency : null,
        autopilot_enabled: false
      },
      collection_mode: "reminder_manual_until_mandate_certified"
    } as T, "Sandbox savings schedule updated."));
  }
  const savingsPauseResumeMatch = path.match(/^\/savings\/goals\/(\d+)\/(pause|resume)$/);
  if (savingsPauseResumeMatch) {
    return Promise.resolve(envelope({
      goal: { ...mockSavingsGoal, status: savingsPauseResumeMatch[2] === "pause" ? "paused" : "active" }
    } as T, "Sandbox savings goal state updated."));
  }
  const contributionMatch = path.match(/^\/savings\/goals\/(\d+)\/contributions$/);
  if (contributionMatch) {
    const body = (init.bodyJson ?? {}) as Record<string, unknown>;
    return Promise.resolve(envelope({
      movement: mockMovement(Number(contributionMatch[1]), "contribution", Number(body.amount_minor ?? 0)),
      position_state: "not_yet_partner_confirmed"
    } as T, "Sandbox savings contribution initiated."));
  }
  const withdrawalMatch = path.match(/^\/savings\/goals\/(\d+)\/withdrawals$/);
  if (withdrawalMatch) {
    const body = (init.bodyJson ?? {}) as Record<string, unknown>;
    return Promise.resolve(envelope({
      movement: mockMovement(Number(withdrawalMatch[1]), "withdrawal", Number(body.amount_minor ?? 0)),
      next_state: "partner_release_required"
    } as T, "Sandbox savings withdrawal requested."));
  }
  if (path.startsWith("/protection/products?") || path === "/protection/products") {
    return Promise.resolve(envelope({
      products: [mockProtectionProduct],
      risk_notice: "Sandbox: the disclosed insurer or underwriter owns underwriting and claim decisions."
    } as T, "Sandbox protection products loaded."));
  }
  if (path === "/protection/policies") {
    return Promise.resolve(envelope({ policies: [mockPolicy] } as T, "Sandbox protection policies loaded."));
  }
  const policyMatch = path.match(/^\/protection\/policies\/(\d+)$/);
  if (policyMatch) {
    return Promise.resolve(envelope({ policy: mockPolicy } as T, "Sandbox protection policy loaded."));
  }
  const enrollMatch = path.match(/^\/protection\/products\/(\d+)\/enroll$/);
  if (enrollMatch) {
    return Promise.resolve(envelope({
      policy: { ...mockPolicy, id: Date.now(), external_policy_number: null, status: "premium_due", issued_at: null },
      next_state: "premium_due"
    } as T, "Sandbox protection enrollment recorded."));
  }
  const premiumMatch = path.match(/^\/protection\/policies\/(\d+)\/premiums$/);
  if (premiumMatch) {
    return Promise.resolve(envelope({
      premium_payment: {
        id: Date.now(),
        protection_policy_id: Number(premiumMatch[1]),
        payment_reference: `SANDBOX-PREMIUM-${Date.now()}`,
        status: "collection_pending",
        amount_minor: mockPolicy.premium_amount_minor,
        currency: "UGX",
        requested_at: new Date().toISOString()
      },
      policy: { ...mockPolicy, status: "premium_pending" },
      next_state: "awaiting_partner_confirmation"
    } as T, "Sandbox premium collection initiated."));
  }
  const claimMatch = path.match(/^\/protection\/policies\/(\d+)\/claims$/);
  if (claimMatch) {
    const body = (init.bodyJson ?? {}) as Record<string, unknown>;
    return Promise.resolve(envelope({
      claim: {
        id: Date.now(),
        protection_policy_id: Number(claimMatch[1]),
        claim_reference: `SANDBOX-CLAIM-${Date.now()}`,
        status: "submitted",
        incident_date: String(body.incident_date ?? new Date().toISOString().slice(0, 10)),
        category: String(body.category ?? "other"),
        description: String(body.description ?? "Sandbox claim"),
        claimed_amount_minor: typeof body.claimed_amount_minor === "number" ? body.claimed_amount_minor : null,
        evidence: Array.isArray(body.evidence) ? body.evidence as string[] : [],
        submitted_at: new Date().toISOString()
      },
      decision_authority: "insurer_or_underwriter"
    } as T, "Sandbox protection claim submitted."));
  }
  const disputeMatch = path.match(/^\/protection\/claims\/(\d+)\/dispute$/);
  if (disputeMatch) {
    return Promise.resolve(envelope({
      claim: {
        id: Number(disputeMatch[1]),
        protection_policy_id: mockPolicy.id,
        claim_reference: `SANDBOX-CLAIM-${disputeMatch[1]}`,
        status: "disputed",
        incident_date: new Date().toISOString().slice(0, 10),
        category: "other",
        description: "Sandbox disputed claim",
        submitted_at: new Date().toISOString()
      }
    } as T, "Sandbox protection claim dispute opened."));
  }

  return Promise.resolve(envelope({} as T, "No Save & Protection mock contract is defined for this endpoint."));
}

export const saveProtectionApi = {
  savingsProducts: (country = "UG", token?: string) =>
    request<SavingsProductsPayload>(`/savings/products?country=${encodeURIComponent(country)}`, { token }),
  savingsGoals: (token?: string) => request<SavingsGoalsPayload>("/savings/goals", { token }),
  savingsGoal: (goalId: number, token?: string) =>
    request<SavingsGoalPayload>(`/savings/goals/${goalId}`, { token }),
  createSavingsGoal: (
    payload: {
      savings_product_id: number;
      name: string;
      target_amount_minor?: number;
      target_date?: string;
      scheduled_amount_minor?: number;
      contribution_frequency?: string;
    },
    token?: string
  ) => request<SavingsGoalPayload>("/savings/goals", { method: "POST", bodyJson: payload, token }),
  updateSavingsSchedule: (
    goalId: number,
    payload: { scheduled_amount_minor?: number; contribution_frequency?: string; autopilot_enabled?: boolean },
    token?: string
  ) => request<SavingsGoalPayload & { collection_mode: string }>(`/savings/goals/${goalId}/schedule`, {
    method: "PATCH",
    bodyJson: payload,
    token
  }),
  pauseSavingsGoal: (goalId: number, token?: string) =>
    request<SavingsGoalPayload>(`/savings/goals/${goalId}/pause`, { method: "POST", token }),
  resumeSavingsGoal: (goalId: number, token?: string) =>
    request<SavingsGoalPayload>(`/savings/goals/${goalId}/resume`, { method: "POST", token }),
  contributeSavings: (goalId: number, payload: { amount_minor: number; idempotency_key: string }, token?: string) =>
    request<SavingsMovementPayload>(`/savings/goals/${goalId}/contributions`, { method: "POST", bodyJson: payload, token }),
  withdrawSavings: (goalId: number, payload: { amount_minor: number; idempotency_key: string }, token?: string) =>
    request<SavingsMovementPayload>(`/savings/goals/${goalId}/withdrawals`, { method: "POST", bodyJson: payload, token }),

  protectionProducts: (country = "UG", token?: string) =>
    request<ProtectionProductsPayload>(`/protection/products?country=${encodeURIComponent(country)}`, { token }),
  protectionPolicies: (token?: string) => request<ProtectionPoliciesPayload>("/protection/policies", { token }),
  protectionPolicy: (policyId: number, token?: string) =>
    request<ProtectionPolicyPayload>(`/protection/policies/${policyId}`, { token }),
  enrollProtection: (productId: number, payload: { accept_disclosures: boolean; disclosure_hash: string }, token?: string) =>
    request<ProtectionEnrollmentPayload>(`/protection/products/${productId}/enroll`, { method: "POST", bodyJson: payload, token }),
  payProtectionPremium: (policyId: number, payload: { idempotency_key: string }, token?: string) =>
    request<ProtectionPremiumPayload>(`/protection/policies/${policyId}/premiums`, { method: "POST", bodyJson: payload, token }),
  submitProtectionClaim: (
    policyId: number,
    payload: {
      incident_date: string;
      category: string;
      description: string;
      claimed_amount_minor?: number;
      evidence?: string[];
    },
    token?: string
  ) => request<ProtectionClaimPayload>(`/protection/policies/${policyId}/claims`, { method: "POST", bodyJson: payload, token }),
  disputeProtectionClaim: (claimId: number, payload: { reason: string }, token?: string) =>
    request<ProtectionClaimPayload>(`/protection/claims/${claimId}/dispute`, { method: "POST", bodyJson: payload, token })
};
