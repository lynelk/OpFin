export type FinancialAccount = {
  id: number;
  display_name: string;
  account_type: "cash" | "mobile_money" | "bank" | "other";
  balance_minor: number;
  currency: string;
  confidence: "user_reported" | "connected_verified" | string;
  observed_at: string | null;
  active: boolean;
};

export type BudgetSnapshot = {
  id: number;
  category: string;
  monthly_limit_minor: number;
  actual_minor: number;
  remaining_minor: number;
  utilization_percent: number;
  alert_threshold_percent: number;
  status: "on_track" | "attention" | "over_budget" | string;
  currency: string;
};

export type CashFlowEntry = {
  id: number;
  direction: "income" | "expense";
  amount_minor: number;
  currency: string;
  category: string;
  description: string | null;
  source: string;
  source_reference?: string | null;
  occurred_at: string;
  category_overridden: boolean;
};

export type CashFlowSummary = {
  from: string;
  to: string;
  currency: string;
  income_minor: number;
  expense_minor: number;
  net_minor: number;
  expense_by_category: Array<{ category: string; amount_minor: number }>;
  scope: string;
  entries: CashFlowEntry[];
};

export type FinancialCalendarEvent = {
  id: string | number;
  title: string;
  event_type: string;
  direction: "income" | "expense";
  amount_minor: number;
  currency: string;
  scheduled_for: string;
  certainty: "confirmed" | "scheduled" | "estimated" | "predicted";
  status: string;
  category: string | null;
  source: string;
  source_reference: string | null;
  recurrence: "weekly" | "monthly" | null;
  derived: boolean;
};

export type FinancialGoalSummary = {
  reference: string;
  name: string;
  target_amount_minor: number;
  balance_minor: number;
  target_date: string | null;
  status: string;
};

export type FinancialActivity = {
  type: string;
  reference: string;
  title: string;
  direction: "income" | "expense";
  amount_minor: number;
  currency: string;
  status: string;
  occurred_at: string | null;
};

export type NextBestAction = {
  code: string;
  title: string;
  text: string;
  href: string;
  action: string;
};

export type FinancialCompassPosition = {
  available_money_minor: number | null;
  available_money_confidence: string | null;
  committed_money_minor: number;
  safe_to_spend_minor: number | null;
  debt_obligations_minor: number;
  current_savings_minor: number;
  upcoming_obligations_minor: number;
  next_income_event: FinancialCalendarEvent | null;
  safe_to_spend_explanation: string;
};

export type FinancialCompass = {
  as_of: string;
  currency: string;
  position: FinancialCompassPosition;
  cash_flow: CashFlowSummary;
  budgets: BudgetSnapshot[];
  calendar: FinancialCalendarEvent[];
  goals: FinancialGoalSummary[];
  activity: FinancialActivity[];
  next_best_action: NextBestAction;
};

export type FinancialCalendarPayload = {
  from: string;
  to: string;
  events: FinancialCalendarEvent[];
  certainty_legend: Record<FinancialCalendarEvent["certainty"], string>;
};

export type FinancialCategoriesPayload = {
  categories: string[];
};
