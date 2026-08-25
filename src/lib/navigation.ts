import type { UserRole } from "./types";

export type NavGroup = "customer" | "admin" | "employer";

export type NavItem = {
  href: string;
  label: string;
  group: NavGroup;
  roles?: UserRole[];
};

export const navigationItems: NavItem[] = [
  // Customer navigation is intentionally journey-based. KYC, consent, decision, offer and
  // repayment details remain reachable contextually but are not permanent top-level destinations.
  { href: "/dashboard", label: "Home", group: "customer" },
  { href: "/borrow", label: "Borrow", group: "customer" },
  { href: "/save", label: "Save", group: "customer" },
  { href: "/grow", label: "Grow", group: "customer" },
  { href: "/more", label: "More", group: "customer" },

  { href: "/admin/dashboard", label: "Operations overview", group: "admin", roles: ["platform_admin", "operations", "support"] },
  { href: "/admin/credit-review", label: "Credit review", group: "admin", roles: ["platform_admin", "operations"] },
  { href: "/admin/save-protection", label: "Save & Protection", group: "admin", roles: ["platform_admin", "operations"] },
  { href: "/admin/reconciliation", label: "Reconciliation", group: "admin", roles: ["platform_admin", "operations"] },
  { href: "/admin/ledger", label: "Ledger", group: "admin", roles: ["platform_admin", "operations", "support"] },
  { href: "/admin/support", label: "Support cases", group: "admin", roles: ["platform_admin", "operations", "support"] },
  { href: "/admin/compliance", label: "Compliance reports", group: "admin", roles: ["platform_admin", "operations"] },
  { href: "/admin/audit-trail", label: "Audit trail", group: "admin", roles: ["platform_admin", "operations", "support"] },

  { href: "/employer", label: "OpFin Work", group: "employer", roles: ["platform_admin", "employer_admin"] }
];
