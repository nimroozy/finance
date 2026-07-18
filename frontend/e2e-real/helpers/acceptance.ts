import { expect, type Page, type TestInfo } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const artifactRoot =
  process.env.ACCEPTANCE_ARTIFACT_DIR ||
  path.join(process.cwd(), "..", "artifacts", "acceptance");

export const PRIMARY_APPS = [
  "customers",
  "payments",
  "collections",
  "tasks",
  "support",
  "noc",
  "crm",
  "installations",
  "inventory",
  "services",
  "reports",
  "administration",
] as const;

/** Harmless external warnings only — keep tiny. */
const CONSOLE_ALLOWLIST = [/Download the React DevTools/i, /favicon\.ico/i];

export async function login(
  page: Page,
  opts: { locale?: "en" | "fa"; user?: string; password?: string } = {},
) {
  const locale = opts.locale || "en";
  const user = opts.user || process.env.E2E_USER || "";
  const password = opts.password || process.env.E2E_PASSWORD || "";
  expect(user, "E2E_USER required").toBeTruthy();
  expect(password, "E2E_PASSWORD required").toBeTruthy();

  await page.goto(`/${locale}/login`);
  await page.locator("#login, input[name='login']").first().fill(user);
  await page.locator('input[type="password"]').first().fill(password);
  await page.getByRole("button", { name: /sign in|log in|login|ورود/i }).first().click();
  await expect(page).not.toHaveURL(/\/login/, { timeout: 30_000 });
}

export function attachFailureGuards(page: Page, testInfo: TestInfo) {
  const consoleErrors: string[] = [];
  const networkErrors: Array<{ url: string; status: number }> = [];

  page.on("console", (msg) => {
    if (msg.type() === "error") {
      const text = msg.text();
      if (!CONSOLE_ALLOWLIST.some((re) => re.test(text))) {
        consoleErrors.push(text);
      }
    }
  });

  page.on("pageerror", (err) => {
    consoleErrors.push(`pageerror:${err.message}`);
  });

  page.on("response", (res) => {
    const status = res.status();
    const url = res.url();
    if (![401, 403, 404, 500].includes(status)) return;
    // Login page and intentional denial checks mark expected via test annotations.
    if (url.includes("/api/v1/auth/login") && status === 401) return;
    if (testInfo.annotations.some((a) => a.type === "expect-status" && a.description === String(status))) {
      return;
    }
    networkErrors.push({ url, status });
  });

  return {
    async flush() {
      fs.mkdirSync(path.join(artifactRoot, "console"), { recursive: true });
      fs.mkdirSync(path.join(artifactRoot, "network"), { recursive: true });
      const slug = testInfo.title.replace(/[^\w.-]+/g, "_").slice(0, 80);
      fs.writeFileSync(
        path.join(artifactRoot, "console", `${slug}.json`),
        JSON.stringify({ errors: consoleErrors }, null, 2),
      );
      fs.writeFileSync(
        path.join(artifactRoot, "network", `${slug}.json`),
        JSON.stringify({ errors: networkErrors }, null, 2),
      );
      expect(consoleErrors, `Unexpected console errors: ${consoleErrors.join(" | ")}`).toEqual([]);
      const unexpected = networkErrors.filter((e) => e.status === 500);
      expect(unexpected, `Unexpected HTTP 500: ${JSON.stringify(unexpected)}`).toEqual([]);
    },
  };
}

export async function assertDb(
  page: Page,
  body: {
    entity: string;
    key: string;
    value: string | number;
    expect?: Record<string, string | number>;
  },
) {
  const res = await page.request.post("/api/v1/acceptance/assert", { data: body });
  expect(res.ok(), await res.text()).toBeTruthy();
  const json = await res.json();
  expect(json.success).toBeTruthy();
}

export async function expectNoHorizontalOverflow(page: Page) {
  const overflow = await page.evaluate(() => {
    const doc = document.documentElement;
    return doc.scrollWidth > doc.clientWidth + 1;
  });
  expect(overflow, "horizontal overflow").toBeFalsy();
}
