import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
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
    respond(200, { can_delete_account: true, active_obligations: [blocker], data_deletion: { available_categories: [] } });
    await expect(getDeletionReadiness("test-token")).rejects.toThrow("not confirmed");
  });
  it("loads authenticated current readiness without leaking credentials in URLs", async () => {
    respond(200, { can_delete_account: false, active_obligations: [blocker], data_deletion: { available_categories: [{ code: "location_context", label: "Saved location", description: "Optional" }], retained_record_categories: [] } });
    const result = await getDeletionReadiness("test-token");
    expect(result.can_delete_account).toBe(false);
    expect(fetchMock.mock.calls[0][0]).toBe("https://example.test/api/account/deletion-readiness");
    expect(fetchMock.mock.calls[0][1].headers.Authorization).toBe("Bearer test-token");
  });
});
