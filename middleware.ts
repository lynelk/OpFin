import { NextResponse, type NextRequest } from "next/server";

const protectedPrefixes = [
  "/dashboard",
  "/kyc",
  "/consent",
  "/loans",
  "/admin/dashboard",
  "/admin/credit-review",
  "/admin/audit-trail",
  "/employer",
  "/savings",
  "/insurance",
  "/investments"
];

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const isProtected = protectedPrefixes.some((prefix) => pathname.startsWith(prefix));

  if (!isProtected) {
    return NextResponse.next();
  }

  const hasSession = request.cookies.has("opfin_access_token");
  if (!hasSession) {
    const loginUrl = new URL("/login", request.url);
    loginUrl.searchParams.set("next", pathname);
    return NextResponse.redirect(loginUrl);
  }

  const role = request.cookies.get("opfin_role")?.value;
  if (pathname.startsWith("/admin") && !["platform_admin", "operations", "support"].includes(role ?? "")) {
    return NextResponse.redirect(new URL("/dashboard", request.url));
  }

  if (pathname.startsWith("/employer") && !["platform_admin", "employer_admin"].includes(role ?? "")) {
    return NextResponse.redirect(new URL("/dashboard", request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    "/dashboard/:path*",
    "/kyc/:path*",
    "/consent/:path*",
    "/loans/:path*",
    "/admin/:path*",
    "/employer/:path*",
    "/savings/:path*",
    "/insurance/:path*",
    "/investments/:path*"
  ]
};
