export type InclusiveFinanceProgramme = {
  id: number;
  code: string;
  name: string;
  sponsor_space_id?: number | null;
  partner_id?: number | null;
  status: string;
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
  cohorts: Record<string, Record<string, number>>;
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

async function get<T>(path: string, token?: string): Promise<T> {
  const baseUrl = process.env.NEXT_PUBLIC_OPFIN_API_URL;
  if (!baseUrl) {
    throw new Error("OpFin API base URL is not configured.");
  }

  const response = await fetch(`${baseUrl}${path}`, {
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {})
    },
    cache: "no-store"
  });

  const payload = (await response.json().catch(() => ({}))) as Partial<ApiEnvelope<T>> & { message?: string };
  if (!response.ok || payload.success !== true || !payload.data) {
    throw new Error(payload.message ?? `OpFin API request failed: ${response.status}`);
  }

  return payload.data;
}

export const inclusiveFinanceApi = {
  programmes: (token?: string) =>
    get<{ programmes: InclusiveFinanceProgramme[] }>("/admin/inclusive-finance/programmes", token),
  impact: (programmeId?: number, token?: string) => {
    const query = programmeId ? `?programme_id=${programmeId}` : "";
    return get<InclusiveFinanceImpact>(`/admin/inclusive-finance/impact${query}`, token);
  }
};
