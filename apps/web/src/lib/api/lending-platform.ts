import { classifyStatus, OpfinApiError } from "./errors";

export type Lender = { id: number; name: string; lender_relationship: string; country: string; regulator_code?: string; authority_basis: string; status: string };
export type LenderProduct = { id: number; institution_id: number; name: string; country: string; currency: string; product_category: string; status: string; terms: { id: number; duration: number; status: string }[] };
export type LendingConfiguration = {
  institutions: Lender[];
  products: LenderProduct[];
  strategy: { id?: number; mode: string; reason: string; max_affiliated_loan_minor?: number };
  distribution_rules: { id: number; version: number; channel: string; country: string; product_category: string; availability: string; reason: string; source_reference: string }[];
  channels: string[];
  enabled_countries: string[];
  can_set_strategy: boolean;
};

export async function lendingPlatformRequest<T>(path: string, token?: string, body?: Record<string, unknown>, method = "POST"): Promise<T> {
  if (!token) throw new OpfinApiError("unauthorized", "Please sign in to manage lending configuration.");
  const base = process.env.NEXT_PUBLIC_OPFIN_API_URL;
  if (!base) throw new OpfinApiError("server", "The OpFin API is not configured.");
  const response = await fetch(`${base}/admin/lending-platform${path}`, {
    method: body ? method : "GET",
    headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` },
    ...(body ? { body: JSON.stringify(body) } : {}), cache: "no-store",
  });
  const result = await response.json();
  if (!response.ok) throw new OpfinApiError(classifyStatus(response.status), result.message ?? "Unable to update lending configuration.", response.status, result.errors ?? {});
  return result.data as T;
}
