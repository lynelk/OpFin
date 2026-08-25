import { describe, expect, it } from "vitest";
import { navigationItems } from "./navigation";

describe("customer navigation", () => {
  it("exposes exactly the five customer journeys", () => {
    const customerItems = navigationItems.filter((item) => item.group === "customer");

    expect(customerItems.map((item) => item.label)).toEqual([
      "Home",
      "Borrow",
      "Save",
      "Grow",
      "More"
    ]);
  });

  it("does not expose lifecycle states as permanent navigation", () => {
    const labels = navigationItems.map((item) => item.label.toLowerCase());

    expect(labels).not.toContain("kyc status");
    expect(labels).not.toContain("decision result");
    expect(labels).not.toContain("loan offer");
    expect(labels).not.toContain("repayment schedule");
  });
});
