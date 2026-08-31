import { classifyStatus, OpfinApiError } from "./errors";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;

export type ActivationState = {
  profile: null | {
    primary_financial_goal: string | null;
    preferred_language: string;
    notifications_enabled: boolean;
    onboarding_completed_at: string | null;
  };
  steps: Array<{ code: string; essential: boolean; complete: boolean }>;
  essential_complete: number;
  essential_total: number;
  activation_percent: number;
  activation_complete: boolean;
};

export type MoneyAutopilotRule = {
  id: number;
  name: string;
  rule_type: string;
  status: string;
  trigger_config: Record<string, unknown>;
  action_config: Record<string, unknown>;
  max_amount_minor: number | null;
  currency: string;
  consented_at: string | null;
  last_evaluated_at: string | null;
  next_evaluation_at: string | null;
};

export type MoneyAutopilotWorkspace = {
  rules: MoneyAutopilotRule[];
  recent_executions: Array<{
    id: number;
    status: string;
    action_type: string;
    amount_minor: number | null;
    currency: string;
    evidence: Record<string, unknown>;
    evaluated_at: string;
  }>;
  guardrail: string;
};

export type InvestmentWorkspace = {
  suitability: null | {
    risk_tolerance: string;
    investment_horizon: string;
    liquidity_need: string;
    experience_level: string;
    status: string;
    assessed_at: string;
  };
  products: Array<{
    id: number;
    product_code: string;
    name: string;
    provider_name: string;
    product_type: string;
    risk_level: string;
    minimum_investment_minor: number;
    currency: string;
    status: string;
    disclosures: Record<string, unknown> | unknown[];
  }>;
  orders: Array<{
    id: number;
    product_name: string;
    provider_name: string;
    amount_minor: number;
    currency: string;
    status: string;
    created_at: string;
  }>;
  settlement_status: string;
};

export type EmployerWorkspace = {
  employer: null | { id: number; name: string; status: string; country: string };
  membership: null | {
    membership_role: string;
    employee_reference: string | null;
    employment_status: string;
    employment_type: string | null;
    verified_monthly_income_minor: number | null;
    verified_at: string | null;
  };
  programs: Array<{
    id: number;
    name: string;
    benefit_type: string;
    status: string;
    eligibility_rules: Record<string, unknown> | unknown[];
    configuration: Record<string, unknown> | unknown[];
  }>;
  employees: Array<{
    id: number;
    name: string;
    membership_role: string;
    employee_reference: string | null;
    employment_status: string;
    employment_type: string | null;
    verified_monthly_income_minor: number | null;
    verified_at: string | null;
  }>;
};

async function request<T>(path: string, token?: string, init: RequestInit = {}): Promise<T> {
  if (!API_BASE_URL) throw new OpfinApiError("server", "OpFin API base URL is not configured.");

  let response: Response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      ...init,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
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
      response.status
    );
  }
  return payload.data as T;
}

export const experienceApi = {
  activation: (token?: string) => request<ActivationState>("/activation", token),
  saveActivation: (payload: Record<string, unknown>, token?: string) => request<ActivationState>("/activation", token, { method: "PATCH", body: JSON.stringify(payload) }),
  moneyAutopilot: (token?: string) => request<MoneyAutopilotWorkspace>("/money-autopilot", token),
  investments: (token?: string) => request<InvestmentWorkspace>("/investments/workspace", token),
  employer: (token?: string) => request<EmployerWorkspace>("/employer/workspace", token)
};
