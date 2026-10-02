import { classifyStatus, OpfinApiError } from "./errors";

export type DeletionBlocker = {
  code: string;
  label: string;
  reference: string;
  status: string;
  amount_minor: number | null;
  amount_basis?: string;
  currency: string;
  due_date: string | null;
  provider: { name: string; phone: string | null; email: string | null; address: string | null; direct_contact_available: boolean };
};
export type DeletionCategory = { code: string; label: string; description: string };
export type DeletionReadiness = {
  can_delete_account: boolean;
  active_obligations: DeletionBlocker[];
  data_categories: DeletionCategory[];
  retained_record_categories: string[];
};
export type AccountDeletionResult = {
  deletion_status: "completed" | "blocked_obligations";
  message: string;
  active_obligations: DeletionBlocker[];
  retained_record_categories: string[];
};

type JsonRecord = Record<string, unknown>;
function object(value: unknown): value is JsonRecord {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
function text(value: unknown): string | null { return typeof value === "string" && value.trim() !== "" ? value : null; }
function strings(value: unknown): string[] { return Array.isArray(value) ? value.filter((item): item is string => typeof item === "string") : []; }
function invalid(): never { throw new OpfinApiError("server", "The account response was not confirmed. Refresh before trying again."); }
function blockers(value: unknown): DeletionBlocker[] {
  if (!Array.isArray(value)) return invalid();
  return value.map((item: unknown): DeletionBlocker => {
    if (!object(item) || !object(item.provider) || !text(item.code) || !text(item.reference) || !text(item.status)) return invalid();
    if (item.amount_minor !== null && item.amount_minor !== undefined && !Number.isSafeInteger(item.amount_minor)) return invalid();
    const provider = item.provider;
    return {
      code: String(item.code), label: text(item.label) ?? "Outstanding obligation", reference: String(item.reference),
      status: String(item.status), amount_minor: typeof item.amount_minor === "number" ? item.amount_minor : null,
      amount_basis: text(item.amount_basis) ?? undefined, currency: text(item.currency) ?? "", due_date: text(item.due_date),
      provider: { name: text(provider.name) ?? "Recorded provider", phone: text(provider.phone), email: text(provider.email),
        address: text(provider.address), direct_contact_available: provider.direct_contact_available === true }
    };
  });
}
async function request(path: string, token: string | undefined, body?: JsonRecord): Promise<{ response: Response; payload: JsonRecord }> {
  const base = process.env.NEXT_PUBLIC_OPFIN_API_URL;
  if (!base) throw new OpfinApiError("server", "OpFin API base URL is not configured.");
  if (!token) throw new OpfinApiError("unauthorized", "Sign in to manage account deletion.", 401);
  let response: Response;
  try {
    response = await fetch(`${base.replace(/\/$/, "")}${path}`, {
      method: body ? "DELETE" : "GET", headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` },
      ...(body ? { body: JSON.stringify(body) } : {}), cache: "no-store", redirect: "error", signal: AbortSignal.timeout(30000)
    });
  } catch {
    throw new OpfinApiError("network", "Account status could not be confirmed. Refresh before retrying the request.");
  }
  const payload: unknown = await response.json().catch(() => null);
  if (!object(payload)) return invalid();
  return { response, payload };
}
function failure(response: Response, payload: JsonRecord): never {
  throw new OpfinApiError(classifyStatus(response.status), text(payload.message) ?? "Account request could not be completed.", response.status,
    {});
}
export async function getDeletionReadiness(token?: string): Promise<DeletionReadiness> {
  const { response, payload } = await request("/account/deletion-readiness", token);
  if (!response.ok) return failure(response, payload);
  const data = payload.data;
  if (payload.success !== true || !object(data) || typeof data.can_delete_account !== "boolean" || !object(data.data_deletion) || !Array.isArray(data.data_deletion.available_categories)) return invalid();
  const active = blockers(data.active_obligations);
  if (data.can_delete_account && active.length > 0) return invalid();
  return {
    can_delete_account: data.can_delete_account, active_obligations: active,
    data_categories: data.data_deletion.available_categories.map((item: unknown) => {
      if (!object(item) || !text(item.code) || !text(item.label)) return invalid();
      return { code: String(item.code), label: String(item.label), description: text(item.description) ?? "" };
    }), retained_record_categories: strings(data.data_deletion.retained_record_categories)
  };
}
export async function deleteAccount(pin: string, token?: string): Promise<AccountDeletionResult> {
  const { response, payload } = await request("/account", token, { pin, confirmation: "DELETE" });
  const data = payload.data;
  if (response.status === 409 && object(data) && data.deletion_status === "blocked_obligations") {
    return { deletion_status: "blocked_obligations", message: text(data.message) ?? "Resolve the listed obligations, then submit a fresh request.",
      active_obligations: blockers(data.active_obligations), retained_record_categories: strings(data.retained_record_categories) };
  }
  if (!response.ok) return failure(response, payload);
  if (payload.success !== true || !object(data) || data.deletion_status !== "completed") return invalid();
  return { deletion_status: "completed", message: text(data.message) ?? "Account deletion completed.",
    active_obligations: [], retained_record_categories: strings(data.retained_record_categories) };
}
export async function deleteOptionalAccountData(pin: string, categories: string[], token?: string): Promise<void> {
  if (categories.length === 0) throw new OpfinApiError("validation", "Select at least one optional data category.", 422);
  const { response, payload } = await request("/account/data", token, { pin, confirmation: "DELETE_DATA", data_categories: categories });
  if (!response.ok) return failure(response, payload);
  if (payload.success !== true || !object(payload.data) || payload.data.deletion_status !== "data_deleted") return invalid();
}
