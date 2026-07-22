import type { NextConfig } from "next";

// Hero images are served as absolute image-style URLs by Drupal, so next/image
// must be told which host to trust. Derived from the backend base URL so it
// follows the environment (local DDEV, Oracle in production).
const drupalUrl = new URL(
  process.env.DRUPAL_BASE_URL ?? "http://news-line.ddev.site:33000",
);

const nextConfig: NextConfig = {
  images: {
    // Next 16 blocks the image optimizer from fetching hosts that resolve to a
    // private IP (SSRF protection). In development the DDEV backend resolves to
    // 127.0.0.1, so allow local IPs there only; production images come from the
    // public backend host and must stay guarded.
    dangerouslyAllowLocalIP: process.env.NODE_ENV !== "production",
    remotePatterns: [
      {
        protocol: drupalUrl.protocol.replace(":", "") as "http" | "https",
        hostname: drupalUrl.hostname,
        port: drupalUrl.port || undefined,
        pathname: "/sites/**",
      },
    ],
  },
};

export default nextConfig;
