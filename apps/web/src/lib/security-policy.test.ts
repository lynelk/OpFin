import { describe, expect, it } from "vitest";
import { contentSecurityPolicy, matchesProtectedPath, securityHeaders } from "./security-policy";

const nonce = "MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=";

describe("browser security policy", () => {
  it("requires nonce-authorised scripts without eval in production", () => {
    const policy = contentSecurityPolicy(nonce, true, "https://api.example.test/api");
    const scripts = policy.split("; ").find((value) => value.startsWith("script-src"));
    expect(scripts).toContain(`'nonce-${nonce}'`);
    expect(scripts).toContain("'strict-dynamic'");
    expect(scripts).not.toMatch(/unsafe-inline|unsafe-eval/);
    expect(policy).toContain("frame-ancestors 'none'");
    expect(policy).toContain("object-src 'none'");
    expect(policy).toContain("form-action 'self'");
    expect(policy).toContain("upgrade-insecure-requests");
    expect(policy).toContain("connect-src 'self' https://api.example.test");
  });

  it("does not reflect credentials, paths or insecure endpoints into production CSP", () => {
    for (const endpoint of ["http://api.example.test", "https://user:password@example.test", "invalid; script-src *", "javascript:alert(1)"]) {
      expect(contentSecurityPolicy(nonce, true, endpoint)).toContain("connect-src 'self';");
    }
    expect(contentSecurityPolicy(nonce, true, "https://api.example.test/private?token=secret")).not.toContain("token=");
  });

  it("rejects malformed nonces rather than permitting header injection", () => {
    expect(() => contentSecurityPolicy("bad\r\nheader", true)).toThrow();
    expect(() => contentSecurityPolicy("short", true)).toThrow();
  });

  it("keeps development-only allowances out of production", () => {
    expect(contentSecurityPolicy(nonce, false)).toContain("'unsafe-eval'");
    expect(contentSecurityPolicy(nonce, false)).not.toContain("upgrade-insecure-requests");
    expect(contentSecurityPolicy(nonce, true)).not.toContain(" ws:");
  });

  it("sets anti-framing and MIME protections while retaining first-party KYC camera access", () => {
    const headers = securityHeaders(true);
    expect(headers["X-Content-Type-Options"]).toBe("nosniff");
    expect(headers["X-Frame-Options"]).toBe("DENY");
    expect(headers["Strict-Transport-Security"]).toBe("max-age=31536000");
    expect(headers["Permissions-Policy"]).toContain("camera=(self)");
    expect(securityHeaders(false)).not.toHaveProperty("Strict-Transport-Security");
  });

  it("matches protected path segments without treating admin-login as an admin page", () => {
    expect(matchesProtectedPath("/admin", "/admin")).toBe(true);
    expect(matchesProtectedPath("/admin/users", "/admin")).toBe(true);
    expect(matchesProtectedPath("/admin-login", "/admin")).toBe(false);
  });
});
