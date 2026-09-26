import "server-only";
import { positiveId } from "@/lib/financial-intelligence/presentation";

export type Context = { space_id: number; space_name: string; space_type: string; role: string; permissions: string[]; country: string };
export type Metrics = Record<string, number | null>;
export type Analysis = { as_of: string; source_system: string; population: string; source_hash: string; engine_version: string;
  currency_metrics: Record<string, Metrics>; concentrations: Array<{ currency: string; dimension: string; value: string; loan_count: number; principal_minor: number; par30_principal_minor: number; unknown_count: number }>;
  vintages: Array<Record<string, string | number>>; financials: Array<Record<string, string | number | boolean | null>>; definitions: Record<string, string> };
export type Source = { id: number; name: string; population: string; country: string; status: string; current_import_id: number | null; as_of: string | null; last_published_at: string | null; stale: boolean | null; analysis: Analysis | null };
export type Overview = { space_id: number; space_name: string; role: string; permissions: string[]; sources: Source[] };
export type ImportSummary = { id: number; source_id: number; as_of: string; row_count: number; status: string; submitted_by: number; reviewed_by: number | null; source_hash: string; engine_version: string; created_at: string; reviewed_at: string | null; source_authenticity: string };
export type Loan = { loan_ref: string; borrower_ref: string; currency: string; principal_outstanding_minor: number; dpd: number | null; arrears_minor: number | null; restructured: boolean; unlikely_to_pay: boolean; regulatory_npl: boolean | null; product: string | null; branch: string | null };
export type CaseItem = { id: number; source_id: number; import_id: number; signal: string; status: string; priority: string; assigned_to: number | null; due_on: string | null; version: number; evidence: { loan_ref: string; borrower_ref: string; currency: string; principal_outstanding_minor: number; dpd: number | null; arrears_minor: number | null; as_of: string; warning: string } };
export type Statement = { id: number; issuer_version_id: number; status: string; period_start: string; period_end: string; file_hash: string; source_authenticity: string; account_ownership: string; credit_decision_eligible: false; authority_expires_at: string; revoked_at: string | null };
export type Issuer = { id: number; legal_name: string; country: string; product_type: string; regulator: string; licence_reference: string; valid_from: string; valid_until: string; review_due_on: string };
export type StatementDetail = { statement: Statement; issuer_eligibility_current: boolean; source_authenticity: string; credit_decision_eligible: false; analysis: null | {
  currency: string; credits_minor: number; debits_minor: number; net_movement_minor: number; financial_checks: string; interpretation: string;
  verified_income_minor: null; row_count: number; transaction_total: number; page: number;
  transactions: Array<{ row_number: number; date: string; description: string; direction: string; amount_minor: number; balance_minor: number | null; suggested_category: string }>;
  findings: Array<{ code: string; row: number | null; severity: string }>;
  monthly_activity: Array<{ month: string; credits_minor: number; debits_minor: number; net_movement_minor: number }>;
} };
export type Page<T> = { items: T[]; total?: number; page: number; page_size?: number };
export type FrozenReport = { report_id: number; content_hash: string; report: { title: string; space_name: string; as_of: string; generated_at: string; import_id: number; source_hash: string; analysis: Analysis; disclaimer: string } };
export class IntelligenceApiError extends Error {
  constructor(public readonly status: number, message: string) { super(message); this.name = "IntelligenceApiError"; }
  get kind(): string { return this.status === 401 ? "unauthenticated" : this.status === 403 ? "forbidden" : this.status === 404 ? "missing" : this.status === 409 ? "conflict" : [413, 422].includes(this.status) ? "validation" : this.status === 503 ? "unavailable" : "server"; }
}
function baseUrl(): string {
  const value = process.env.NEXT_PUBLIC_OPFIN_API_URL;
  if (!value) throw new IntelligenceApiError(503, "API configuration is unavailable.");
  return value.replace(/\/$/, "");
}
function spacePath(space: number): string { positiveId(String(space)); return `/financial-spaces/${space}/intelligence`; }
async function request<T>(space: number, path: string, token: string | undefined, method = "GET", body?: unknown, key?: string): Promise<T> {
  if (!token) throw new IntelligenceApiError(401, "Authentication required.");
  const form = typeof FormData !== "undefined" && body instanceof FormData;
  let response: Response;
  try {
    response = await fetch(baseUrl() + spacePath(space) + path, { method, cache: "no-store", signal: AbortSignal.timeout(45000),
      headers: { Accept: "application/json", Authorization: `Bearer ${token}`, ...(form ? {} : { "Content-Type": "application/json" }), ...(key ? { "Idempotency-Key": key } : {}) },
      body: body === undefined ? undefined : form ? body : JSON.stringify(body) });
  } catch (error) {
    if (error instanceof IntelligenceApiError) throw error;
    throw new IntelligenceApiError(503, "No confirmed API response was received.");
  }
  const envelope: unknown = await response.json().catch(() => null);
  if (!response.ok) throw new IntelligenceApiError(response.status, "Financial Intelligence request was not accepted.");
  if (!envelope || typeof envelope !== "object" || !("data" in envelope)) throw new IntelligenceApiError(502, "Invalid API response.");
  return (envelope as { data: T }).data;
}
const id = (value: number) => positiveId(String(value));
export const intelligenceApi = {
  context: (s: number, t?: string) => request<Context>(s, "/context", t),
  overview: (s: number, t?: string) => request<Overview>(s, "", t),
  createSource: (s: number, body: unknown, t?: string) => request<{ source_id: number }>(s, "/sources", t, "POST", body),
  template: (s: number, t?: string) => request<Record<string, unknown>>(s, "/template", t),
  imports: (s: number, source: number, page: number, t?: string) => request<Page<ImportSummary>>(s, `/sources/${id(source)}/imports?page=${id(page)}`, t),
  uploadPortfolio: (s: number, source: number, body: FormData, key: string, t?: string) => request<ImportSummary>(s, `/sources/${id(source)}/csv`, t, "POST", body, key),
  importDetail: (s: number, record: number, page: number, t?: string) => request<{ import: ImportSummary; analysis: Analysis; loans: Loan[]; page: number; total: number }>(s, `/imports/${id(record)}?page=${id(page)}`, t),
  review: (s: number, record: number, body: unknown, t?: string) => request<ImportSummary>(s, `/imports/${id(record)}/review`, t, "POST", body),
  compare: (s: number, from: number, to: number, t?: string) => request<Record<string, unknown>>(s, `/comparison?from_import_id=${id(from)}&to_import_id=${id(to)}`, t),
  stress: (s: number, body: unknown, t?: string) => request<Record<string, unknown>>(s, "/stress", t, "POST", body),
  cases: (s: number, page: number, t?: string) => request<Page<CaseItem>>(s, `/cases?page=${id(page)}`, t),
  caseHistory: (s: number, record: number, page: number, t?: string) => request<{ case: CaseItem; events: Array<{ id: number; action: string; actor_id: number; version: number; created_at: string; detail: Record<string, unknown> }> }>(s, `/cases/${id(record)}/events?page=${id(page)}`, t),
  caseEvent: (s: number, record: number, body: unknown, key: string, t?: string) => request<{ case_id: number; version: number }>(s, `/cases/${id(record)}/events`, t, "POST", body, key),
  members: (s: number, t?: string) => request<{ members: Array<{ user_id: number; role: string }>; grants: Array<{ user_id: number; role: string; expires_at: string; revoked_at: string | null }> }>(s, "/members", t),
  grant: (s: number, body: unknown, t?: string) => request<Record<string, unknown>>(s, "/grants", t, "PUT", body),
  reports: (s: number, page: number, t?: string) => request<Page<{ id: number; import_id: number; content_hash: string; created_at: string }>>(s, `/reports?page=${id(page)}`, t),
  freezeReport: (s: number, record: number, t?: string) => request<{ report_id: number; content_hash: string }>(s, "/reports", t, "POST", { import_id: id(record) }),
  report: (s: number, record: number, t?: string) => request<FrozenReport>(s, `/reports/${id(record)}`, t),
  share: (s: number, record: number, body: unknown, t?: string) => request<Record<string, unknown>>(s, `/reports/${id(record)}/share`, t, "PUT", body),
  network: (s: number, page: number, t?: string) => request<Page<{ grant_id: number; expires_at: string; shared_payload_hash: string; report: Record<string, unknown> }>>(s, `/network?page=${id(page)}`, t),
  issuers: (s: number, t?: string) => request<{ items: Issuer[]; note: string }>(s, "/issuers", t),
  statements: (s: number, page: number, t?: string) => request<Page<Statement>>(s, `/statements?page=${id(page)}`, t),
  statement: (s: number, record: number, page: number, t?: string) => request<StatementDetail>(s, `/statements/${id(record)}?page=${id(page)}`, t),
  uploadStatement: (s: number, body: FormData, key: string, t?: string) => request<Statement>(s, "/statements", t, "POST", body, key),
  revokeStatement: (s: number, record: number, t?: string) => request<Record<string, unknown>>(s, `/statements/${id(record)}/permission`, t, "DELETE"),
};
export async function reportBytes(space: number, report: number, format: "csv" | "html", token?: string): Promise<Response> {
  if (!token) throw new IntelligenceApiError(401, "Authentication required.");
  return fetch(baseUrl() + spacePath(space) + `/reports/${id(report)}/${format}`, { headers: { Authorization: `Bearer ${token}` }, cache: "no-store", signal: AbortSignal.timeout(45000) });
}
