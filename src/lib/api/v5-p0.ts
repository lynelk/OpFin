import type { ApiEnvelope } from "../types";
import { classifyStatus, OpfinApiError } from "./errors";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;
const USE_MOCKS = process.env.NEXT_PUBLIC_USE_MOCK_API === "true";

type RequestOptions = RequestInit & { token?: string; bodyJson?: unknown };

export type SecurityControls = {
  transactions_frozen: boolean;
  login_alerts: boolean;
  payment_alerts: boolean;
  changed_at?: string | null;
};

export type SecurityEvent = {
  id: number;
  event_type: string;
  severity: string;
  source: string;
  ip_address?: string | null;
  occurred_at: string;
};

export type SecurityCentre = { controls: SecurityControls; events: SecurityEvent[] };

export type CreditBuilderPlan = {
  id?: number;
  goal?: string | null;
  baseline_score?: number | null;
  target_score?: number | null;
  status?: string;
  actions?: string | string[] | null;
  review_due_at?: string | null;
};

export type CreditBuilder = {
  plan: CreditBuilderPlan | null;
  factors: {
    outstanding_debt_minor: number;
    overdue_instalments: number;
    on_time_signal: string;
  };
  explanation: string;
};

export type HardshipCase = {
  id: number;
  reason: string;
  status: string;
  monthly_income_minor: number;
  essential_expenses_minor: number;
  debt_commitments_minor: number;
  requested_relief: string[] | Record<string, unknown>;
  approved_relief?: string[] | Record<string, unknown> | null;
  created_at: string;
};

export type FinancialPassport = {
  content: {
    user_id: number;
    generated_at: string;
    financial_position: {
      recorded_accounts: number;
      recorded_balance_minor: number;
      outstanding_debt_minor: number;
    };
    consents: unknown[];
    kyc: Record<string, unknown> | null;
  };
  provenance: Record<string, string>;
  confidence: string;
  content_hash: string;
};

export type ReconciliationSummary = {
  total: number;
  matched: number;
  open: number;
  mismatch: number;
  items: Array<Record<string, unknown>>;
};

type GovernanceRecord = Record<string, unknown> & { id?: number; status?: string };

async function request<T>(path: string, init: RequestOptions = {}): Promise<ApiEnvelope<T>> {
  if (USE_MOCKS) return mockRequest<T>(path, init);
  if (!API_BASE_URL) throw new OpfinApiError("server", "OpFin API base URL is not configured.");

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
    throw new OpfinApiError("network", error instanceof Error ? error.message : "OpFin API is unreachable");
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

function envelope<T>(data: T): ApiEnvelope<T> {
  return { success: true, message: "Sandbox v5 P0 data loaded", data };
}

function mockRequest<T>(path: string, init: RequestOptions): Promise<ApiEnvelope<T>> {
  const now = new Date().toISOString();
  if (path === "/security-centre") {
    return Promise.resolve(envelope({
      controls: { transactions_frozen: false, login_alerts: true, payment_alerts: true, changed_at: now },
      events: [{ id: 1, event_type: "login_success", severity: "info", source: "authentication", occurred_at: now }]
    } as T));
  }
  if (path === "/credit-builder") {
    return Promise.resolve(envelope({
      plan: init.method === "PUT" ? { ...(init.bodyJson as object), id: 1, status: "active" } : null,
      factors: { outstanding_debt_minor: 0, overdue_instalments: 0, on_time_signal: "positive" },
      explanation: "Credit Builder uses confirmed OpFin repayment data and user-approved goals. It does not fabricate a bureau score."
    } as T));
  }
  if (path === "/hardship") {
    return Promise.resolve(envelope((init.method === "POST" ? [{ id: 1, ...(init.bodyJson as object), status: "submitted", created_at: now }] : []) as T));
  }
  if (path === "/financial-passport") {
    return Promise.resolve(envelope({
      content: {
        user_id: 1,
        generated_at: now,
        financial_position: { recorded_accounts: 1, recorded_balance_minor: 650000, outstanding_debt_minor: 0 },
        consents: [],
        kyc: { status: "verified" }
      },
      provenance: { balances: "user_recorded_or_imported", debt: "opfin_repayment_schedule", identity: "opfin_kyc", consent: "opfin_consent_registry" },
      confidence: "mixed",
      content_hash: "sandbox-passport"
    } as T));
  }
  if (path === "/reconciliation/summary") {
    return Promise.resolve(envelope({ total: 0, matched: 0, open: 0, mismatch: 0, items: [] } as T));
  }
  return Promise.resolve(envelope({ id: Date.now(), status: "draft", ...(init.bodyJson as object) } as T));
}

export const v5P0Api = {
  security: (token?: string) => request<SecurityCentre>("/security-centre", { token }),
  updateSecurity: (payload: Partial<SecurityControls>, token?: string) => request<SecurityCentre>("/security-centre", { method: "PATCH", bodyJson: payload, token }),
  creditBuilder: (token?: string) => request<CreditBuilder>("/credit-builder", { token }),
  saveCreditBuilder: (payload: CreditBuilderPlan, token?: string) => request<CreditBuilder>("/credit-builder", { method: "PUT", bodyJson: payload, token }),
  hardship: (token?: string) => request<HardshipCase[]>("/hardship", { token }),
  openHardship: (payload: Record<string, unknown>, token?: string) => request<HardshipCase>("/hardship", { method: "POST", bodyJson: payload, token }),
  passport: (token?: string) => request<FinancialPassport>("/financial-passport", { token }),
  reconciliation: (token?: string) => request<ReconciliationSummary>("/reconciliation/summary", { token }),
  approveHardship: (id: number, approvedRelief: unknown, token?: string) => request<GovernanceRecord>(`/admin/hardship/${id}/approve`, { method: "POST", bodyJson: { approved_relief: approvedRelief }, token }),
  createProduct: (payload: Record<string, unknown>, token?: string) => request<GovernanceRecord>("/admin/product-factory/products", { method: "POST", bodyJson: payload, token }),
  transitionProduct: (id: number, status: string, token?: string) => request<GovernanceRecord>(`/admin/product-factory/products/${id}/transition`, { method: "POST", bodyJson: { status }, token }),
  createRule: (payload: Record<string, unknown>, token?: string) => request<GovernanceRecord>("/admin/rules", { method: "POST", bodyJson: payload, token }),
  approveRule: (id: number, token?: string) => request<GovernanceRecord>(`/admin/rules/${id}/approve`, { method: "POST", token }),
  evaluateRules: (context: Record<string, unknown>, token?: string) => request<GovernanceRecord[]>("/admin/rules/evaluate", { method: "POST", bodyJson: { context }, token }),
  createWorkflow: (payload: Record<string, unknown>, token?: string) => request<GovernanceRecord>("/admin/workflows", { method: "POST", bodyJson: payload, token }),
  approveWorkflow: (id: number, token?: string) => request<GovernanceRecord>(`/admin/workflows/${id}/approve`, { method: "POST", token })
};
