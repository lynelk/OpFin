import { afterEach, describe, expect, it, vi } from "vitest";

async function loadMockApi() {
  vi.resetModules();
  vi.stubEnv("NEXT_PUBLIC_USE_MOCK_API", "true");
  vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "");
  return import("./save-protection");
}

describe("Save & Protection API client", () => {
  afterEach(() => {
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it("keeps savings custody and payment confirmation states distinct in sandbox mode", async () => {
    const { saveProtectionApi } = await loadMockApi();
    const products = await saveProtectionApi.savingsProducts();

    expect(products.data.products[0].custody_model).toBe("partner_held");
    expect(products.data.custody_notice).toContain("not an OpFin stored-value wallet");

    const contribution = await saveProtectionApi.contributeSavings(201, {
      amount_minor: 50000,
      idempotency_key: "save-test-idempotency-001"
    });

    expect(contribution.data.movement.status).toBe("collection_pending");
    expect(contribution.data.position_state).toBe("not_yet_partner_confirmed");
  });

  it("keeps protection enrollment separate from insurer issuance", async () => {
    const { saveProtectionApi } = await loadMockApi();
    const products = await saveProtectionApi.protectionProducts();
    const product = products.data.products[0];

    expect(product.insurer_name).toContain("Insurer");
    expect(product.disclosure_hash).toHaveLength(64);
    expect(products.data.risk_notice).toContain("insurer or underwriter");

    const enrollment = await saveProtectionApi.enrollProtection(product.id, {
      accept_disclosures: true,
      disclosure_hash: product.disclosure_hash
    });

    expect(enrollment.data.policy.status).toBe("premium_due");
    expect(enrollment.data.policy.external_policy_number).toBeNull();
    expect(enrollment.data.next_state).toBe("premium_due");
  });

  it("uses bearer authentication and the documented savings endpoint in live mode", async () => {
    vi.resetModules();
    vi.stubEnv("NEXT_PUBLIC_USE_MOCK_API", "false");
    vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "https://api.example.test/api");

    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: "Savings products loaded.",
        data: { products: [], custody_notice: "Partner held." }
      })
    });
    vi.stubGlobal("fetch", fetchMock);

    const { saveProtectionApi } = await import("./save-protection");
    await saveProtectionApi.savingsProducts("UG", "test-token");

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("https://api.example.test/api/savings/products?country=UG");
    expect(init.headers.Authorization).toBe("Bearer test-token");
    expect(init.cache).toBe("no-store");
  });

  it("posts exact protection disclosure acceptance in live mode", async () => {
    vi.resetModules();
    vi.stubEnv("NEXT_PUBLIC_USE_MOCK_API", "false");
    vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "https://api.example.test/api");

    const disclosureHash = "a".repeat(64);
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: "Protection enrollment recorded.",
        data: {
          policy: { id: 44, status: "premium_due" },
          next_state: "premium_due"
        }
      })
    });
    vi.stubGlobal("fetch", fetchMock);

    const { saveProtectionApi } = await import("./save-protection");
    await saveProtectionApi.enrollProtection(9, {
      accept_disclosures: true,
      disclosure_hash: disclosureHash
    }, "test-token");

    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("https://api.example.test/api/protection/products/9/enroll");
    expect(init.method).toBe("POST");
    expect(JSON.parse(init.body)).toEqual({
      accept_disclosures: true,
      disclosure_hash: disclosureHash
    });
  });
});
