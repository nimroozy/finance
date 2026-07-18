/**
 * Stage 9.1 unified app launcher UX — mocked API Playwright coverage.
 */
import { test, expect, type Page, type Route } from "@playwright/test";

const ADMIN_PERMS = [
  "dashboard.view",
  "payments.view",
  "payments.create",
  "assignments.view",
  "visits.view",
  "tickets.view",
  "tickets.create",
  "tasks.view",
  "tasks.create",
  "installations.view",
  "installations.create",
  "customers.view",
  "crm.leads.view",
  "crm.leads.create",
  "crm.quotations.view",
  "crm.follow_ups.manage",
  "crm.reports.view",
  "crm.pipeline.manage",
  "inventory.dashboard.view",
  "inventory.stock.view",
  "inventory.products.view",
  "inventory.equipment.view",
  "inventory.transfers.view",
  "inventory.purchasing.view",
  "inventory.sites.view",
  "inventory.towers.view",
  "inventory.repairs.manage",
  "inventory.reports.view",
  "whatsapp.view",
  "notifications.view",
  "reports.assignments",
  "reports.visits",
  "reports.payments",
  "branches.view",
  "users.view",
  "audit.view",
  "settings.manage",
  "escalations.view",
];

const SUPPORT_PERMS = [
  "dashboard.view",
  "tickets.view",
  "tickets.create",
  "tasks.view",
  "customers.view",
  "whatsapp.view",
  "notifications.view",
];

function mockUser(overrides: Partial<{
  roles: string[];
  permissions: string[];
  ui_preferences: Record<string, unknown>;
}> = {}) {
  return {
    id: 1,
    name: "Ops Admin",
    email: "ops@finance.mns.af",
    username: "ops",
    locale: "en",
    status: "active",
    roles: overrides.roles ?? ["Super Administrator"],
    permissions: overrides.permissions ?? ADMIN_PERMS,
    force_password_change: false,
    branches: [{ id: 1, code: "KBL", name_en: "Kabul", name_fa: "کابل" }],
    ui_preferences: overrides.ui_preferences ?? {
      locale: "en",
      theme: "system",
      default_app_id: null,
      favorite_app_ids: [],
      recent_app_ids: [],
    },
  };
}

async function fulfillJson(route: Route, data: unknown, meta?: Record<string, unknown>) {
  await route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data, ...(meta ? { meta } : {}) }),
  });
}

async function setupLauncherMocks(
  page: Page,
  user = mockUser(),
  prefsState?: { favorite_app_ids: string[]; recent_app_ids: string[]; theme: string },
) {
  const prefs = {
    id: 1,
    user_id: user.id,
    locale: "en",
    theme: prefsState?.theme ?? "system",
    default_app_id: null as string | null,
    favorite_app_ids: prefsState?.favorite_app_ids ?? ([] as string[]),
    recent_app_ids: prefsState?.recent_app_ids ?? ([] as string[]),
    date_format: null,
    calendar_system: null,
    collapsed_nav_groups: null,
    bottom_nav_overrides: null,
    notification_prefs: null,
  };

  await page.addInitScript((u) => {
    window.localStorage.setItem(
      "auth-storage",
      JSON.stringify({ state: { token: "e2e-stage91", user: u }, version: 0 }),
    );
  }, user);

  await page.route("**/api/v1/**", async (route) => {
    const req = route.request();
    const url = req.url();
    const method = req.method();

    if (url.includes("/auth/me")) return fulfillJson(route, user);
    if (url.includes("/auth/login") && method === "POST") {
      return fulfillJson(route, {
        token: "e2e-login-token",
        token_type: "Bearer",
        user,
        force_password_change: false,
      });
    }

    if (url.includes("/me/ui-preferences/favorites/") && method === "POST") {
      const appId = decodeURIComponent(url.split("/favorites/")[1]?.split(/[?#]/)[0] ?? "");
      if (appId && !prefs.favorite_app_ids.includes(appId)) prefs.favorite_app_ids.push(appId);
      return fulfillJson(route, { ...prefs });
    }
    if (url.includes("/me/ui-preferences/favorites/") && method === "DELETE") {
      const appId = decodeURIComponent(url.split("/favorites/")[1]?.split(/[?#]/)[0] ?? "");
      prefs.favorite_app_ids = prefs.favorite_app_ids.filter((id) => id !== appId);
      return fulfillJson(route, { ...prefs });
    }
    if (url.includes("/me/ui-preferences/recent/") && method === "POST") {
      const appId = decodeURIComponent(url.split("/recent/")[1]?.split(/[?#]/)[0] ?? "");
      prefs.recent_app_ids = [appId, ...prefs.recent_app_ids.filter((id) => id !== appId)].slice(0, 12);
      return fulfillJson(route, { ...prefs });
    }
    if (url.includes("/me/ui-preferences") && method === "PUT") {
      const body = req.postDataJSON() as Record<string, unknown>;
      Object.assign(prefs, body);
      return fulfillJson(route, { ...prefs });
    }
    if (url.includes("/me/ui-preferences") && method === "GET") {
      return fulfillJson(route, { ...prefs });
    }

    if (url.includes("/notifications")) {
      return fulfillJson(route, [], { current_page: 1, last_page: 1, per_page: 20, total: 0 });
    }

    if (url.includes("/operations/search")) {
      return fulfillJson(route, { tickets: [], tasks: [], installations: [] });
    }

    if (url.includes("/dashboard/summary")) {
      return fulfillJson(route, {
        role: "super_admin",
        customers: { total: 0, unmapped: 0 },
        invoices: { total: 0, open: 0, overdue: 0, outstanding_amount: 0 },
        generated_at: new Date().toISOString(),
      });
    }

    return fulfillJson(route, []);
  });
}

test.describe("Stage 9.1 launcher", () => {
  test("login redirects to /apps", async ({ page }) => {
    const user = mockUser();
    await page.route("**/api/v1/**", async (route) => {
      const url = route.request().url();
      if (url.includes("/auth/login")) {
        return fulfillJson(route, {
          token: "login-token",
          token_type: "Bearer",
          user,
          force_password_change: false,
        });
      }
      if (url.includes("/me/ui-preferences")) {
        return fulfillJson(route, {
          theme: "system",
          favorite_app_ids: [],
          recent_app_ids: [],
          default_app_id: null,
        });
      }
      if (url.includes("/auth/me")) return fulfillJson(route, user);
      return fulfillJson(route, []);
    });

    await page.goto("/en/login");
    await page.getByLabel(/login|email|username/i).fill("ops");
    await page.locator('#password').fill("password");
    await page.getByRole("button", { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/en\/apps/);
    await expect(page.getByTestId("apps-launcher")).toBeVisible();
  });

  test("role visibility hides unauthorized apps", async ({ page }) => {
    await setupLauncherMocks(
      page,
      mockUser({ roles: ["Support Agent"], permissions: SUPPORT_PERMS }),
    );
    await page.goto("/en/apps");
    await expect(page.getByTestId("app-card-support")).toBeVisible();
    await expect(page.getByTestId("app-card-tasks")).toBeVisible();
    await expect(page.getByTestId("app-card-inventory")).toHaveCount(0);
    await expect(page.getByTestId("app-card-administration")).toHaveCount(0);
  });

  test("primary launcher apps only — submodules hidden from grid", async ({ page }) => {
    await setupLauncherMocks(page);
    await page.goto("/en/apps");
    await expect(page.getByTestId("apps-launcher")).toBeVisible();
    await expect(page.getByTestId("apps-all").getByTestId("app-card-customers")).toBeVisible();
    await expect(page.getByTestId("apps-all").getByTestId("app-card-administration")).toBeVisible();
    await expect(page.getByTestId("apps-all").getByTestId("app-card-leads")).toHaveCount(0);
    await expect(page.getByTestId("apps-all").getByTestId("app-card-equipment")).toHaveCount(0);
    await expect(page.getByTestId("apps-all").getByTestId("app-card-users")).toHaveCount(0);
  });

  test("favorites toggle and recent recording", async ({ page }) => {
    await setupLauncherMocks(page);
    await page.goto("/en/apps");
    await expect(page.getByTestId("apps-launcher")).toBeVisible();

    const payments = page.getByTestId("app-card-payments");
    await expect(payments).toBeVisible();
    await page.getByTestId("app-favorite-payments").first().click();
    await expect(page.getByTestId("apps-favorites")).toBeVisible();
    await expect(page.getByTestId("apps-favorites").getByTestId("app-card-payments")).toBeVisible();

    await page.getByTestId("apps-all").getByTestId("app-card-payments").getByRole("link").click();
    await page.goto("/en/apps");
    await expect(page.getByTestId("apps-recent")).toBeVisible({
      timeout: 10000,
    });
  });

  test("EN/FA RTL launcher", async ({ page }) => {
    await setupLauncherMocks(page);
    await page.goto("/en/apps");
    await expect(page.locator("html")).toHaveAttribute("dir", "ltr");
    await expect(page.getByRole("heading", { name: "Apps", exact: true })).toBeVisible();

    await page.goto("/fa/apps");
    await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
    await expect(page.getByRole("heading", { name: "برنامه‌ها", exact: true })).toBeVisible();
  });

  test("dark mode theme switcher", async ({ page }) => {
    await setupLauncherMocks(page);
    await page.goto("/en/apps");

    const viewport = page.viewportSize();
    const isMobile = (viewport?.width ?? 1280) < 1024;
    if (isMobile) {
      await page.getByTestId("user-menu").click({ force: true });
      await page
        .getByTestId("user-menu-dropdown")
        .getByTestId("theme-switcher")
        .getByLabel(/Dark/i)
        .click({ force: true });
    } else {
      await page
        .getByTestId("app-header")
        .locator("> div.flex [data-testid='theme-switcher']")
        .getByLabel(/Dark/i)
        .click();
    }
    await expect(page.locator("html")).toHaveClass(/dark/);
  });

  test("mobile launcher and bottom nav", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await setupLauncherMocks(page);
    await page.goto("/en/apps");
    await expect(page.getByTestId("apps-launcher")).toBeVisible();
    await expect(page.getByTestId("bottom-nav")).toBeVisible();
    await expect(page.getByTestId("launcher-button")).toBeVisible();
    // 2-col grid on mobile
    const grid = page.getByTestId("app-grid").first();
    await expect(grid).toBeVisible();
  });

  test("quick create and search", async ({ page }) => {
    await setupLauncherMocks(page);
    await page.goto("/en/apps");

    await page.getByTestId("quick-create").click();
    await expect(page.getByTestId("quick-create-menu")).toBeVisible();
    await expect(page.getByTestId("quick-create-menu").getByText(/New ticket/i)).toBeVisible();

    await page.getByTestId("apps-search").fill("payment");
    await expect(page.getByTestId("app-card-payments")).toBeVisible();
    await expect(page.getByTestId("app-card-inventory")).toHaveCount(0);

    // Ctrl+K command menu with app hits
    await page.keyboard.press("Control+K");
    await expect(page.getByTestId("command-menu-input")).toBeVisible();
    await page.getByTestId("command-menu-input").fill("crm");
    await expect(page.getByTestId("command-menu-results")).toBeVisible();
  });

  test("default_app_id redirects after login", async ({ page }) => {
    const user = mockUser({
      ui_preferences: {
        locale: "en",
        theme: "system",
        default_app_id: "crm",
        favorite_app_ids: [],
        recent_app_ids: [],
      },
    });
    await page.route("**/api/v1/**", async (route) => {
      const url = route.request().url();
      if (url.includes("/auth/login")) {
        return fulfillJson(route, {
          token: "login-token",
          token_type: "Bearer",
          user,
          force_password_change: false,
        });
      }
      if (url.includes("/me/ui-preferences")) {
        return fulfillJson(route, user.ui_preferences);
      }
      if (url.includes("/auth/me")) return fulfillJson(route, user);
      return fulfillJson(route, []);
    });

    await page.goto("/en/login");
    await page.getByLabel(/login|email|username/i).fill("ops");
    await page.locator("#password").fill("password");
    await page.getByRole("button", { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/en\/crm\/dashboard/);
  });

  test("403 forbidden page", async ({ page }) => {
    await setupLauncherMocks(page);
    await page.goto("/en/403");
    await expect(page.getByTestId("forbidden-page")).toBeVisible();
    await expect(page.getByText("403")).toBeVisible();
  });
});
