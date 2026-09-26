/** @type {import('next').NextConfig} */
const production = process.env.NODE_ENV === "production";
if (production) {
  if (process.env.NEXT_PUBLIC_USE_MOCK_API === "true") {
    throw new Error("NEXT_PUBLIC_USE_MOCK_API=true is not allowed in production builds.");
  }
  if (process.env.OPFIN_ENABLE_DEMO_SHORTCUTS === "true") {
    throw new Error("OPFIN_ENABLE_DEMO_SHORTCUTS=true is not allowed in production builds.");
  }
}

const nextConfig = {
  reactStrictMode: true,
  poweredByHeader: false,
  experimental: {
    serverActions: {
      // Retain the original limit unless the reviewed upload capability is enabled.
      // API and action handlers independently enforce bounded file and metadata sizes.
      bodySizeLimit: process.env.OPFIN_FINANCIAL_INTELLIGENCE_ENABLED === "true" ? "26mb" : "1mb"
    }
  },
  images: {
    remotePatterns: [],
    dangerouslyAllowSVG: false,
    dangerouslyAllowLocalIP: false,
    maximumRedirects: 0
  },
  async headers() {
    return [{
      source: "/:path*",
      headers: [
        { key: "X-Content-Type-Options", value: "nosniff" },
        { key: "X-Frame-Options", value: "DENY" },
        { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
        { key: "Permissions-Policy", value: "camera=(self), microphone=(), geolocation=(), payment=()" },
        ...(production ? [{ key: "Strict-Transport-Security", value: "max-age=31536000" }] : [])
      ]
    }];
  }
};

export default nextConfig;
