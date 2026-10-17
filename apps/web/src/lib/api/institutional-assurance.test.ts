import { afterEach, describe, expect, it, vi } from "vitest";
import { assuranceRequest } from "./institutional-assurance";

describe("institutional financial assurance", () => {
  afterEach(() => {
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it("does not request financial records without a bearer token", async () => {
    vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "https://api.example.test/api");
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);
    await expect(assuranceRequest("?institution_id=14")).rejects.toMatchObject({ kind: "unauthorized" });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("uses institution-scoped URL and disables response caching", async () => {
    vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "https://api.example.test/api");
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true, json: async () => ({ data: { reconciliations: [], shared_origination_enabled: false } })
    });
    vi.stubGlobal("fetch", fetchMock);
    const result = await assuranceRequest<{shared_origination_enabled: boolean}>("?institution_id=14", "qa-token");
    expect(result.shared_origination_enabled).toBe(false);
    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe("https://api.example.test/api/admin/institutional-assurance?institution_id=14");
    expect(options.headers.Authorization).toBe("Bearer qa-token");
    expect(options.cache).toBe("no-store");
    expect(options.method).toBe("GET");
  });

  it("submits exact bank and lender declaration without altering minor-unit amounts", async () => {
    vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "https://api.example.test/api");
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true, json: async () => ({ data: { id: 3 } })
    });
    vi.stubGlobal("fetch", fetchMock);
    const payload = {
      institution_id: 14, ecl_run_id: 19, as_of_date: "2026-10-17", currency: "UGX",
      provider_statements: [{provider: "provider_a", balance_minor: 150123, statement_sha256: "a".repeat(64),
        statement_reference: "SOURCE-ORIGINAL-BANK-ID"}],
      external_payable_minor: 150123, external_principal_minor: 200125,
      external_loan_count: 1, lender_ledger_reference: "SIGNED-TRIAL-BALANCE-ID",
      lender_ledger_sha256: "b".repeat(64),
    };
    await assuranceRequest("/reconciliations", "qa-token", payload);
    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe("https://api.example.test/api/admin/institutional-assurance/reconciliations");
    expect(options.method).toBe("POST");
    expect(JSON.parse(options.body)).toEqual(payload);
  });

  it("surfaces 403 as an access failure, not an approved reviewer action", async () => {
    vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "https://api.example.test/api");
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false, status: 403, json: async () => ({ message: "Independent reviewer required" })
    });
    vi.stubGlobal("fetch", fetchMock);
    await expect(assuranceRequest("/reconciliations/3/approve", "qa-token", {}))
      .rejects.toMatchObject({ kind: "forbidden", status: 403 });
  });
});
