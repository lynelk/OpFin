import type { UserRole } from "./types";

export type NavGroup = "customer" | "admin" | "employer" | "partner";

export type NavItem = {
  href: string;
  label: string;
  group: NavGroup;
  section?: string;
  roles?: UserRole[];
};

export const navigationItems: NavItem[] = [
  // Customer navigation remains journey-based. Extended capabilities stay under More so the
  // core five destinations do not expand every time the platform gains another rail or product.
  { href: "/dashboard", label: "Home", group: "customer" },
  { href: "/borrow", label: "Borrow", group: "customer" },
  { href: "/save", label: "Save", group: "customer" },
  { href: "/grow", label: "Grow", group: "customer" },
  { href: "/more", label: "More", group: "customer" },

  { href: "/admin/dashboard", label: "Operations overview", group: "admin", section: "Overview & automation", roles: ["platform_admin", "operations", "support"] },
  { href: "/admin/autopilot", label: "Platform Autopilot", group: "admin", section: "Overview & automation", roles: ["platform_admin", "operations"] },

  { href: "/admin/long-range", label: "Extended finance", group: "admin", section: "Finance & risk", roles: ["platform_admin", "operations"] },
  { href: "/admin/lending-platform", label: "Lenders & credit deployment", group: "admin", section: "Finance & risk", roles: ["platform_admin", "operations"] },
  { href: "/admin/essentials", label: "Essentials operations", group: "admin", section: "Finance & risk", roles: ["platform_admin", "operations"] },
  { href: "/admin/credit-review", label: "Credit review", group: "admin", section: "Finance & risk", roles: ["platform_admin", "operations"] },
  { href: "/admin/save-protection", label: "Save & Protection", group: "admin", section: "Finance & risk", roles: ["platform_admin", "operations"] },
  { href: "/admin/reconciliation", label: "Reconciliation", group: "admin", section: "Finance & risk", roles: ["platform_admin", "operations"] },
  { href: "/admin/ledger", label: "Ledger", group: "admin", section: "Finance & risk", roles: ["platform_admin", "operations", "support"] },

  { href: "/admin/support", label: "Support cases", group: "admin", section: "Customer service", roles: ["platform_admin", "operations", "support"] },

  { href: "/admin/platform-governance", label: "Platform governance", group: "admin", section: "Governance & assurance", roles: ["platform_admin", "operations"] },
  { href: "/admin/compliance", label: "Compliance reports", group: "admin", section: "Governance & assurance", roles: ["platform_admin", "operations"] },
  { href: "/admin/location-insights", label: "Location insights", group: "admin", section: "Governance & assurance", roles: ["platform_admin", "operations"] },
  { href: "/admin/audit-trail", label: "Audit trail", group: "admin", section: "Governance & assurance", roles: ["platform_admin", "operations", "support"] },

  { href: "/admin/inclusion", label: "Inclusion & programmes", group: "admin", section: "Programmes & growth", roles: ["platform_admin", "operations"] },
  { href: "/admin/inclusion/delivery", label: "Programme delivery", group: "admin", section: "Programmes & growth", roles: ["platform_admin", "operations"] },
  { href: "/admin/commercial", label: "Commercial performance", group: "admin", section: "Programmes & growth", roles: ["platform_admin", "operations"] },

  { href: "/employer", label: "OpFin Work", group: "employer", roles: ["platform_admin", "employer_admin"] },
  { href: "/partner/impact", label: "Programme impact", group: "partner", roles: ["programme_partner"] },
  { href: "/partner/location", label: "Service network", group: "partner", roles: ["programme_partner"] }
];
