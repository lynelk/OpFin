import type { UserRole } from "./types";

export type NavItem = {
  href: string;
  label: string;
  group: "customer" | "admin" | "employer" | "wealth";
  roles?: UserRole[];
};

export const navigationItems: NavItem[] = [
  { href: "/dashboard", label: "Customer dashboard", group: "customer" },
  { href: "/kyc", label: "KYC status", group: "customer" },
  { href: "/consent", label: "Consent management", group: "customer" },
  { href: "/loans/apply", label: "Loan application", group: "customer" },
  { href: "/loans/decision", label: "Decision result", group: "customer" },
  { href: "/loans/offer", label: "Loan offer", group: "customer" },
  { href: "/loans/schedule", label: "Repayment schedule", group: "customer" },
  { href: "/loans/account", label: "Loan account", group: "customer" },
  { href: "/savings", label: "Savings", group: "wealth" },
  { href: "/insurance", label: "Insurance", group: "wealth" },
  { href: "/investments", label: "Investments", group: "wealth" },
  { href: "/admin/dashboard", label: "Admin dashboard", group: "admin", roles: ["platform_admin", "operations", "support"] },
  { href: "/admin/credit-review", label: "Credit review", group: "admin", roles: ["platform_admin", "operations"] },
  { href: "/admin/reconciliation", label: "Reconciliation", group: "admin", roles: ["platform_admin", "operations"] },
  { href: "/admin/ledger", label: "Ledger", group: "admin", roles: ["platform_admin", "operations", "support"] },
  { href: "/admin/support", label: "Support cases", group: "admin", roles: ["platform_admin", "operations", "support"] },
  { href: "/admin/compliance", label: "Compliance reports", group: "admin", roles: ["platform_admin", "operations"] },
  { href: "/admin/audit-trail", label: "Audit trail", group: "admin", roles: ["platform_admin", "operations", "support"] },
  { href: "/employer", label: "Employer portal", group: "employer", roles: ["platform_admin", "employer_admin"] }
];
