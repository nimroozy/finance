/**
 * Throws if acceptance env is incomplete so required tests never silently skip.
 */
export default function globalSetup() {
  if (process.env.E2E_ACCEPTANCE !== "1") {
    throw new Error(
      "E2E_ACCEPTANCE=1 is required for acceptance suite (see docs/REAL_WORKFLOW_RESULTS.md)",
    );
  }

  const base = process.env.BASE_URL || process.env.PLAYWRIGHT_BASE_URL;
  if (!base) {
    throw new Error("BASE_URL or PLAYWRIGHT_BASE_URL is required for acceptance suite");
  }

  if (!process.env.E2E_USER || !process.env.E2E_PASSWORD) {
    throw new Error("E2E_USER and E2E_PASSWORD are required for acceptance suite");
  }
}
