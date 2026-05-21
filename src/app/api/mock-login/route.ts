import { NextResponse, type NextRequest } from "next/server";
import type { UserRole } from "@/lib/types";

const allowedRoles: UserRole[] = ["customer", "platform_admin", "operations", "support", "employer_admin"];

export function GET(request: NextRequest) {
  const role = request.nextUrl.searchParams.get("role") as UserRole | null;
  const next = request.nextUrl.searchParams.get("next") ?? "/dashboard";
  const response = NextResponse.redirect(new URL(next, request.url));

  response.cookies.set("opfin_role", allowedRoles.includes(role ?? "customer") ? role ?? "customer" : "customer", {
    httpOnly: true,
    sameSite: "lax",
    path: "/"
  });

  return response;
}
