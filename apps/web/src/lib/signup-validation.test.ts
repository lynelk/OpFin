import { describe, expect, it } from "vitest";
import { normaliseUgandanPhone, validSignupPin } from "./signup-validation";

describe("customer registration validation", () => {
  it.each([
    ["0700123456", "256700123456"],
    ["+256700123456", "256700123456"],
    ["256700123456", "256700123456"],
    ["0700 123 456", "256700123456"]
  ])("normalises %s", (input, expected) => {
    expect(normaliseUgandanPhone(input)).toBe(expected);
  });
  it.each(["", "123", "+256", "070012345", "0800123456", "+15551234567"])("rejects %s", (input) => {
    expect(normaliseUgandanPhone(input)).toBeNull();
  });
  it("accepts matching non-trivial PIN", () => expect(validSignupPin("619284", "619284")).toBe(true));
  it.each(["111111", "123456", "654321", "99999", "abcdef"])("rejects weak PIN %s", pin => {
    expect(validSignupPin(pin, pin)).toBe(false);
  });
  it("rejects mismatched PINs", () => expect(validSignupPin("619284", "619285")).toBe(false));
});
