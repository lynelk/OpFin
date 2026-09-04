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

const SESSION_MAX_AGE_SECONDS = 60 * 60 * 8;
const SESSION_COOKIE_OPTIONS = {
  httpOnly: true,
  sameSite: "lax" as const,
  secure: process.env.NODE_ENV === "production",
  path: "/",
  maxAge: SESSION_MAX_AGE_SECONDS
};

function safeInternalPath(path: string | null): string {
  if (!path || !path.startsWith("/") || path.startsWith("//") || path.includes("\\") || path.includes("\n") || path.includes("\r")) {
    return "/dashboard";
  }

  return path;
}

export function GET(request: NextRequest) {
  if (process.env.NODE_ENV === "production" || process.env.OPFIN_ENABLE_DEMO_SHORTCUTS !== "true") {
    return NextResponse.json({ message: "Sandbox login shortcuts are disabled." }, { status: 404 });
  }

  const role = request.nextUrl.searchParams.get("role") as UserRole | null;
  const safeRole = allowedRoles.includes(role ?? "customer") ? role ?? "customer" : "customer";
  const next = safeInternalPath(request.nextUrl.searchParams.get("next"));
  const response = NextResponse.redirect(new URL(next, request.url));

  response.cookies.set("opfin_access_token", `sandbox-${crypto.randomUUID()}`, SESSION_COOKIE_OPTIONS);
  response.cookies.set("opfin_role", safeRole, SESSION_COOKIE_OPTIONS);
  response.cookies.set("opfin_name", encodeURIComponent(roleNames[safeRole]), SESSION_COOKIE_OPTIONS);

  return response;
}
