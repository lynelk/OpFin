import { describe, expect, it } from "vitest";
import { destination, displayMetric, integerInput, positiveId, selectedTab } from "./presentation";

describe("Financial Intelligence presentation boundaries", () => {
  it("preserves unknown versus zero", () => {
    expect(displayMetric("par30_bps", null)).toBe("Not available");
    expect(displayMetric("par30_bps", 0)).toBe("0%");
    expect(displayMetric("principal_minor", 0)).toBe("0");
  });
  it("does not present unsafe integers as reliable amounts", () => {
    expect(displayMetric("principal_minor", Number.MAX_SAFE_INTEGER + 1)).toBe("Not available");
  });
  it("formats ratio basis points without performing risk decisions", () => {
    expect(displayMetric("par30_bps", 1234)).toBe("12.34%");
  });
  it("rejects malformed, fractional and path-injection identifiers", () => {
    for (const value of ["0", "-1", "1.2", "1e3", "01", "1/../../2", "999999999999999999", undefined]) expect(() => positiveId(value)).toThrow();
    expect(positiveId("123")).toBe(123);
  });
  it("keeps input money exact", () => {
    for (const value of ["1,000", "1.5", "1e3", "", "01", "900000000000001"]) expect(() => integerInput(value)).toThrow();
    expect(integerInput("900000000000000")).toBe(900000000000000);
    expect(integerInput("-12", -100)).toBe(-12);
  });
  it("routes collection-only members to assigned cases", () => {
    expect(selectedTab(undefined, ["case"])).toBe("cases");
    expect(selectedTab("overview", ["case"])).toBe("cases");
  });
  it("routes personal owners only to statement analysis", () => {
    expect(selectedTab("imports", ["statement"])).toBe("statements");
  });
  it("does not grant borrower-detail navigation to board members", () => {
    expect(selectedTab("imports", ["overview", "report"])).toBe("overview");
  });
  it("uses an institution-bound relative destination", () => {
    expect(destination(7, "cases", { case: 12 })).toBe("/spaces/7/intelligence?tab=cases&case=12");
    expect(() => destination(-1, "overview")).toThrow();
  });
});
