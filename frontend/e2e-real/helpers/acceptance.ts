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
const CONSOLE_ALLOWLIST = [
  /Download the React DevTools/i,
  /favicon\.ico/i,
  /Failed to load resource: the server responded with a status of 401/i,
  // Next.js soft-nav prefetch fallbacks under load — page still navigates in-browser.
  /Failed to fetch RSC payload/i,
  /Falling back to browser navigation/i,
];

function loadCachedAuth(): { token: string; user: Record<string, unknown> } {
  if (process.env.E2E_TOKEN) {
    return {
      token: process.env.E2E_TOKEN,
      user: JSON.parse(process.env.E2E_USER_JSON || "{}"),
    };
  }
  const file = path.join(artifactRoot, "auth", "token.json");
  if (fs.existsSync(file)) {
    return JSON.parse(fs.readFileSync(file, "utf8"));
  }
  throw new Error("Missing E2E_TOKEN — globalSetup did not seed auth");
}

/**
 * Authenticate for acceptance using the globalSetup token by default
 * (avoids login rate-limiter 429 storms across the six-project matrix).
 * Set ui=true to also exercise the login form once.
 */
export async function login(
  page: Page,
  opts: { locale?: "en" | "fa"; user?: string; password?: string; ui?: boolean } = {},
) {
  const locale = opts.locale || "en";
  const useUi = opts.ui === true;
  const { token, user } = loadCachedAuth();

  if (useUi) {
    const loginName = opts.user || process.env.E2E_USER || "";
    const password = opts.password || process.env.E2E_PASSWORD || "";
    await page.goto(`/${locale}/login`);
    await page.evaluate(() => localStorage.removeItem("auth-storage"));
    await page.reload();
    await page.locator("#login, input[name='login']").first().fill(loginName);
    await page.locator('input[type="password"]').first().fill(password);
    await page.getByRole("button", { name: /sign in|log in|login|ورود/i }).first().click();
    await expect(page).not.toHaveURL(/\/login/, { timeout: 30_000 });
    return token;
  }

  await page.addInitScript(
    ({ token: t, user: u }) => {
      localStorage.setItem(
        "auth-storage",
        JSON.stringify({ state: { token: t, user: u }, version: 0 }),
      );
    },
    { token, user },
  );
  // Hydrate auth store via launcher before deep-linking into detail pages.
  await page.goto(`/${locale}/apps`);
  await expect(page.getByTestId("apps-launcher")).toBeVisible({ timeout: 30_000 });
  await expect
    .poll(async () => page.evaluate(() => !!localStorage.getItem("auth-storage")), {
      timeout: 10_000,
    })
    .toBeTruthy();
  return token;
}

export async function authedGet(page: Page, token: string, url: string) {
  return page.request.get(url, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    timeout: 20_000,
  });
}

export async function authedPost(
  page: Page,
  token: string,
  url: string,
  data?: Record<string, unknown>,
) {
  return page.request.post(url, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    data,
    timeout: 30_000,
  });
}

/** API login for alternate acceptance users (collector, etc.). */
export async function apiLogin(
  page: Page,
  username: string,
  password = process.env.E2E_PASSWORD || "AcceptancePass1!",
): Promise<string> {
  const res = await page.request.post("/api/v1/auth/login", {
    data: { login: username, password, device_name: "acceptance-e2e" },
    headers: { Accept: "application/json" },
  });
  if (!res.ok()) {
    throw new Error(`apiLogin(${username}) failed: ${res.status()} ${await res.text()}`);
  }
  const body = await res.json();
  const token = body.data?.token as string | undefined;
  if (!token) throw new Error(`apiLogin(${username}) returned no token`);
  return token;
}

export async function ensureActiveAssignment(
  page: Page,
  adminToken: string,
  customerId: number,
  collectorId: number,
): Promise<number> {
  const list = await authedGet(
    page,
    adminToken,
    `/api/v1/assignments?customer_id=${customerId}&per_page=50`,
  );
  if (list.ok()) {
    const rows = ((await list.json()).data || []) as Array<{
      id: number;
      is_active?: boolean;
      collector_id?: number;
      status?: string;
    }>;
    for (const row of rows) {
      if (!row.is_active) continue;
      if (Number(row.collector_id) === Number(collectorId)) {
        return Number(row.id);
      }
      const cancel = await authedPost(page, adminToken, `/api/v1/assignments/${row.id}/cancel`, {
        reason: "ACCEPTANCE reset for workflow",
      });
      if (!cancel.ok()) {
        throw new Error(`cancel assignment ${row.id}: ${await cancel.text()}`);
      }
    }
  }

  const create = await authedPost(page, adminToken, "/api/v1/assignments", {
    customer_id: customerId,
    collector_id: collectorId,
    priority: "high",
  });
  if (!create.ok()) {
    throw new Error(`create assignment: ${await create.text()}`);
  }
  const created = await create.json();
  const id = Number(created.data?.assignment?.id || created.data?.id);
  if (!id) throw new Error("assignment create returned no id");
  return id;
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
    const body = document.body;
    const width = Math.max(doc.clientWidth, window.innerWidth);
    const scroll = Math.max(doc.scrollWidth, body?.scrollWidth || 0);
    return {
      scrollWidth: scroll,
      clientWidth: width,
      overflow: scroll > width + 4,
    };
  });
  expect(
    overflow.overflow,
    `horizontal overflow ${overflow.scrollWidth}>${overflow.clientWidth}`,
  ).toBeFalsy();
}
