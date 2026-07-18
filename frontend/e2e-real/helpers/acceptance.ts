import { expect, type APIRequestContext, type Page, type TestInfo } from "@playwright/test";
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
const CONSOLE_ALLOWLIST = [
  /Download the React DevTools/i,
  /favicon\.ico/i,
  /Failed to load resource: the server responded with a status of 401/i,
];

export async function apiLogin(
  request: APIRequestContext,
  opts: { user?: string; password?: string } = {},
): Promise<{ token: string; user: Record<string, unknown> }> {
  const user = opts.user || process.env.E2E_USER || "";
  const password = opts.password || process.env.E2E_PASSWORD || "";
  expect(user, "E2E_USER required").toBeTruthy();
  expect(password, "E2E_PASSWORD required").toBeTruthy();

  const res = await request.post("/api/v1/auth/login", {
    data: { login: user, password, device_name: "acceptance" },
    timeout: 20_000,
  });
  expect(res.ok(), await res.text()).toBeTruthy();
  const body = await res.json();
  const token = body?.data?.token as string;
  expect(token).toBeTruthy();
  return { token, user: body.data.user };
}

/**
 * Authenticate for acceptance.
 * - ui=true: exercise the real login form, then obtain a bearer token for API asserts
 * - ui=false: inject Sanctum token into localStorage and open /apps
 */
export async function login(
  page: Page,
  opts: { locale?: "en" | "fa"; user?: string; password?: string; ui?: boolean } = {},
) {
  const locale = opts.locale || "en";
  const useUi = opts.ui === true; // default API+inject (stable); UI path opted in
  const loginName = opts.user || process.env.E2E_USER || "";
  const password = opts.password || process.env.E2E_PASSWORD || "";

  if (useUi) {
    await page.goto(`/${locale}/login`);
    await page.evaluate(() => localStorage.removeItem("auth-storage"));
    await page.reload();
    await page.locator("#login, input[name='login']").first().fill(loginName);
    await page.locator('input[type="password"]').first().fill(password);
    await page.getByRole("button", { name: /sign in|log in|login|ورود/i }).first().click();
    await expect(page).not.toHaveURL(/\/login/, { timeout: 30_000 });
  }

  const { token, user } = await apiLogin(page.request, opts);

  if (!useUi) {
    await page.addInitScript(
      ({ token: t, user: u }) => {
        localStorage.setItem(
          "auth-storage",
          JSON.stringify({ state: { token: t, user: u }, version: 0 }),
        );
      },
      { token, user },
    );
    await page.goto(`/${locale}/apps`);
    await expect(page.getByTestId("apps-launcher")).toBeVisible({ timeout: 30_000 });
  }

  return token;
}

export async function authedGet(page: Page, token: string, url: string) {
  return page.request.get(url, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    timeout: 20_000,
  });
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
    if (res.status() === 500) {
      networkErrors.push({ url: res.url(), status: 500 });
    }
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
      expect(networkErrors, `Unexpected HTTP 500: ${JSON.stringify(networkErrors)}`).toEqual([]);
    },
  };
}

export async function assertDb(
  page: Page,
  token: string,
  body: {
    entity: string;
    key: string;
    value: string | number;
    expect?: Record<string, string | number>;
  },
) {
  const res = await page.request.post("/api/v1/acceptance/assert", {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    data: body,
    timeout: 20_000,
  });
  expect(res.ok(), await res.text()).toBeTruthy();
  const json = await res.json();
  expect(json.success).toBeTruthy();
}

export async function expectNoHorizontalOverflow(page: Page) {
  await page.waitForTimeout(250);
  const overflow = await page.evaluate(() => {
    const doc = document.documentElement;
    return {
      scrollWidth: doc.scrollWidth,
      clientWidth: doc.clientWidth,
      overflow: doc.scrollWidth > doc.clientWidth + 2,
    };
  });
  expect(
    overflow.overflow,
    `horizontal overflow ${overflow.scrollWidth}>${overflow.clientWidth}`,
  ).toBeFalsy();
}
