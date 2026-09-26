/** Presentation and input guards only. All financial calculations remain server-authoritative. */
export const tabs = ["overview", "imports", "cases", "statements", "reports", "access", "network", "compare", "scenario"] as const;
export type IntelligenceTab = (typeof tabs)[number];
export type Permission = "overview" | "source" | "import" | "publish" | "detail" | "case" | "report" | "grant" | "statement" | "share";
export const tabPermissions: Record<IntelligenceTab, Permission> = {
  overview: "overview", imports: "detail", cases: "case", statements: "statement", reports: "report",
  access: "grant", network: "report", compare: "overview", scenario: "overview"
};
export function positiveId(value: unknown): number {
  if (typeof value !== "string" || !/^[1-9][0-9]{0,14}$/.test(value)) throw new Error("Choose a valid record identifier.");
  const id = Number(value);
  if (!Number.isSafeInteger(id)) throw new Error("The record identifier is out of range.");
  return id;
}
export function integerInput(value: string, min = 0, max = 900000000000000): number {
  if (!/^-?(0|[1-9][0-9]*)$/.test(value)) throw new Error("Use an exact whole number without commas or decimals.");
  const parsed = Number(value);
  if (!Number.isSafeInteger(parsed) || parsed < min || parsed > max) throw new Error("The number is outside the accepted range.");
  return parsed;
}
export function selectedTab(value: unknown, permissions: readonly string[]): IntelligenceTab {
  if (typeof value === "string" && tabs.includes(value as IntelligenceTab) && permissions.includes(tabPermissions[value as IntelligenceTab])) return value as IntelligenceTab;
  return tabs.find(tab => permissions.includes(tabPermissions[tab])) ?? "overview";
}
export function displayMetric(key: string, value: number | null | undefined): string {
  if (value == null || !Number.isSafeInteger(value)) return "Not available";
  if (key.endsWith("_bps")) return new Intl.NumberFormat("en-GB", { maximumFractionDigits: 2 }).format(value / 100) + "%";
  return new Intl.NumberFormat("en-GB", { maximumFractionDigits: 0 }).format(value);
}
export function label(key: string): string {
  return key.replaceAll("_", " ").replace(/\bpar(\d+)/g, "PAR$1").replace(/\bnpl\b/g, "NPL").replace(/\bbps\b/g, "ratio");
}
export function destination(spaceId: number, tab: IntelligenceTab, params: Record<string, string | number> = {}): string {
  positiveId(String(spaceId));
  const query = new URLSearchParams({ tab });
  for (const [key, value] of Object.entries(params)) query.set(key, String(value));
  return `/spaces/${spaceId}/intelligence?${query.toString()}`;
}
export const errorMessages: Record<string, string> = {
  validation: "The request could not be accepted. Check the required fields, source totals, dates and whole-number amounts. No rejected import was published.",
  forbidden: "Your current permission does not allow this action. Access may have expired or been withdrawn.",
  conflict: "This record changed or the submission key was already used. Reload the latest record before continuing.",
  unavailable: "Financial Intelligence or its processing service is not available. No successful processing is being assumed.",
  unauthenticated: "Sign in again before continuing.",
  missing: "The requested record is not available in this Financial Space.",
  server: "The request did not return a confirmed result. Refresh the record before retrying the same instruction."
};
