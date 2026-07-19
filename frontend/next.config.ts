import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const nextConfig: NextConfig = {
  output: "standalone",
  poweredByHeader: false,
  // Dev-only: lets the dev server accept RSC/server-action requests that
  // arrive through a local reverse proxy (e.g. the acceptance test stack's
  // unified origin) instead of falling back to a full-page navigation that
  // resolves to the dev server's own bind address.
  allowedDevOrigins: ["127.0.0.1", "localhost"],
};

export default withNextIntl(nextConfig);
