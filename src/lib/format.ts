export function formatUgx(value: number | string | undefined): string {
  const amount = Number(value ?? 0);

  return new Intl.NumberFormat("en-UG", {
    style: "currency",
    currency: "UGX",
    maximumFractionDigits: 0
  }).format(amount);
}
