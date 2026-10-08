import { NextResponse, type NextRequest } from "next/server";

// Pages a signed-in user may still open while a one-time password must be replaced.
const OPEN_WHILE_CHANGE_REQUIRED = ["/account/change-password", "/login", "/admin-login", "/forgot-password", "/reset-password"];

/**
 * Keeps an account that must replace a one-time password on the change-password page.
 * The API enforces the same rule; this only avoids pages that would fail to load.
 */
export function proxy(request: NextRequest) {
  const mustChange = request.cookies.get("opfin_password_change_required")?.value === "1";
  const path = request.nextUrl.pathname;
  if (mustChange && !OPEN_WHILE_CHANGE_REQUIRED.some((open) => path === open || path.startsWith(`${open}/`))) {
    return NextResponse.redirect(new URL("/account/change-password", request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!api|_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|jpeg|webp|ico|txt|xml)$).*)"]
};
