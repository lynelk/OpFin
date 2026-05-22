import { NextResponse, type NextRequest } from "next/server";
import type { UserRole } from "@/lib/types";

const allowedRoles: UserRole[] = ["customer", "platform_admin", "operations", "support", "employer_admin"];
const roleNames: Record<UserRole, string> = {
  customer: "Demo Customer",
  platform_admin: "Platform Admin",
  operations: "Operations User",
  support: "Support User",
  employer_admin: "Employer Admin"
};

export function GET(request: NextRequest) {
  if (process.env.NODE_ENV === "production" || process.env.OPFIN_ENABLE_DEMO_SHORTCUTS !== "true") {
    return NextResponse.json({ message: "Sandbox login shortcuts are disabled." }, { status: 404 });
  }

  const role = request.nextUrl.searchParams.get("role") as UserRole | null;
  const safeRole = allowedRoles.includes(role ?? "customer") ? role ?? "customer" : "customer";
  const next = request.nextUrl.searchParams.get("next") ?? "/dashboard";
  const response = NextResponse.redirect(new URL(next, request.url));

  response.cookies.set("opfin_access_token", `sandbox-${crypto.randomUUID()}`, {
    httpOnly: true,
    sameSite: "lax",
    path: "/"
  });
  response.cookies.set("opfin_role", safeRole, {
    httpOnly: true,
    sameSite: "lax",
    path: "/"
  });
  response.cookies.set("opfin_name", encodeURIComponent(roleNames[safeRole]), {
    httpOnly: true,
    sameSite: "lax",
    path: "/"
  });

  return response;
}
