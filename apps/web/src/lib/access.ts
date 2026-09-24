import { navigationItems } from "./navigation";
import type { UserRole } from "./types";

export const USER_ROLES: readonly UserRole[] = [
  "customer",
  "platform_admin",
  "operations",
  "support",
  "employer_admin",
  "programme_partner"
];

const roleHomes: Record<UserRole, string> = {
  customer: "/dashboard",
  platform_admin: "/admin/dashboard",
  operations: "/admin/dashboard",
  support: "/admin/support",
  employer_admin: "/employer",
  programme_partner: "/partner/impact"
};

export const protectedPortalPrefixes = [
  "/admin",
  "/asset-finance",
  "/borrow",
  "/calendar",
  "/community-finance",
  "/connected-accounts",
  "/consent",
  "/credit-builder",
  "/dashboard",
  "/ecosystem",
  "/employer",
  "/financial-passport",
  "/grow",
  "/hardship",
  "/household-finance",
  "/insurance",
  "/investments",
  "/kyc",
  "/loans",
  "/microbusiness",
  "/money",
  "/money-autopilot",
  "/more",
  "/participatory-finance",
  "/partner",
  "/payment-status",
  "/peer-lending",
  "/programme",
  "/referrals",
  "/save",
  "/savings",
  "/security",
  "/setup",
  "/spaces",
  "/support",
  "/whatsapp"
] as const;

function pathnameOnly(path: string): string {
  return path.split(/[?#]/, 1)[0] || "/";
}

function matchesPrefix(path: string, prefix: string): boolean {
  return path === prefix || path.startsWith(prefix + "/");
}

function rolesForAdminPath(pathname: string): UserRole[] | undefined {
  const match = navigationItems
    .filter((item) => item.group === "admin" && matchesPrefix(pathname, item.href))
    .sort((left, right) => right.href.length - left.href.length)[0];

  return match?.roles;
}

export function isUserRole(value: string | undefined): value is UserRole {
  return USER_ROLES.includes(value as UserRole);
}

export function homeForRole(role: UserRole): string {
  return roleHomes[role];
}

export function isProtectedPortalPath(path: string): boolean {
  const pathname = pathnameOnly(path);
  return protectedPortalPrefixes.some((prefix) => matchesPrefix(pathname, prefix));
}

export function canRoleOpenPath(role: UserRole, path: string): boolean {
  const pathname = pathnameOnly(path);

  if (matchesPrefix(pathname, "/account/delete")) {
    return role === "customer";
  }

  if (matchesPrefix(pathname, "/admin")) {
    const explicitRoles = rolesForAdminPath(pathname);
    if (explicitRoles) return explicitRoles.includes(role);

    // Unlisted operational modules remain staff-only, but Support does not
    // inherit new modules merely because they happen to sit under /admin.
    return role === "platform_admin" || role === "operations";
  }

  if (matchesPrefix(pathname, "/employer")) {
    return role === "platform_admin" || role === "employer_admin";
  }

  if (matchesPrefix(pathname, "/partner")) {
    return role === "programme_partner";
  }

  if (isProtectedPortalPath(pathname)) {
    return role === "customer" || role === "platform_admin";
  }

  return true;
}
