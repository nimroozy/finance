import { defineConfig, devices } from "@playwright/test";

const baseURL =
  process.env.BASE_URL ||
  process.env.PLAYWRIGHT_BASE_URL ||
  "http://127.0.0.1:3000";

export default defineConfig({
  testDir: "./e2e-real",
  testMatch: /acceptance\.spec\.ts$/,
  globalSetup: "./e2e-real/global-setup.acceptance.ts",
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  reporter: [["list"]],
  use: {
    baseURL,
    trace: "on-first-retry",
  },
  projects: [{ name: "desktop-chromium", use: { ...devices["Desktop Chrome"] } }],
});
