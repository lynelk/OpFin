import { cookies } from "next/headers";
import type { Session, UserRole } from "../types";

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

  return {
    role,
    name: roleNames[role] ?? roleNames.customer
  };
}

export function canSeeGroup(role: UserRole, group: "customer" | "admin" | "employer" | "wealth"): boolean {
  if (group === "admin") return ["platform_admin", "operations", "support"].includes(role);
  if (group === "employer") return role === "employer_admin" || role === "platform_admin";
  return true;
}
