import { describe, expect, it } from "vitest";
import { classifyStatus, OpfinApiError } from "./errors";
import { opfinApi } from "./client";

describe("OpFin API client", () => {
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
    const profile = await opfinApi.profile();

    expect(profile.success).toBe(true);
    expect(profile.data.user.role).toBe("customer");
  });

  it("returns sandbox admin review updates without colliding with loan application list mocks", async () => {
    const response = await opfinApi.updateLoanApplicationStatus(101, "Approved");

    expect(response.success).toBe(true);
    expect(response.data.status).toBe("Approved");
  });

  it("keeps repayment schedules sandboxed until a backend route is documented", async () => {
    const response = await opfinApi.repaymentSchedule();

    expect(response.message).toContain("Sandbox repayment schedule");
    expect(response.data.length).toBeGreaterThan(0);
  });

  it("runs the mock investor demo flow through consent, application, offer, and admin snapshot contracts", async () => {
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
});
