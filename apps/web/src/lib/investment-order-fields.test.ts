import { describe, expect, it } from "vitest";
import { investmentOrderFields } from "./investment-order-fields";

function request(amount: string, acknowledgement: string | null = "yes") {
  const data = new FormData();
  data.set("product_id", "4");
  data.set("amount_minor", amount);
  if (acknowledgement) data.set("disclosure_acknowledged", acknowledgement);
  return data;
}
describe("investment order fields", () => {
  it("requires explicit acknowledgement on the submitted form", () => {
    expect(() => investmentOrderFields(request("100000", null))).toThrow("acknowledge");
    expect(() => investmentOrderFields(request("100000", "no"))).toThrow("acknowledge");
  });
  it("accepts a precise positive UGX minor-unit amount", () => {
    expect(investmentOrderFields(request("100000"))).toEqual({ productId: 4, amountMinor: 100000 });
  });
  it("rejects decimals, infinity, negative and unsafe large quantities", () => {
    for (const invalid of ["", "0", "-1", "1.5", "1e6", "9999999999999999999999"]) {
      expect(() => investmentOrderFields(request(invalid))).toThrow();
    }
  });
  it("rejects missing or malformed product references", () => {
    const data = request("1000"); data.set("product_id", "abc");
    expect(() => investmentOrderFields(data)).toThrow("valid investment product");
  });
});
