from pathlib import Path

Path('apps/web/src/lib/api/account.ts').write_text(r'''import { classifyStatus, OpfinApiError } from "./errors";

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
    object(payload.errors) ? payload.errors : {});
}
export async function getDeletionReadiness(token?: string): Promise<DeletionReadiness> {
  const { response, payload } = await request("/account/deletion-readiness", token);
  if (!response.ok) return failure(response, payload);
  const data = payload.data;
  if (payload.success !== true || !object(data) || typeof data.can_delete_account !== "boolean" || !Array.isArray(data.data_categories)) return invalid();
  const active = blockers(data.active_obligations);
  if (data.can_delete_account && active.length > 0) return invalid();
  return {
    can_delete_account: data.can_delete_account, active_obligations: active,
    data_categories: data.data_categories.map((item: unknown) => {
      if (!object(item) || !text(item.code) || !text(item.label)) return invalid();
      return { code: String(item.code), label: String(item.label), description: text(item.description) ?? "" };
    }), retained_record_categories: strings(data.retained_record_categories)
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
''')

Path('apps/web/src/app/account-delete-actions.ts').write_text(r'''"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { deleteAccount, deleteOptionalAccountData, type AccountDeletionResult } from "@/lib/api/account";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}
function failure(error: unknown): never {
  // Obligation amounts, references and contacts never enter a redirect URL.
  const kind = error instanceof OpfinApiError ? error.kind : "server";
  redirect(`/account/delete?error=${encodeURIComponent(kind)}`);
}
export async function deleteAccountAction(formData: FormData) {
  if (value(formData, "confirmation") !== "DELETE") redirect("/account/delete?error=validation");
  const token = await getAccessToken();
  let result: AccountDeletionResult;
  try { result = await deleteAccount(value(formData, "pin"), token); }
  catch (error) { return failure(error); }
  if (result.deletion_status !== "completed") redirect("/account/delete?status=blocked");
  const cookieStore = await cookies();
  for (const name of ["opfin_access_token", "opfin_role", "opfin_name", "opfin_demo_consent"]) cookieStore.delete(name);
  redirect("/login?status=account-deleted");
}
export async function deleteOptionalDataAction(formData: FormData) {
  if (value(formData, "confirmation") !== "DELETE_DATA") redirect("/account/delete?error=validation");
  const token = await getAccessToken();
  const categories = formData.getAll("data_categories").filter((item): item is string => typeof item === "string");
  try { await deleteOptionalAccountData(value(formData, "pin"), categories, token); }
  catch (error) { return failure(error); }
  redirect("/account/delete?status=data-deleted");
}
''')

Path('apps/web/src/app/(portal)/account/delete/page.tsx').write_text(r'''import Link from "next/link";
import { deleteAccountAction, deleteOptionalDataAction } from "@/app/account-delete-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { getDeletionReadiness, type DeletionReadiness } from "@/lib/api/account";

export default async function AccountDeletePage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string }> }>) {
  const params = searchParams ? await searchParams : {};
  const token = await getAccessToken();
  let readiness: DeletionReadiness | null = null;
  if (token) {
    try { readiness = await getDeletionReadiness(token); }
    catch { readiness = null; }
  }
  return (
    <Screen title="Account and optional-data deletion"
      description="Manage your account and optional data on the web without reinstalling the app."
      action={token ? <Link className="button secondary" href="/more">Back to More</Link> : <Link className="button secondary" href="/login?next=%2Faccount%2Fdelete">Verify account</Link>}>
      {params.status === "blocked" ? <StateNotice state="empty" message="Account deletion was rejected because obligations remain. No pending deletion request has been created. Resolve them, then submit a fresh request." /> : null}
      {params.status === "data-deleted" ? <StateNotice state="empty" message="Your selected optional data was deleted. Your account remains active." /> : null}
      {params.error ? <StateNotice state="validation" message="The request was not completed. Check the confirmation and current PIN, refresh your account status, and try again." /> : null}
      <section className="panel">
        <h2>Your choice</h2>
        <p>Delete selected optional data while keeping the account, or request full account deletion. OpFin checks loans, financing arrangements, investments, savings, claims, payroll reservations and other unresolved financial activity before account closure.</p>
        <p>Any blocker causes immediate rejection. Once obligations are settled or closed, submit a fresh deletion request.</p>
      </section>
      {!token ? <section className="panel"><h2>Verify your account to continue</h2>
        <p>Sign in on this website to check obligations and securely manage deletion. The mobile app is not required.</p>
        <Link className="button" href="/login?next=%2Faccount%2Fdelete">Sign in</Link></section> : null}
      {token && !readiness ? <StateNotice state="server" message="Deletion readiness could not be confirmed. Account deletion is unavailable until the current status loads. Refresh this page; your account remains accessible." /> : null}
      {readiness && readiness.active_obligations.length > 0 ? <section className="panel" aria-label="Deletion blockers">
        <h2>Resolve these obligations first</h2>
        {readiness.active_obligations.map((item, index) => <article key={`${item.code}:${item.reference}:${index}`} className="panel">
          <h3>{item.label}</h3><p>Type: {item.code.replaceAll("_", " ")}. Reference: {item.reference}. Status: {item.status.replaceAll("_", " ")}.</p>
          {item.amount_minor !== null ? <p>Relevant amount: {item.currency} {item.amount_minor.toLocaleString("en-GB")}{item.amount_basis === "contract_total_not_current_balance" ? " (contract total, not a current balance)" : ""}</p> : null}
          {item.due_date ? <p>Due or relevant date: {item.due_date}</p> : null}
          <p>Provider: {item.provider.name}</p>
          {item.provider.phone || item.provider.email || item.provider.address
            ? <p>Recorded contact: {[item.provider.phone, item.provider.email, item.provider.address].filter(Boolean).join(" · ")}</p>
            : <p>No direct provider contact is recorded in OpFin. Preserve the reference above when seeking closure support.</p>}
        </article>)}
      </section> : null}
      <section className="panel"><h2>Records retained where required</h2>
        <p>Regulated financial, KYC/AML, accounting, settlement, credit-reporting, security, dispute and audit records are preserved where retention is legally required. Deleting optional data does not erase obligations or financial evidence.</p>
      </section>
      {readiness ? <section className="panel"><h2>Delete selected optional data</h2>
        <form action={deleteOptionalDataAction} className="form-grid">
          <fieldset><legend>Optional categories to delete</legend>{readiness.data_categories.map((category) => <label key={category.code} className="field">
            <span><input type="checkbox" name="data_categories" value={category.code} /> {category.label}</span><span>{category.description}</span>
          </label>)}</fieldset>
          <div className="field"><label htmlFor="data-pin">Current 6-digit PIN</label><input id="data-pin" name="pin" type="password" inputMode="numeric" autoComplete="current-password" minLength={6} maxLength={6} required /></div>
          <div className="field"><label htmlFor="data-confirmation">Type DELETE_DATA to confirm selected-data deletion</label><input id="data-confirmation" name="confirmation" pattern="DELETE_DATA" autoComplete="off" required /></div>
          <button className="button secondary" type="submit">Delete selected data and keep account</button>
        </form>
      </section> : null}
      {readiness ? <section className="panel"><h2>Delete the entire account</h2>
        <p>{readiness.can_delete_account ? "No current obligation prevents deletion. OpFin will check again when you submit." : "Full deletion is blocked until the obligations above are resolved."}</p>
        <form action={deleteAccountAction} className="form-grid">
          <div className="field"><label htmlFor="pin">Current 6-digit PIN</label><input id="pin" name="pin" type="password" inputMode="numeric" autoComplete="current-password" minLength={6} maxLength={6} required disabled={!readiness.can_delete_account} /></div>
          <div className="field"><label htmlFor="confirmation">Type DELETE to confirm full account deletion</label><input id="confirmation" name="confirmation" autoComplete="off" pattern="DELETE" required disabled={!readiness.can_delete_account} /></div>
          <button className="button" type="submit" disabled={!readiness.can_delete_account}>Delete my account</button>
        </form>
      </section> : null}
    </Screen>
  );
}
''')

Path('apps/web/src/lib/api/account.test.ts').write_text(r'''import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { deleteAccount, deleteOptionalAccountData, getDeletionReadiness } from "./account";

const fetchMock = vi.fn();
const blocker = { code: "financing_arrangement", label: "Open financing", reference: "ARR-001", status: "active", amount_minor: 300000,
  amount_basis: "contract_total_not_current_balance", currency: "UGX", due_date: null,
  provider: { name: "Recorded provider", phone: null, email: null, address: null, direct_contact_available: false } };
function respond(status: number, data: unknown, success = status < 400) {
  fetchMock.mockResolvedValueOnce(new Response(JSON.stringify({ success, data }), { status, headers: { "Content-Type": "application/json" } }));
}
beforeEach(() => { vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "https://example.test/api"); vi.stubGlobal("fetch", fetchMock); fetchMock.mockReset(); });
afterEach(() => { vi.unstubAllGlobals(); vi.unstubAllEnvs(); });

describe("account closure confirmation", () => {
  it("returns structured immediate blockers without inventing contacts", async () => {
    respond(409, { deletion_status: "blocked_obligations", active_obligations: [blocker], retained_record_categories: [] });
    const result = await deleteAccount("482951", "test-token");
    expect(result.deletion_status).toBe("blocked_obligations");
    expect(result.active_obligations[0].provider.phone).toBeNull();
    expect(result.active_obligations[0].amount_basis).toBe("contract_total_not_current_balance");
  });
  it.each(["pending_obligations", "blocked_obligations", "data_deleted", "unknown"])("rejects a misleading HTTP 200 %s", async (status) => {
    respond(200, { deletion_status: status, active_obligations: [] });
    await expect(deleteAccount("482951", "test-token")).rejects.toThrow("not confirmed");
  });
  it("accepts only explicit completed closure", async () => {
    respond(200, { deletion_status: "completed", retained_record_categories: ["financial evidence"] });
    expect((await deleteAccount("482951", "test-token")).deletion_status).toBe("completed");
    expect(fetchMock.mock.calls[0][1].cache).toBe("no-store");
    expect(fetchMock.mock.calls[0][1].redirect).toBe("error");
  });
  it("does not send unauthenticated deletion requests", async () => {
    await expect(deleteAccount("482951")).rejects.toThrow("Sign in");
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
describe("optional deletion and readiness", () => {
  it("requires the separate optional-data success contract", async () => {
    respond(200, { deletion_status: "data_deleted" });
    await deleteOptionalAccountData("482951", ["financial_planning"], "test-token");
    expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ pin: "482951", confirmation: "DELETE_DATA", data_categories: ["financial_planning"] });
    respond(200, { deletion_status: "completed" });
    await expect(deleteOptionalAccountData("482951", ["financial_planning"], "test-token")).rejects.toThrow("not confirmed");
  });
  it("rejects empty categories before making a write", async () => {
    await expect(deleteOptionalAccountData("482951", [], "test-token")).rejects.toThrow("Select");
    expect(fetchMock).not.toHaveBeenCalled();
  });
  it("fails closed on inconsistent readiness", async () => {
    respond(200, { can_delete_account: true, active_obligations: [blocker], data_categories: [] });
    await expect(getDeletionReadiness("test-token")).rejects.toThrow("not confirmed");
  });
  it("loads authenticated current readiness without leaking credentials in URLs", async () => {
    respond(200, { can_delete_account: false, active_obligations: [blocker], data_categories: [{ code: "location_context", label: "Saved location", description: "Optional" }], retained_record_categories: [] });
    const result = await getDeletionReadiness("test-token");
    expect(result.can_delete_account).toBe(false);
    expect(fetchMock.mock.calls[0][0]).toBe("https://example.test/api/account/deletion-readiness");
    expect(fetchMock.mock.calls[0][1].headers.Authorization).toBe("Bearer test-token");
  });
});
''')
print('Web account-deletion parity, selective deletion and contract regressions prepared.')
