import { afterEach, describe, expect, it, vi } from "vitest";
import { eclRequest } from "./institutional-ecl";

describe("institutional loan-loss provisioning API", () => {
  afterEach(() => {
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it("requires authentication before contacting the financial API", async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);
    vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "https://api.example.test/api");

    await expect(eclRequest("/runs/123")).rejects.toMatchObject({ kind: "unauthorized" });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("fails closed when the financial API endpoint is unconfigured", async () => {
    vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "");
    await expect(eclRequest("/runs/123", "test-token")).rejects.toMatchObject({ kind: "server" });
  });

  it("reads institution-scoped ECL information with bearer token and no caching", async () => {
    vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "https://api.example.test/api");
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: { policies: [], runs: [] } })
    });
    vi.stubGlobal("fetch", fetchMock);

    const result = await eclRequest<{ runs: unknown[] }>("?institution_id=14", "test-token");
    expect(result.runs).toEqual([]);
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("https://api.example.test/api/admin/institutional-ecl?institution_id=14");
    expect(init.method).toBe("GET");
    expect(init.headers.Authorization).toBe("Bearer test-token");
    expect(init.cache).toBe("no-store");
  });

  it("posts exact independently approved risk ownership and no unrelated client fields", async () => {
    vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "https://api.example.test/api");
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: { id: 19 } })
    });
    vi.stubGlobal("fetch", fetchMock);

    const payload = {
      institution_id: 14,
      credit_offer_id: 90,
      owner_type: "partner",
      opfin_share_bps: 0,
      agreement_reference: "SIGNED-PARTNER-AGREEMENT-2026",
    };
    await expect(eclRequest("/offer-ownerships", "test-token", payload)).resolves.toEqual({ id: 19 });
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("https://api.example.test/api/admin/institutional-ecl/offer-ownerships");
    expect(init.method).toBe("POST");
    expect(JSON.parse(init.body)).toEqual(payload);
  });

  it("preserves server 403 tenancy decisions and 422 review failures", async () => {
    vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "https://api.example.test/api");
    const fetchMock = vi.fn()
      .mockResolvedValueOnce({
        ok: false, status: 403,
        json: async () => ({ message: "Cannot access another institution's loan book." })
      })
      .mockResolvedValueOnce({
        ok: false, status: 422,
        json: async () => ({ message: "Independent approval required.", errors: { reviewer: ["Maker cannot approve."] } })
      });
    vi.stubGlobal("fetch", fetchMock);

    await expect(eclRequest("?institution_id=99", "test-token"))
      .rejects.toMatchObject({ kind: "forbidden", status: 403 });
    await expect(eclRequest("/runs/42/approve", "test-token", {}))
      .rejects.toMatchObject({ kind: "validation", status: 422, errors: { reviewer: ["Maker cannot approve."] } });
  });
});
