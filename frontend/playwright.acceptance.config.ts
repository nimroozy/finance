import { defineConfig, devices } from "@playwright/test";
import path from "node:path";

const baseURL =
  process.env.BASE_URL ||
  process.env.PLAYWRIGHT_BASE_URL ||
  "http://127.0.0.1:18080";

const artifactRoot =
  process.env.ACCEPTANCE_ARTIFACT_DIR ||
  path.join(process.cwd(), "..", "artifacts", "acceptance");

export default defineConfig({
  testDir: "./e2e-real",
  testMatch: /acceptance.*\.spec\.ts$/,
  globalSetup: "./e2e-real/global-setup.acceptance.ts",
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: 0,
  timeout: 90_000,
  expect: { timeout: 15_000 },
  reporter: [
    ["list"],
    ["html", { open: "never", outputFolder: path.join(artifactRoot, "reports", "html") }],
    ["junit", { outputFile: path.join(artifactRoot, "junit", "acceptance.xml") }],
    ["json", { outputFile: path.join(artifactRoot, "reports", "acceptance.json") }],
  ],
  use: {
    baseURL,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    video: "off",
  },
  outputDir: path.join(artifactRoot, "test-output"),
  projects: [
    {
      name: "desktop-en",
      use: { ...devices["Desktop Chrome"], viewport: { width: 1440, height: 900 }, locale: "en-US" },
    },
    {
      name: "desktop-fa",
      use: { ...devices["Desktop Chrome"], viewport: { width: 1440, height: 900 }, locale: "fa-AF" },
    },
    {
      name: "mobile-en",
      use: { ...devices["iPhone 13"], viewport: { width: 390, height: 844 }, locale: "en-US" },
    },
    {
      name: "mobile-fa",
      use: { ...devices["iPhone 13"], viewport: { width: 390, height: 844 }, locale: "fa-AF" },
    },
    {
      name: "small-mobile-en",
      use: {
        ...devices["Galaxy S9+"],
        viewport: { width: 320, height: 700 },
        locale: "en-US",
        isMobile: true,
        hasTouch: true,
      },
    },
    {
      name: "small-mobile-fa",
      use: {
        ...devices["Galaxy S9+"],
        viewport: { width: 320, height: 700 },
        locale: "fa-AF",
        isMobile: true,
        hasTouch: true,
      },
    },
  ],
});
