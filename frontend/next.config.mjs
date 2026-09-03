import bundleAnalyzer from "@next/bundle-analyzer"

const withBundleAnalyzer = bundleAnalyzer({
  enabled: process.env.ANALYZE === "true",
})

const apiProxyTarget =
  process.env.API_PROXY_TARGET ?? "http://localhost:8080"

/** @type {import('next').NextConfig} */
const nextConfig = {
  output: "standalone",
  typescript: {
    // Type errors are caught in CI separately; don't block production builds
    ignoreBuildErrors: true,
  },
  eslint: {
    ignoreDuringBuilds: true,
  },
  compress: true,
  poweredByHeader: false,
  reactStrictMode: true,
  transpilePackages: ["@webina/ui"],
  experimental: {
    optimizePackageImports: ["lucide-react"],
  },
  async redirects() {
    return [
      { source: "/dashboard", destination: "/admin", permanent: false },
      { source: "/dashboard/:path*", destination: "/admin/:path*", permanent: false },
    ]
  },
  async rewrites() {
    return [
      {
        source: "/api/:path*",
        destination: `${apiProxyTarget}/api/:path*`,
      },
    ]
  },
}

export default withBundleAnalyzer(nextConfig)
