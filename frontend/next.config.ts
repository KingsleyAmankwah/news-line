import type { NextConfig } from "next";

// Hero images are served as absolute image-style URLs by Drupal, so next/image
// must be told which host to trust. Derived from the backend base URL so it
// follows the environment (local DDEV, Oracle in production).
const drupalUrl = new URL(
  process.env.DRUPAL_BASE_URL ?? "http://news-line.ddev.site:33000",
);

const nextConfig: NextConfig = {
  images: {
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
