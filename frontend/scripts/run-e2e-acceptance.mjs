/**
 * Fail-fast runner for Stage 10.4 real acceptance Playwright suite.
 * Exits non-zero if E2E_ACCEPTANCE!=1 or required env is missing (no silent skip).
 * Required skipped count must be zero (optional Zoho write may skip).
 */
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";

const missing = [];

if (process.env.E2E_ACCEPTANCE !== "1") {
  missing.push("E2E_ACCEPTANCE=1");
}

const base = process.env.BASE_URL || process.env.PLAYWRIGHT_BASE_URL || "";
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
      "\n\nSee docs/REAL_WORKFLOW_RESULTS.md and docs/STAGE_10_4_ACCEPTANCE_RESULTS.md",
  );
  process.exit(1);
}

const artifactRoot =
  process.env.ACCEPTANCE_ARTIFACT_DIR ||
  path.join(process.cwd(), "..", "artifacts", "acceptance");
fs.mkdirSync(path.join(artifactRoot, "reports"), { recursive: true });

const result = spawnSync(
  "npx",
  ["playwright", "test", "-c", "playwright.acceptance.config.ts"],
  {
    stdio: "inherit",
    env: { ...process.env, ACCEPTANCE_ARTIFACT_DIR: artifactRoot },
    shell: process.platform === "win32",
  },
);

const status = result.status === null ? 1 : result.status;

// Enforce: required suite must not report skipped tests (optional Zoho is annotated skip).
try {
  const jsonPath = path.join(artifactRoot, "reports", "acceptance.json");
  if (fs.existsSync(jsonPath)) {
    const report = JSON.parse(fs.readFileSync(jsonPath, "utf8"));
    const suites = report.suites || [];
    let skipped = 0;
    let optionalSkipped = 0;
    const walk = (suite) => {
      for (const spec of suite.specs || []) {
        for (const t of spec.tests || []) {
          for (const r of t.results || []) {
            if (r.status === "skipped") {
              const title = `${spec.title} ${t.projectName || ""}`;
              if (/optional external zoho/i.test(title)) optionalSkipped += 1;
              else skipped += 1;
            }
          }
        }
      }
      for (const child of suite.suites || []) walk(child);
    };
    for (const s of suites) walk(s);
    const summary = {
      required_skipped: skipped,
      optional_skipped: optionalSkipped,
      exit_status: status,
    };
    fs.writeFileSync(
      path.join(artifactRoot, "reports", "skip-enforcement.json"),
      JSON.stringify(summary, null, 2),
    );
    if (skipped > 0) {
      console.error(
        `e2e:acceptance failed: required skipped count is ${skipped} (must be 0)`,
      );
      process.exit(1);
    }
  }
} catch (e) {
  console.error("e2e:acceptance skip enforcement parse failed", e);
  process.exit(1);
}

process.exit(status);
