import type { ApiEnvelope } from "../types";
import type {
  BudgetSnapshot,
  CashFlowEntry,
  CashFlowSummary,
  FinancialAccount,
  FinancialCalendarEvent,
  FinancialCalendarPayload,
  FinancialCategoriesPayload,
  FinancialCompass
} from "../financial-wellbeing/types";
import { classifyStatus, OpfinApiError } from "./errors";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;
const USE_MOCKS = process.env.NEXT_PUBLIC_USE_MOCK_API === "true";

type RequestOptions = RequestInit & { token?: string; bodyJson?: unknown };

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

function envelope<T>(data: T, message = "Sandbox financial wellbeing data loaded"): ApiEnvelope<T> {
  return { success: true, message, data };
}

const now = new Date();
const mockAccount: FinancialAccount = {
  id: 1,
  display_name: "Primary mobile money",
  account_type: "mobile_money",
  balance_minor: 650000,
  currency: "UGX",
  confidence: "user_reported",
  observed_at: now.toISOString(),
  active: true
};
const mockEvent: FinancialCalendarEvent = {
  id: 1,
  title: "Rent",
  event_type: "bill",
  direction: "expense",
  amount_minor: 250000,
  currency: "UGX",
  scheduled_for: new Date(now.getTime() + 7 * 86400000).toISOString(),
  certainty: "scheduled",
  status: "upcoming",
  category: "Rent",
  source: "manual",
  source_reference: null,
  recurrence: "monthly",
  derived: false
};
const mockBudget: BudgetSnapshot = {
  id: 1,
  category: "Food",
  monthly_limit_minor: 200000,
  actual_minor: 75000,
  remaining_minor: 125000,
  utilization_percent: 37.5,
  alert_threshold_percent: 80,
  status: "on_track",
  currency: "UGX"
};
const mockEntry: CashFlowEntry = {
  id: 1,
  direction: "expense",
  amount_minor: 75000,
  currency: "UGX",
  category: "Food",
  description: "Household groceries",
  source: "manual",
  occurred_at: now.toISOString(),
  category_overridden: false
};
const mockCashFlow: CashFlowSummary = {
  from: new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10),
  to: new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10),
  currency: "UGX",
  income_minor: 1200000,
  expense_minor: 450000,
  net_minor: 750000,
  expense_by_category: [{ category: "Food", amount_minor: 75000 }],
  scope: "user_recorded_or_imported_entries",
  entries: [mockEntry]
};
const mockCompass: FinancialCompass = {
  as_of: now.toISOString(),
  currency: "UGX",
  position: {
    available_money_minor: 650000,
    available_money_confidence: "user_reported",
    committed_money_minor: 250000,
    safe_to_spend_minor: 400000,
    debt_obligations_minor: 0,
    current_savings_minor: 125000,
    upcoming_obligations_minor: 250000,
    next_income_event: null,
    safe_to_spend_explanation: "Safe-to-spend equals recorded available money less confirmed and scheduled obligations in the next 30 days."
  },
  cash_flow: mockCashFlow,
  budgets: [mockBudget],
  calendar: [mockEvent],
  goals: [],
  activity: [],
  next_best_action: {
    code: "review_position",
    title: "Review your money plan",
    text: "Your recorded position covers current scheduled obligations.",
    href: "/money",
    action: "Review money plan"
  }
};

function mockRequest<T>(path: string, init: RequestOptions): Promise<ApiEnvelope<T>> {
  if (path === "/financial-compass") return Promise.resolve(envelope(mockCompass as T));
  if (path === "/financial-categories") return Promise.resolve(envelope({ categories: ["Food", "Transport", "Rent", "Utilities", "School Fees", "Health", "Airtime/Data", "Business Stock", "Family Support", "Loan Repayment", "Savings", "Insurance", "Entertainment", "Transfers", "Other"] } as T));
  if (path === "/financial-accounts" && init.method === "POST") return Promise.resolve(envelope({ ...mockAccount, ...(init.bodyJson as object), id: Date.now() } as T));
  if (path === "/financial-accounts") return Promise.resolve(envelope({ accounts: [mockAccount] } as T));
  if (path === "/budgets" && init.method === "POST") return Promise.resolve(envelope({ ...mockBudget, ...(init.bodyJson as object), id: Date.now(), actual_minor: 0, remaining_minor: Number((init.bodyJson as { monthly_limit_minor?: number })?.monthly_limit_minor ?? 0), utilization_percent: 0, status: "on_track" } as T));
  if (path.startsWith("/budgets")) return Promise.resolve(envelope({ budgets: [mockBudget] } as T));
  if (path === "/cash-flow/entries" && init.method === "POST") return Promise.resolve(envelope({ ...mockEntry, ...(init.bodyJson as object), id: Date.now() } as T));
  if (path.startsWith("/cash-flow")) return Promise.resolve(envelope(mockCashFlow as T));
  if (path === "/financial-calendar/events" && init.method === "POST") return Promise.resolve(envelope({ ...mockEvent, ...(init.bodyJson as object), id: Date.now(), derived: false, status: "upcoming", source: "manual", source_reference: null } as T));
  if (path.startsWith("/financial-calendar")) return Promise.resolve(envelope({
    from: now.toISOString().slice(0, 10),
    to: new Date(now.getTime() + 90 * 86400000).toISOString().slice(0, 10),
    events: [mockEvent],
    certainty_legend: {
      confirmed: "Known obligation or income backed by system data.",
      scheduled: "User or provider scheduled event.",
      estimated: "User estimate; not guaranteed cash.",
      predicted: "Model-derived forecast; not guaranteed cash."
    }
  } as T));
  return Promise.resolve(envelope({} as T));
}

export const financialWellbeingApi = {
  compass: (token?: string) => request<FinancialCompass>("/financial-compass", { token }),
  categories: (token?: string) => request<FinancialCategoriesPayload>("/financial-categories", { token }),
  accounts: (token?: string) => request<{ accounts: FinancialAccount[] }>("/financial-accounts", { token }),
  createAccount: (payload: { display_name: string; account_type: string; balance_minor: number; currency?: string; observed_at?: string }, token?: string) =>
    request<FinancialAccount>("/financial-accounts", { method: "POST", bodyJson: payload, token }),
  createBudget: (payload: { category: string; monthly_limit_minor: number; effective_from: string; currency?: string; alert_threshold_percent?: number }, token?: string) =>
    request<BudgetSnapshot>("/budgets", { method: "POST", bodyJson: payload, token }),
  cashFlow: (token?: string) => request<CashFlowSummary>("/cash-flow", { token }),
  createEntry: (payload: { direction: "income" | "expense"; amount_minor: number; description?: string; category?: string; occurred_at: string; currency?: string }, token?: string) =>
    request<CashFlowEntry>("/cash-flow/entries", { method: "POST", bodyJson: payload, token }),
  updateEntryCategory: (entryId: number, category: string, token?: string) =>
    request<CashFlowEntry>(`/cash-flow/entries/${entryId}`, { method: "PATCH", bodyJson: { category }, token }),
  calendar: (token?: string) => request<FinancialCalendarPayload>("/financial-calendar", { token }),
  createCalendarEvent: (payload: { title: string; event_type: string; direction: "income" | "expense"; amount_minor: number; scheduled_for: string; certainty?: "scheduled" | "estimated"; recurrence?: "weekly" | "monthly"; category?: string; notes?: string; currency?: string }, token?: string) =>
    request<FinancialCalendarEvent>("/financial-calendar/events", { method: "POST", bodyJson: payload, token })
};
