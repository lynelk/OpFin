import { cookies } from "next/headers";
import type { Session, UserRole } from "../types";
import type { NavGroup } from "../navigation";

const roleNames: Record<UserRole, string> = {
  customer: "Demo Customer",
  platform_admin: "Platform Admin",
  operations: "Operations User",
  support: "Support User",
  employer_admin: "Employer Admin"
};

export async function getCurrentSession(): Promise<Session> {
  const cookieStore = await cookies();
  const role = (cookieStore.get("opfin_role")?.value ?? "customer") as UserRole;
  const name = cookieStore.get("opfin_name")?.value;

  return {
    role,
    name: name ? decodeURIComponent(name) : roleNames[role] ?? roleNames.customer
  };
}

export async function getAccessToken(): Promise<string | undefined> {
  const cookieStore = await cookies();
  return cookieStore.get("opfin_access_token")?.value;
}

export function canSeeGroup(role: UserRole, group: NavGroup): boolean {
  if (group === "admin") return ["platform_admin", "operations", "support"].includes(role);
  if (group === "employer") return role === "employer_admin" || role === "platform_admin";
  return true;
}
