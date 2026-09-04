export function formatUgx(value: number | string | undefined): string {
  const amount = Number(value ?? 0);

  return new Intl.NumberFormat("en-UG", {
    style: "currency",
    currency: "UGX",
    maximumFractionDigits: 0
  }).format(amount);
}

export function maskSensitiveId(value: string | null | undefined): string {
  if (!value) {
    return "Not available";
  }

  if (value.length <= 4) {
    return "****";
  }

  return `${value.slice(0, 2)}${"*".repeat(Math.max(4, value.length - 4))}${value.slice(-2)}`;
}
