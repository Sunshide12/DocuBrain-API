import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Emits .next/standalone so the Docker image ships only the traced runtime files.
  output: "standalone",
};

export default nextConfig;
