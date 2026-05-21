import { describe, expect, it } from "vitest";
import { opfinApi } from "./client";

describe("opfinApi mock client", () => {
  it("returns profile data from the mock profile contract", async () => {
    const profile = await opfinApi.profile();

    expect(profile.success).toBe(true);
    expect(profile.data.user.role).toBe("customer");
    expect(profile.data.permissions).toContain("profile.view");
  });

  it("returns known loan application fields without requiring missing contracts", async () => {
    const applications = await opfinApi.loanApplications(1);

    expect(applications.data[0]).toHaveProperty("amount");
    expect(applications.data[0]).toHaveProperty("status");
    expect(applications.data[0]).toHaveProperty("reason");
  });
});
