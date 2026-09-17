export function contentSecurityPolicy(nonce: string, production: boolean, apiUrl?: string): string {
  if (!/^[A-Za-z0-9+/=_-]{22,}$/.test(nonce)) throw new Error("Invalid CSP nonce");
  let apiOrigin = "";
  if (apiUrl) {
    try {
      const url = new URL(apiUrl);
      if (!url.username && !url.password && (url.protocol === "https:" || (!production && url.protocol === "http:"))) {
        apiOrigin = ` ${url.origin}`;
      }
    } catch { /* Invalid or non-browser configuration is never reflected into CSP. */ }
  }
  return [
    "default-src 'self'",
    `script-src 'self' 'nonce-${nonce}' 'strict-dynamic'${production ? "" : " 'unsafe-eval'"}`,
    // Existing progress indicators use inline style attributes, not inline scripts.
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: blob:",
    "font-src 'self'",
    `connect-src 'self'${apiOrigin}${production ? "" : " ws: wss:"}`,
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
    ...(production ? ["upgrade-insecure-requests"] : [])
  ].join("; ");
}

export function securityHeaders(production: boolean): Record<string, string> {
  return {
    "X-Content-Type-Options": "nosniff",
    "X-Frame-Options": "DENY",
    "Referrer-Policy": "strict-origin-when-cross-origin",
    // Keep first-party camera access available for an authorised KYC flow.
    "Permissions-Policy": "camera=(self), microphone=(), geolocation=(), payment=()",
    ...(production ? { "Strict-Transport-Security": "max-age=31536000" } : {})
  };
}

export function matchesProtectedPath(pathname: string, prefix: string): boolean {
  return pathname === prefix || pathname.startsWith(`${prefix}/`);
}
