/** @type {import('next').NextConfig} */
if (process.env.NODE_ENV === "production") {
  if (process.env.NEXT_PUBLIC_USE_MOCK_API === "true") {
    throw new Error("NEXT_PUBLIC_USE_MOCK_API=true is not allowed in production builds.");
  }

  if (process.env.OPFIN_ENABLE_DEMO_SHORTCUTS === "true") {
    throw new Error("OPFIN_ENABLE_DEMO_SHORTCUTS=true is not allowed in production builds.");
  }
}

const nextConfig = {
  reactStrictMode: true,
  poweredByHeader: false
};

export default nextConfig;
