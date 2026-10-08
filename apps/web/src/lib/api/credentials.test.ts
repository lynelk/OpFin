import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { changePassword, identityFields, normalisePhone, requestResetCode, resetPassword } from "./credentials";

const fetchMock = vi.fn();
function respond(status: number, payload: unknown) {
  fetchMock.mockResolvedValueOnce(new Response(JSON.stringify(payload), { status, headers: { "Content-Type": "application/json" } }));
}
function sentBody(call = 0): Record<string, unknown> {
  return JSON.parse(String(fetchMock.mock.calls[call][1].body)) as Record<string, unknown>;
}
beforeEach(() => { vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "https://example.test/api"); vi.stubGlobal("fetch", fetchMock); fetchMock.mockReset(); });
afterEach(() => { vi.unstubAllGlobals(); vi.unstubAllEnvs(); });

describe("sign-in identity", () => {
  it.each([["+256 700 000 001", "256700000001"], ["0700000001", "256700000001"], ["256700000001", "256700000001"]])(
    "normalises %s like the mobile app", (raw, expected) => expect(normalisePhone(raw)).toBe(expected));
  it("treats an address with @ as a staff email and lower-cases it", () => {
    expect(identityFields(" Owner@Example.test ")).toEqual({ email: "owner@example.test" });
    expect(identityFields("0700000001")).toEqual({ phone: "256700000001" });
  });
});

describe("password recovery", () => {
  it("asks for an email code for an email and an SMS code for a phone", async () => {
    respond(200, { success: true, data: {} });
    respond(200, { success: true, data: {} });
    await requestResetCode("owner@example.test");
    await requestResetCode("0700000001");
    expect(sentBody(0)).toEqual({ channel: "email", email: "owner@example.test" });
    expect(sentBody(1)).toEqual({ channel: "sms", phone: "256700000001" });
  });
  it("resets with the code and both password entries, without caching or following redirects", async () => {
    respond(200, { success: true, data: {} });
    await resetPassword("owner@example.test", " 123456 ", "Correct-Horse-42!", "Correct-Horse-42!");
    expect(sentBody()).toEqual({ email: "owner@example.test", otp: "123456", password: "Correct-Horse-42!", password_confirmation: "Correct-Horse-42!" });
    expect(fetchMock.mock.calls[0][1].cache).toBe("no-store");
    expect(fetchMock.mock.calls[0][1].redirect).toBe("error");
  });
  it("surfaces the plain-language validation message", async () => {
    respond(422, { success: false, message: "Validation failed.", errors: { password: ["The password field must be at least 12 characters."] } });
    await expect(changePassword("token", "Temp@2468", "short", "short")).rejects.toMatchObject({ kind: "validation", errors: { password: ["The password field must be at least 12 characters."] } });
    expect(fetchMock.mock.calls[0][1].headers.Authorization).toBe("Bearer token");
  });
  it("requires a session to change a password", async () => {
    await expect(changePassword(undefined, "a", "b", "b")).rejects.toMatchObject({ kind: "unauthorized" });
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
