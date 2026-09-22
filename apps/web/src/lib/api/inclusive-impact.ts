import { classifyStatus, OpfinApiError } from "./errors";
import type { InclusiveFinanceImpact } from "./inclusive-finance";

export type ImpactIndicator = {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  outcome_domain: string;
  value_type: string;
  unit?: string | null;
  calculation_methodology?: string | null;
  collection_method: string;
  source_type: string;
  verification_required: boolean;
  frequency?: string | null;
  baseline_required: boolean;
  privacy_classification: string;
  credit_decision_eligible: false;
  framework: string;
  framework_version: string;
  disaggregation_dimensions: string[];
  status: string;
  valid_from?: string | null;
  valid_to?: string | null;
  assignment?: {
    id: number;
    target_numeric?: number | null;
    target_text?: string | null;
    reporting_frequency?: string | null;
    baseline_required: boolean;
    metadata?: Record<string, unknown>;
  };
};

export type ProgrammeTheoryOfChange = {
  problem_statement?: string | null;
  inputs: unknown[];
  interventions: unknown[];
  outputs: unknown[];
  outcomes: unknown[];
  impact: unknown[];
  assumptions: unknown[];
  risks: unknown[];
  evidence_sources: unknown[];
  version: string;
  status: string;
};

export type ProgrammeFramework = {
  programme: {
    id: number;
    code: string;
    name: string;
    status: string;
    starts_at?: string | null;
    ends_at?: string | null;
  };
  theory_of_change?: ProgrammeTheoryOfChange | null;
  indicators: ImpactIndicator[];
  measurement_boundary: string;
};

export type IndicatorSummary = {
  indicator: ImpactIndicator;
  participant_count: number;
  observation_count: number;
  institutional_observation_count: number;
  suppressed: boolean;
  latest_observed_at?: string | null;
  average_numeric?: number;
  boolean_counts?: { true: number; false: number };
  boolean_distribution_suppressed?: boolean;
  category_counts?: Record<string, number>;
  category_distribution_suppressed?: boolean;
  institutional_latest_numeric?: number;
};

export type ProgrammeOutcomes = {
  programme: ProgrammeFramework["programme"];
  theory_of_change?: ProgrammeTheoryOfChange | null;
  indicator_summaries: IndicatorSummary[];
  snapshot_coverage: {
    financial_health_people: number;
    livelihood_people: number;
    empowerment_people: number;
    community_finance_people: number;
  };
  privacy: {
    minimum_cohort_size: number;
    individual_records_exposed: false;
    small_participant_indicator_cohorts_suppressed: boolean;
  };
  causality_notice: string;
};

export type PartnerProgramme = {
  id: number;
  code: string;
  name: string;
  status: string;
  starts_at?: string | null;
  ends_at?: string | null;
  access_level: "read_only" | "auditor" | "mel_officer" | "programme_admin" | string;
  partner_id: number;
  can_view_individual_records: false;
};

export type PartnerImpact = {
  delivery: InclusiveFinanceImpact;
  outcomes: ProgrammeOutcomes;
  access_boundary: {
    programme_scoped: true;
    individual_records_exposed: false;
  };
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

export const inclusiveImpactApi = {
  indicators: (token?: string) =>
    request<{
      indicators: ImpactIndicator[];
      outcome_domains: string[];
      value_types: string[];
      measurement_stages: string[];
      governance: {
        credit_decision_eligible: false;
        principle: string;
      };
    }>("/admin/inclusive-finance/indicators", token),

  programmeFramework: (programmeId: number, token?: string) =>
    request<ProgrammeFramework>(
      `/admin/inclusive-finance/programmes/${programmeId}/framework`,
      token
    ),

  programmeOutcomes: (programmeId: number, token?: string) =>
    request<ProgrammeOutcomes>(
      `/admin/inclusive-finance/programmes/${programmeId}/outcomes`,
      token
    ),

  partnerProgrammes: (token?: string) =>
    request<{ programmes: PartnerProgramme[] }>(
      "/partner/inclusive-finance/programmes",
      token
    ),

  partnerImpact: (programmeId: number, token?: string) =>
    request<PartnerImpact>(
      `/partner/inclusive-finance/programmes/${programmeId}/impact`,
      token
    )
};
