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
    request<{ alert: IntegrityAlert }>(`/admin/governance/integrity-alerts/${alertId}/resolve`, token, { method: "POST", body: JSON.stringify({ resolution }) })
};
