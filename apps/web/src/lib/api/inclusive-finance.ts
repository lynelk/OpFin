import { classifyStatus, OpfinApiError } from "./errors";

export type ProgrammeEligibilityRule = {
  field: string;
  operator: "equals" | "in";
  value?: string | boolean;
  values?: Array<string | boolean>;
};

export type InclusiveFinanceProgramme = {
  id: number;
  code: string;
  name: string;
  sponsor_space_id?: number | null;
  partner_id?: number | null;
  status: string;
  target_population?: Record<string, unknown>;
  eligibility_rules?: {
    all?: ProgrammeEligibilityRule[];
    any?: ProgrammeEligibilityRule[];
  };
  starts_at?: string | null;
  ends_at?: string | null;
  enrolled_people?: number;
};

export type InclusiveFinanceImpact = {
  programme_id?: number | null;
  enrolled_people: number;
  applications: number;
  decisions: Record<string, number>;
  average_approved_amount_minor: number;
  npl_count: number;
  impact_events: Record<string, number>;
  capability_events: Record<string, number>;
  participant_capability_events: Record<string, number>;
  cohorts: Record<string, Record<string, number>>;
  measurement_notes: {
    credit_outcomes_window: string;
    capability_events_window: string;
  };
  privacy: {
    minimum_cohort_size: number;
    small_cohorts_suppressed: boolean;
    only_consented_measurement_profiles_included: boolean;
  };
};

type ApiEnvelope<T> = {
  success: boolean;
  message: string;
  data: T;
};

export type CreateInclusiveFinanceProgramme = {
  code: string;
  name: string;
  status?: "draft" | "active" | "paused" | "closed";
  sponsor_space_id?: number | null;
  partner_id?: number | null;
  target_population?: Record<string, unknown>;
  eligibility_rules?: {
    all?: ProgrammeEligibilityRule[];
    any?: ProgrammeEligibilityRule[];
  };
  starts_at?: string | null;
  ends_at?: string | null;
};

async function request<T>(
  path: string,
  token?: string,
  init: RequestInit = {}
): Promise<T> {
  const baseUrl = process.env.NEXT_PUBLIC_OPFIN_API_URL;
  if (!baseUrl) {
    throw new OpfinApiError("server", "OpFin API base URL is not configured.");
  }

  let response: Response;
  try {
    response = await fetch(`${baseUrl}${path}`, {
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
    throw new OpfinApiError(
      "network",
      error instanceof Error ? error.message : "OpFin API is unreachable"
    );
  }

  const payload = (await response.json().catch(() => ({}))) as Partial<ApiEnvelope<T>> & {
    message?: string;
    errors?: Record<string, string[]>;
  };
  if (!response.ok || payload.success !== true || payload.data === undefined) {
    throw new OpfinApiError(
      classifyStatus(response.status),
      payload.message ?? `OpFin API request failed: ${response.status}`,
      response.status,
      payload.errors ?? {}
    );
  }

  return payload.data;
}

export const inclusiveFinanceApi = {
  programmes: (token?: string) =>
    request<{ programmes: InclusiveFinanceProgramme[] }>("/admin/inclusive-finance/programmes", token),
  impact: (programmeId?: number, token?: string) => {
    const query = programmeId ? `?programme_id=${programmeId}` : "";
    return request<InclusiveFinanceImpact>(`/admin/inclusive-finance/impact${query}`, token);
  },
  createProgramme: (payload: CreateInclusiveFinanceProgramme, token?: string) =>
    request<InclusiveFinanceProgramme>("/admin/inclusive-finance/programmes", token, {
      method: "POST",
      body: JSON.stringify(payload)
    })
};
