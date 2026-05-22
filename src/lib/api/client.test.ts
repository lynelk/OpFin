import { classifyStatus, OpfinApiError } from "./errors";
import { afterEach, describe, expect, it, vi } from "vitest";

async function loadMockApi() {
  vi.resetModules();
  vi.stubEnv("NEXT_PUBLIC_USE_MOCK_API", "true");
  vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "");
  return import("./client");
}

describe("OpFin API client", () => {
  afterEach(() => {
    vi.unstubAllEnvs();
  });

  it("classifies documented HTTP error states", () => {
    expect(classifyStatus(401)).toBe("unauthorized");
    expect(classifyStatus(403)).toBe("forbidden");
    expect(classifyStatus(422)).toBe("validation");
    expect(classifyStatus(500)).toBe("server");
  });

  it("preserves validation errors for display", () => {
    const error = new OpfinApiError("validation", "Validation failed", 422, {
      amount: ["The amount field is required."]
    });

    expect(error.errors.amount[0]).toContain("amount");
  });

  it("returns mock profile data when mock API mode is enabled", async () => {
    const { opfinApi } = await loadMockApi();
    const profile = await opfinApi.profile();

    expect(profile.success).toBe(true);
    expect(profile.data.user.role).toBe("customer");
  });

  it("returns sandbox admin review updates without colliding with loan application list mocks", async () => {
    const { opfinApi } = await loadMockApi();
    const response = await opfinApi.updateLoanApplicationStatus(101, "Approved");

    expect(response.success).toBe(true);
    expect(response.data.status).toBe("Approved");
  });

  it("keeps repayment schedules sandboxed until a backend route is documented", async () => {
    const { opfinApi } = await loadMockApi();
    const response = await opfinApi.repaymentSchedule();

    expect(response.message).toContain("Sandbox repayment schedule");
    expect(response.data.length).toBeGreaterThan(0);
  });

  it("uses production KYC and consent contracts in mock mode", async () => {
    const { opfinApi } = await loadMockApi();

    const kyc = await opfinApi.kycStatus();
    expect(kyc.data.latest_case?.status).toBe("pending_review");

    const submitted = await opfinApi.submitKycCase({ national_id: "CM1234567890" });
    expect(submitted.data.kyc_case.national_id).toBe("CM1234567890");

    const granted = await opfinApi.grantConsent({
      purpose: "credit_processing",
      policy_version: "credit-consent-v1"
    });
    expect(granted.data.consent.status).toBe("granted");

    const revoked = await opfinApi.revokeConsent(granted.data.consent.id);
    expect(revoked.data.consent.status).toBe("revoked");
  });

  it("runs the mock investor demo flow through consent, application, offer, and admin snapshot contracts", async () => {
    const { opfinApi } = await loadMockApi();
    const consent = await opfinApi.grantDemoConsent();
    expect(consent.data.status).toBe("granted");

    const application = await opfinApi.submitDemoLoanApplication({
      loan_product_id: 1,
      loan_product_term_id: 1,
      institution_id: 1,
      amount: 250000,
      reason: "Investor demo school fees"
    });

    expect(application.data.decision.status).toBe("approved");
    expect(application.data.decision.reason_codes).toContain("MOCK_AFFORDABILITY_CHECK");
    expect(application.data.offer?.status).toBe("pending_acceptance");

    const acceptance = await opfinApi.acceptDemoOffer(application.data.offer!.id);
    expect(acceptance.data.loan.status).toBe("Disbursed");
    expect(acceptance.data.mobile_money.provider).toBe("mock");

    const snapshot = await opfinApi.investorDemoAdminSnapshot();
    expect(snapshot.data.audit_trail.length).toBeGreaterThan(0);
    expect(snapshot.data.ledger_entries.length).toBeGreaterThan(0);
  });

  it("fails closed when API base URL is missing and mock mode is not explicitly enabled", async () => {
    vi.resetModules();
    vi.stubEnv("NEXT_PUBLIC_USE_MOCK_API", "false");
    vi.stubEnv("NEXT_PUBLIC_OPFIN_API_URL", "");
    const { opfinApi } = await import("./client");

    await expect(opfinApi.profile()).rejects.toMatchObject({
      kind: "server",
      message: expect.stringContaining("API base URL is not configured")
    });
  });
});
