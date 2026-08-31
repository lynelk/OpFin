import { OpfinApiError, classifyStatus } from "./errors";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;

export type AutopilotDomainCount = { domain: string; total: number };
export type AutopilotSeverityCount = { severity: string; total: number };
export type AutopilotSummary = {
  autonomy_rate: number;
  open_exceptions: number;
  open_automatic_items: number;
  by_domain: AutopilotDomainCount[];
  by_severity: AutopilotSeverityCount[];
  last_run: null | {
    id: number;
    status: string;
    observations: number;
    actions_executed: number;
    exceptions_created: number;
    completed_at: string | null;
  };
};

export type AutopilotWorkItem = {
  id: number;
  domain: string;
  type: string;
  severity: string;
  status: string;
  subject_type: string | null;
  subject_reference: string | null;
  title: string;
  description: string | null;
  recommended_action: string | null;
  confidence: string | number | null;
  automation_tier: string;
  requires_human: boolean;
  due_at: string | null;
  context: Record<string, unknown>;
};

async function request<T>(path: string, token?: string, init: RequestInit = {}): Promise<T> {
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
      response.status
    );
  }

  return payload.data as T;
}

export const autopilotApi = {
  summary: (token?: string) => request<AutopilotSummary>("/admin/autopilot/summary", token),
  workQueue: (token?: string, domain?: string) => request<{ items: AutopilotWorkItem[] }>(`/admin/autopilot/work-queue${domain ? `?domain=${encodeURIComponent(domain)}` : ""}`, token),
  run: (token?: string) => request<AutopilotSummary & { run_id: number }>("/admin/autopilot/runs", token, { method: "POST" })
};
