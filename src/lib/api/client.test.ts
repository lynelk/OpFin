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
});
