/**
 * Fail-fast runner for Stage 10.3 real acceptance Playwright suite.
 * Exits non-zero if E2E_ACCEPTANCE!=1 or required env is missing (no silent skip).
 */
import { spawnSync } from "node:child_process";

const missing = [];

if (process.env.E2E_ACCEPTANCE !== "1") {
  missing.push("E2E_ACCEPTANCE=1");
}

const base =
  process.env.BASE_URL || process.env.PLAYWRIGHT_BASE_URL || "";
if (!base) {
  missing.push("BASE_URL (or PLAYWRIGHT_BASE_URL)");
}

if (!process.env.E2E_USER) {
  missing.push("E2E_USER");
}

if (!process.env.E2E_PASSWORD) {
  missing.push("E2E_PASSWORD");
}

if (missing.length) {
  console.error(
    "e2e:acceptance refused to start. Missing required env:\n  - " +
      missing.join("\n  - ") +
      "\n\nSee docs/REAL_WORKFLOW_RESULTS.md",
  );
  process.exit(1);
}

const result = spawnSync(
  "npx",
  ["playwright", "test", "-c", "playwright.acceptance.config.ts"],
  {
    stdio: "inherit",
    env: process.env,
    shell: process.platform === "win32",
  },
);

process.exit(result.status === null ? 1 : result.status);
