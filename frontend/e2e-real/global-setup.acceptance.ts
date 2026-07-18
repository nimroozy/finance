/**
 * Throws if acceptance env is incomplete so required tests never silently skip.
 * Also performs a single API login to seed E2E_TOKEN (avoids login rate limits).
 */
import fs from "node:fs";
import path from "node:path";

export default async function globalSetup() {
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

  const res = await fetch(`${base.replace(/\/$/, "")}/api/v1/auth/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({
      login: process.env.E2E_USER,
      password: process.env.E2E_PASSWORD,
      device_name: "acceptance-global",
    }),
  });

  if (!res.ok) {
    const text = await res.text();
    throw new Error(`Acceptance global login failed (${res.status}): ${text}`);
  }

  const body = (await res.json()) as {
    data?: { token?: string; user?: Record<string, unknown> };
  };
  const token = body.data?.token;
  if (!token) {
    throw new Error("Acceptance global login returned no token");
  }

  process.env.E2E_TOKEN = token;
  process.env.E2E_USER_JSON = JSON.stringify(body.data?.user || {});

  const artifactRoot =
    process.env.ACCEPTANCE_ARTIFACT_DIR ||
    path.join(process.cwd(), "..", "artifacts", "acceptance");
  fs.mkdirSync(path.join(artifactRoot, "auth"), { recursive: true });
  fs.writeFileSync(
    path.join(artifactRoot, "auth", "token.json"),
    JSON.stringify({ token, user: body.data?.user || {} }, null, 2),
  );
}
