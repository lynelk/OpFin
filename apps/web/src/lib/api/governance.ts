import { classifyStatus, OpfinApiError } from "./errors";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;

type Envelope<T> = { success: boolean; message: string; data: T };

export type IntegrityRun = {
  id: number;
  status: string;
  ledger_transactions_checked: number;
  unbalanced_transactions: number;
  payment_exceptions: number;
  duplicate_references: number;
  orphan_entries: number;
  net_ledger_imbalance_minor: number;
  evidence_hash?: string | null;
  completed_at?: string | null;
};

export type IntegrityAlert = {
  id: number;
  severity: string;
  type: string;
  reference?: string | null;
  description: string;
  status: string;
  created_at: string;
};

export type RegulatoryReport = {
  id: number;
  report_type: string;
  regulator: string;
  period_start: string;
  period_end: string;
  status: string;
  payload_hash: string;
  generated_at: string;
  validated_at?: string | null;
  approved_at?: string | null;
};

export type CreditReferenceSubmission = {
  id: number;
  loan_id?: number | null;
  event_type: string;
  information_type: "positive" | "negative" | string;
  reporting_date: string;
  status: string;
  payload_hash: string;
  provider_reference?: string | null;
  retry_count: number;
  due_at: string;
  submitted_at?: string | null;
  error_message?: string | null;
};

export type NplControl = {
  id: number;
  loan_id: number;
  non_performing_at?: string | null;
  principal_at_npl_minor: number;
  default_penalty_cap_minor: number;
  recoverable_interest_cap_minor: number;
  total_recoverable_cap_minor: number;
  total_recovered_since_npl_minor: number;
  enforcement_mode: string;
  last_evaluated_at?: string | null;
};

export type TermVariation = {
  id: number;
  loan_id: number;
  status: string;
  proposed_changes: Record<string, unknown>;
  reason: string;
  requires_umra_approval: boolean;
  umra_approval_reference?: string | null;
  umra_approved_at?: string | null;
  customer_consented_at?: string | null;
  applied_at?: string | null;
};

export type GovernanceDashboard = {
  integrity: {
    latest_run: IntegrityRun | null;
    open_critical_alerts: number;
    open_high_alerts: number;
    platform_balanced: boolean;
    funds_integrity_rule: string;
  };
  regulatory_reports: RegulatoryReport[];
  open_integrity_alerts: IntegrityAlert[];
  whatsapp: {
    verified_sessions: number;
    messages_24h: number;
    audit_hashes_present: number;
  };
};

async function request<T>(path: string, token?: string, init: RequestInit = {}): Promise<Envelope<T>> {
  if (!API_BASE_URL) {
    throw new OpfinApiError("server", "OpFin API base URL is not configured.");
  }

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
      response.status,
      typeof payload.errors === "object" && payload.errors ? payload.errors : {}
    );
  }

  return payload as Envelope<T>;
}

export const governanceApi = {
  dashboard: (token?: string) => request<GovernanceDashboard>("/admin/governance/dashboard", token),
  reports: (token?: string) => request<{ profiles: Record<string, string>; reports: RegulatoryReport[] }>("/admin/governance/regulatory-reports", token),
  generateReport: (payload: { report_type: string; period_start: string; period_end: string }, token?: string) =>
    request<{ report: RegulatoryReport }>("/admin/governance/regulatory-reports", token, { method: "POST", body: JSON.stringify(payload) }),
  approveReport: (reportId: number, token?: string) =>
    request<{ report: RegulatoryReport }>(`/admin/governance/regulatory-reports/${reportId}/approve`, token, { method: "POST" }),
  runIntegrity: (token?: string) => request<{ run: IntegrityRun }>("/admin/governance/integrity-runs", token, { method: "POST" }),
  resolveIntegrityAlert: (alertId: number, resolution: string, token?: string) =>
    request<{ alert: IntegrityAlert }>(`/admin/governance/integrity-alerts/${alertId}/resolve`, token, { method: "POST", body: JSON.stringify({ resolution }) }),
  umraCreditReferences: (token?: string) =>
    request<{ submissions: CreditReferenceSubmission[]; summary: Record<string, number> }>("/admin/umra/credit-reference-submissions", token),
  retryCreditReference: (submissionId: number, token?: string) =>
    request<{ submission: CreditReferenceSubmission }>(`/admin/umra/credit-reference-submissions/${submissionId}/retry`, token, { method: "POST" }),
  umraNplControls: (token?: string) =>
    request<{ controls: NplControl[]; enforcement_mode: string }>("/admin/umra/npl-controls", token),
  evaluateNpl: (loanId: number, token?: string) =>
    request<{ control: NplControl }>(`/admin/umra/loans/${loanId}/evaluate-npl`, token, { method: "POST" }),
  umraVariations: (token?: string) =>
    request<{ variations: TermVariation[] }>("/admin/umra/term-variations", token),
  proposeVariation: (loanId: number, payload: { proposed_changes: Record<string, unknown>; reason: string }, token?: string) =>
    request<{ variation: TermVariation }>(`/admin/umra/loans/${loanId}/term-variations`, token, { method: "POST", body: JSON.stringify(payload) }),
  recordUmraApproval: (variationId: number, payload: { approval_reference: string; approval_document_hash: string }, token?: string) =>
    request<{ variation: TermVariation }>(`/admin/umra/term-variations/${variationId}/umra-approval`, token, { method: "POST", body: JSON.stringify(payload) }),
  applyVariation: (variationId: number, token?: string) =>
    request<{ variation: TermVariation }>(`/admin/umra/term-variations/${variationId}/apply`, token, { method: "POST" })
};
