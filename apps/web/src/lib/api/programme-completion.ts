import { classifyStatus, OpfinApiError } from "./errors";

export type ProgrammeQuestion = {
  id: number;
  code: string;
  indicator_definition_id?: number | null;
  answer_type: "text" | "integer" | "decimal" | "boolean" | "single_choice" | "multi_choice" | "currency_minor" | string;
  required: boolean;
  sort_order: number;
  options: string[];
  validation_rules: Record<string, unknown>;
  verification_source: string;
  prompt: string;
  help_text?: string | null;
  locale: string;
  requested_locale: string;
  translation_fallback: boolean;
  credit_decision_eligible: false;
};

export type ProgrammeInstrument = {
  id: number;
  programme_id: number;
  code: string;
  name: string;
  description?: string | null;
  outcome_domain: string;
  default_measurement_stage: string;
  consent_classification: string;
  channels: string[];
  default_locale: string;
  supported_locales: string[];
  schedule_config: Array<{ stage: string; offset_days: number }>;
  status: string;
  version: string;
  questions: ProgrammeQuestion[];
  credit_decision_eligible: false;
  programme?: { code: string; name: string };
  schedule?: {
    id: number;
    measurement_stage: string;
    due_at: string;
    status: string;
  };
};

export type ProgrammeOperations = {
  programme_id?: number | null;
  active_enrolments: number;
  scheduled: number;
  due_next_7_days: number;
  overdue: number;
  completed: number;
  consent_exceptions: number;
  baseline_missing: number;
  follow_ups: Array<{
    id: number;
    programme_id: number;
    user_id: number;
    instrument_name: string;
    measurement_stage: string;
    due_at: string;
    status: string;
  }>;
  data_quality: {
    consent_exceptions: number;
    baseline_missing: number;
  };
};

export type CommercialDashboard = {
  period: {
    from: string;
    to: string;
    currency: string;
    channel?: string | null;
    programme_id?: number | null;
  };
  acquisition: {
    customers: number;
    acquisition_cost_minor: number;
    cac_minor?: number | null;
    by_channel: Record<string, number>;
  };
  funnel: {
    applications: number;
    approved: number;
    declined: number;
    referred: number;
    approval_rate_percent?: number | null;
    disbursed_loans: number;
    principal_disbursed_minor: number;
  };
  portfolio: {
    borrowers: number;
    repeat_borrowers: number;
    repeat_rate_percent?: number | null;
    npl_count: number;
    npl_rate_percent?: number | null;
    overdue_loan_count: number;
    npl_principal_exposure_minor: number;
  };
  economics: {
    opfin_revenue_minor: number;
    recorded_cost_minor: number;
    cost_by_type: Record<string, number>;
    contribution_before_credit_loss_minor: number;
    contribution_after_npl_exposure_minor: number;
    revenue_per_acquired_customer_minor?: number | null;
  };
  cohorts: Array<{
    cohort: string;
    customers: number;
    loans_to_date: number;
    repeat_borrowers_to_date: number;
  }>;
  measurement_notes: Record<string, string>;
};

export type GraduationSummary = {
  programme_id?: number | null;
  evaluated_participants: number;
  graduated_participants: number;
  graduation_rate_percent?: number | null;
  criteria: string[];
  boundary: string;
};

export type ProgrammeTemplate = {
  code: string;
  name: string;
  description: string;
};

export type ProviderAdapter = {
  id: number;
  programme_id?: number | null;
  partner_id?: number | null;
  code: string;
  name: string;
  adapter_type: string;
  status: string;
  purpose: string;
  allowed_signal_keys: string[];
  signal_mapping: Record<string, string>;
  requires_credit_processing_consent: boolean;
  credentials_configured: boolean;
  legal_basis_confirmed: boolean;
  activation_notes?: string | null;
  external_credentials_stored_here: false;
};

type ApiEnvelope<T> = {
  success: boolean;
  message: string;
  data: T;
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

export const programmeCompletionApi = {
  dueCheckIns: (token?: string, locale?: string) => {
    const params = new URLSearchParams({ channel: "web" });
    if (locale) params.set("locale", locale);
    return request<{
      instruments: ProgrammeInstrument[];
      channel: string;
      locale: string;
      fallback_locale: string;
      translation_policy: string;
    }>(`/inclusive-finance/programme-check-ins?${params.toString()}`, token);
  },

  submitCheckIn: (
    instrumentId: number,
    payload: {
      schedule_id?: number;
      locale?: string;
      answers: Array<{ question_id: number; value: unknown }>;
    },
    token?: string
  ) =>
    request<Record<string, unknown>>(
      `/inclusive-finance/programme-check-ins/${instrumentId}/responses`,
      token,
      {
        method: "POST",
        body: JSON.stringify({ ...payload, channel: "web" })
      }
    ),

  createInstrument: (
    payload: {
      programme_id: number;
      code: string;
      name: string;
      description?: string;
      outcome_domain: string;
      default_measurement_stage?: string;
      consent_classification?: string;
      channels: string[];
      default_locale?: string;
      supported_locales?: string[];
      schedule_config?: Array<{ stage: string; offset_days: number }>;
      status?: string;
      version?: string;
    },
    token?: string
  ) =>
    request<ProgrammeInstrument>("/admin/inclusive-finance/instruments", token, {
      method: "POST",
      body: JSON.stringify(payload)
    }),

  addQuestion: (
    instrumentId: number,
    payload: {
      code: string;
      prompt: string;
      help_text?: string;
      indicator_definition_id?: number;
      answer_type: string;
      required?: boolean;
      sort_order?: number;
      options?: string[];
      validation_rules?: Record<string, unknown>;
      verification_source?: string;
    },
    token?: string
  ) =>
    request<ProgrammeQuestion>(
      `/admin/inclusive-finance/instruments/${instrumentId}/questions`,
      token,
      {
        method: "POST",
        body: JSON.stringify(payload)
      }
    ),

  upsertTranslation: (
    questionId: number,
    payload: {
      locale: string;
      prompt: string;
      help_text?: string;
      options?: string[];
    },
    token?: string
  ) =>
    request<Record<string, unknown>>(
      `/admin/inclusive-finance/questions/${questionId}/translations`,
      token,
      {
        method: "PUT",
        body: JSON.stringify(payload)
      }
    ),

  assistedCapture: (
    instrumentId: number,
    payload: {
      user_id: number;
      schedule_id?: number;
      locale?: string;
      answers: Array<{ question_id: number; value: unknown }>;
    },
    token?: string
  ) =>
    request<Record<string, unknown>>(
      `/admin/inclusive-finance/instruments/${instrumentId}/assisted-responses`,
      token,
      {
        method: "POST",
        body: JSON.stringify(payload)
      }
    ),

  operations: (programmeId?: number, token?: string) =>
    request<ProgrammeOperations>(
      `/admin/inclusive-finance/operations${programmeId ? `?programme_id=${programmeId}` : ""}`,
      token
    ),

  instruments: (programmeId?: number, token?: string) =>
    request<{
      instruments: ProgrammeInstrument[];
      channels: string[];
      locales: string[];
      answer_types: string[];
      consent_classifications: string[];
    }>(
      `/admin/inclusive-finance/instruments${programmeId ? `?programme_id=${programmeId}` : ""}`,
      token
    ),

  templates: (token?: string) =>
    request<{ templates: ProgrammeTemplate[] }>(
      "/admin/inclusive-finance/templates",
      token
    ),

  applyTemplate: (programmeId: number, templateCode: string, token?: string) =>
    request<Record<string, unknown>>(
      `/admin/inclusive-finance/programmes/${programmeId}/templates`,
      token,
      {
        method: "POST",
        body: JSON.stringify({ template_code: templateCode })
      }
    ),

  generateFollowUps: (programmeId?: number, token?: string) =>
    request<{ generated: number }>(
      "/admin/inclusive-finance/follow-ups/generate",
      token,
      {
        method: "POST",
        body: JSON.stringify(programmeId ? { programme_id: programmeId } : {})
      }
    ),

  invitePartner: (
    payload: {
      programme_id: number;
      partner_id: number;
      invited_name: string;
      invited_phone?: string;
      invited_email?: string;
      access_level: string;
    },
    token?: string
  ) =>
    request<{
      invitation_id: number;
      programme_id: number;
      access_level: string;
      expires_at: string;
      activation_token: string;
      delivery_status: string;
      notice: string;
    }>("/admin/inclusive-finance/partner-invitations", token, {
      method: "POST",
      body: JSON.stringify(payload)
    }),

  commercialDashboard: (token?: string, programmeId?: number) =>
    request<CommercialDashboard>(
      `/admin/commercial/dashboard${programmeId ? `?programme_id=${programmeId}` : ""}`,
      token
    ),

  graduationSummary: (token?: string, programmeId?: number) =>
    request<GraduationSummary>(
      `/admin/commercial/graduations${programmeId ? `?programme_id=${programmeId}` : ""}`,
      token
    ),

  adapters: (token?: string, programmeId?: number) =>
    request<{
      adapters: ProviderAdapter[];
      adapter_types: string[];
      purposes: string[];
      activation_rule: string;
    }>(
      `/admin/inclusive-finance/provider-adapters${programmeId ? `?programme_id=${programmeId}` : ""}`,
      token
    ),

  recordCommercialCost: (
    payload: {
      cost_type: string;
      channel?: string;
      programme_id?: number;
      amount_minor: number;
      currency?: string;
      quantity?: number;
      source_reference?: string;
      occurred_at?: string;
    },
    token?: string
  ) =>
    request<Record<string, unknown>>("/admin/commercial/costs", token, {
      method: "POST",
      body: JSON.stringify(payload)
    }),

  recordAttribution: (
    userId: number,
    payload: {
      acquisition_channel: string;
      source?: string;
      campaign?: string;
      programme_id?: number;
      acquired_at?: string;
    },
    token?: string
  ) =>
    request<Record<string, unknown>>(
      `/admin/commercial/customers/${userId}/attribution`,
      token,
      {
        method: "POST",
        body: JSON.stringify(payload)
      }
    ),

  configureAdapter: (
    payload: {
      id?: number;
      programme_id?: number;
      partner_id?: number;
      code: string;
      name: string;
      adapter_type: string;
      status?: string;
      purpose?: string;
      allowed_signal_keys: string[];
      signal_mapping?: Record<string, string>;
      requires_credit_processing_consent?: boolean;
      credentials_configured?: boolean;
      legal_basis_confirmed?: boolean;
      activation_notes?: string;
    },
    token?: string
  ) =>
    request<ProviderAdapter>("/admin/inclusive-finance/provider-adapters", token, {
      method: "POST",
      body: JSON.stringify(payload)
    })
};
