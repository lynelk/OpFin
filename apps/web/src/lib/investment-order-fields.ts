/**
 * Validate exactly what the person submitted before forwarding an investment request.
 * Browser checkbox requirements are not a substitute for server-side consent.
 */
export function investmentOrderFields(form: FormData): { productId: number; amountMinor: number } {
  if (form.get("disclosure_acknowledged") !== "yes") {
    throw new Error("Review and acknowledge the product disclosures before submitting an investment order.");
  }
  const rawProduct = form.get("product_id");
  const rawAmount = form.get("amount_minor");
  const productId = typeof rawProduct === "string" && rawProduct.trim() !== "" ? Number(rawProduct) : NaN;
  const amountMinor = typeof rawAmount === "string" && /^\d+$/.test(rawAmount) ? Number(rawAmount) : NaN;
  if (!Number.isSafeInteger(productId) || productId <= 0) {
    throw new Error("Select a valid investment product.");
  }
  if (!Number.isSafeInteger(amountMinor) || amountMinor <= 0) {
    throw new Error("Enter a positive whole-number investment amount in UGX minor units.");
  }
  return { productId, amountMinor };
}
