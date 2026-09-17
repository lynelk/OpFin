import { NextResponse, type NextRequest } from "next/server";
import { contentSecurityPolicy, matchesProtectedPath, securityHeaders } from "./src/lib/security-policy";

const protectedPrefixes = [
  "/dashboard", "/setup", "/kyc", "/consent", "/borrow", "/save", "/grow",
  "/more", "/money", "/money-autopilot", "/calendar", "/support", "/loans",
  "/admin", "/employer", "/savings", "/insurance", "/investments"
];

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const production = process.env.NODE_ENV === "production";
  const nonce = btoa(String.fromCharCode(...crypto.getRandomValues(new Uint8Array(32))));
  const policy = contentSecurityPolicy(nonce, production, process.env.NEXT_PUBLIC_OPFIN_API_URL);
  const requestHeaders = new Headers(request.headers);
  // Never trust a nonce or policy supplied by a caller.
  requestHeaders.set("x-nonce", nonce);
  requestHeaders.set("Content-Security-Policy", policy);
  let response = NextResponse.next({ request: { headers: requestHeaders } });
  const isProtected = protectedPrefixes.some((prefix) => matchesProtectedPath(pathname, prefix));

  if (isProtected) {
    if (!request.cookies.get("opfin_access_token")?.value) {
      const loginUrl = new URL("/login", request.url);
      loginUrl.searchParams.set("next", pathname);
      response = NextResponse.redirect(loginUrl);
    } else {
      // These cookie hints control navigation only. The API must independently
      // authenticate the token and authorise every protected operation.
      const role = request.cookies.get("opfin_role")?.value;
      if (matchesProtectedPath(pathname, "/admin") && !["platform_admin", "operations", "support"].includes(role ?? "")) {
        response = NextResponse.redirect(new URL("/dashboard", request.url));
      } else if (matchesProtectedPath(pathname, "/employer") && !["platform_admin", "employer_admin"].includes(role ?? "")) {
        response = NextResponse.redirect(new URL("/dashboard", request.url));
      }
    }
  }

  response.headers.set("Content-Security-Policy", policy);
  response.headers.set("Cache-Control", "private, no-store, max-age=0");
  for (const [key, value] of Object.entries(securityHeaders(production))) response.headers.set(key, value);
  return response;
}

export const config = {
  matcher: ["/((?!api(?:/|$)|_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|jpeg|gif|webp|avif|woff2?|ttf)$).*)"]
};
