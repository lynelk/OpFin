import { classifyStatus, OpfinApiError } from "./errors";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;

export type Capability = { status: string; owner: string; external_gate?: string };
export type LongRangeRecord = Record<string, unknown> & { id: number; status?: string; reference?: string; created_at?: string };

export type LongRangeOverview = {
  linked_accounts: LongRangeRecord[];
  household: LongRangeRecord | null;
  microbusiness: LongRangeRecord | null;
  asset_finance: LongRangeRecord[];
  community_memberships: LongRangeRecord[];
  participatory_listings: LongRangeRecord[];
  participatory_commitments: LongRangeRecord[];
  referrals: LongRangeRecord[];
  offline_sync: LongRangeRecord[];
  capital_mandates: LongRangeRecord[];
  capabilities: Record<string, Capability>;
};

export type LongRangeGovernance = {
  linked_accounts_pending: number;
  asset_finance_pending: number;
  community_memberships_pending: number;
  participatory_listings_pending: number;
  capital_mandates_pending: number;
  partners_pending: number;
  referrals_pending: number;
  financial_intents_awaiting_step_up: number;
  financial_intents_processing: number;
  offline_conflicts: number;
  queues: {
    linked_accounts: LongRangeRecord[];
    asset_finance: LongRangeRecord[];
    community_memberships: LongRangeRecord[];
    participatory_listings: LongRangeRecord[];
    capital_mandates: LongRangeRecord[];
    partners: LongRangeRecord[];
    referrals: LongRangeRecord[];
    offline_conflicts: LongRangeRecord[];
  };
  external_activation_gates: Array<{ capability: string; gate: string }>;
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
    throw new OpfinApiError(classifyStatus(response.status), typeof payload.message === "string" ? payload.message : `OpFin API request failed: ${response.status}`, response.status);
  }
  return payload.data as T;
}

const post = <T>(path: string, payload: Record<string, unknown>, token?: string) => request<T>(path, token, { method: "POST", body: JSON.stringify(payload) });
const put = <T>(path: string, payload: Record<string, unknown>, token?: string) => request<T>(path, token, { method: "PUT", body: JSON.stringify(payload) });

export const longRangeApi = {
  overview: (token?: string) => request<LongRangeOverview>("/long-range/overview", token),
  marketplace: (token?: string) => request<{ listings: LongRangeRecord[] }>("/long-range/participatory/marketplace", token),
  linkAccount: (payload: Record<string, unknown>, token?: string) => post<{ linked_account: LongRangeRecord }>("/long-range/linked-accounts", payload, token),
  saveHousehold: (payload: Record<string, unknown>, token?: string) => put<{ household: LongRangeRecord }>("/long-range/household", payload, token),
  saveMicrobusiness: (payload: Record<string, unknown>, token?: string) => put<{ microbusiness: LongRangeRecord }>("/long-range/microbusiness", payload, token),
  requestAssetFinance: (payload: Record<string, unknown>, token?: string) => post<{ asset_finance_request: LongRangeRecord }>("/long-range/asset-finance", payload, token),
  joinCommunity: (payload: Record<string, unknown>, token?: string) => post<{ membership: LongRangeRecord }>("/long-range/community-memberships", payload, token),
  createParticipatoryListing: (payload: Record<string, unknown>, token?: string) => post<{ listing: LongRangeRecord }>("/long-range/participatory/listings", payload, token),
  createParticipatoryCommitment: (payload: Record<string, unknown>, token?: string) => post<{ commitment: LongRangeRecord }>("/long-range/participatory/commitments", payload, token),
  createReferral: (payload: Record<string, unknown>, token?: string) => post<{ referral: LongRangeRecord }>("/long-range/referrals", payload, token),
  createFinancialIntent: (payload: Record<string, unknown>, token?: string) => post<{ financial_intent: LongRangeRecord }>("/long-range/financial-intents", payload, token),
  confirmFinancialIntent: (reference: string, verificationToken: string, token?: string) => post<{ financial_intent: LongRangeRecord }>(`/long-range/financial-intents/${reference}/confirm`, { verification_token: verificationToken }, token),
  createCapitalMandate: (payload: Record<string, unknown>, token?: string) => post<{ capital_mandate: LongRangeRecord }>("/long-range/capital-mandates", payload, token),
  createPartner: (payload: Record<string, unknown>, token?: string) => post<{ partner: LongRangeRecord }>("/long-range/partners", payload, token),
  governance: (token?: string) => request<LongRangeGovernance>("/admin/long-range/dashboard", token),
  reviewLinkedAccount: (id: number, payload: Record<string, unknown>, token?: string) => post(`/admin/long-range/linked-accounts/${id}/review`, payload, token),
  decideAssetFinance: (id: number, payload: Record<string, unknown>, token?: string) => post(`/admin/long-range/asset-finance/${id}/decision`, payload, token),
  reviewCommunity: (id: number, payload: Record<string, unknown>, token?: string) => post(`/admin/long-range/community-memberships/${id}/review`, payload, token),
  reviewParticipatoryListing: (id: number, payload: Record<string, unknown>, token?: string) => post(`/admin/long-range/participatory/listings/${id}/review`, payload, token),
  reviewCapitalMandate: (id: number, payload: Record<string, unknown>, token?: string) => post(`/admin/long-range/capital-mandates/${id}/review`, payload, token),
  reviewPartner: (id: number, payload: Record<string, unknown>, token?: string) => post(`/admin/long-range/partners/${id}/review`, payload, token),
  approveReferralReward: (id: number, rewardMinor: number, token?: string) => post(`/admin/long-range/referrals/${id}/reward`, { reward_minor: rewardMinor }, token)
};
