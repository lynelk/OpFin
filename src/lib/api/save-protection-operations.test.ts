import { afterEach, describe, expect, it, vi } from "vitest";

async function loadMockApi() {
  vi.resetModules();
  vi.stubEnv("NEXT_PUBLIC_USE_MOCK_API", "true");
  vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "");
  return import("./save-protection-operations");
}

describe("Save & Protection operations API client", () => {
  afterEach(() => {
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it("exposes a structured operations queue in sandbox mode", async () => {
    const { saveProtectionOperationsApi } = await loadMockApi();
    const response = await saveProtectionOperationsApi.workQueue();

    expect(response.data.scope).toBe("institution");
    expect(response.data.counts.savings_contributions).toBe(0);
    expect(response.data.protection_claims).toEqual([]);
  });

  it("creates product drafts rather than silently activating them", async () => {
    const { saveProtectionOperationsApi } = await loadMockApi();
    const savings = await saveProtectionOperationsApi.createSavingsProduct({
      code: "SAVE-TEST",
      name: "Test Savings",
      partner_name: "Regulated Partner",
      partner_product_reference: "PARTNER-SAVE-TEST",
      country_code: "UG",
      currency: "UGX",
      product_type: "goal",
      custody_model: "partner_held",
      terms_version: "v1",
      terms_url: "https://example.test/save",
      disclosures: ["Partner held"]
    });

    expect(savings.data.product.status).toBe("draft");
    expect(savings.data.product.custody_model).toBe("partner_held");

    const protection = await saveProtectionOperationsApi.createProtectionProduct({
      code: "PROTECT-TEST",
      name: "Test Protection",
      insurer_name: "Regulated Insurer",
      country_code: "UG",
      currency: "UGX",
      product_type: "health",
      premium_amount_minor: 10000,
      premium_frequency: "monthly",
      disclosure_version: "v1",
      benefits: ["Test benefit"],
      exclusions: ["Test exclusion"],
      disclosure_payload: { decision_authority: "insurer_or_underwriter" },
      terms_url: "https://example.test/protect"
    });

    expect(protection.data.product.status).toBe("draft");
    expect(protection.data.product.insurer_name).toBe("Regulated Insurer");
  });

  it("calls the institution-scoped work queue with bearer authentication in live mode", async () => {
    vi.resetModules();
    vi.stubEnv("NEXT_PUBLIC_USE_MOCK_API", "false");
    vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "https://api.example.test/api");

    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: "Save & Protection operations work queue loaded.",
        data: {
          counts: {
            savings_contributions: 0,
            savings_withdrawals: 0,
            protection_premiums: 0,
            protection_policies: 0,
            protection_claims: 0
          },
          savings_contributions: [],
          savings_withdrawals: [],
          protection_premiums: [],
          protection_policies: [],
          protection_claims: [],
          scope: "institution",
          institution_id: 7
        }
      })
    });
    vi.stubGlobal("fetch", fetchMock);

    const { saveProtectionOperationsApi } = await import("./save-protection-operations");
    await saveProtectionOperationsApi.workQueue("ops-token");

    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("https://api.example.test/api/admin/save-protection/work-queue");
    expect(init.headers.Authorization).toBe("Bearer ops-token");
    expect(init.cache).toBe("no-store");
  });
});
