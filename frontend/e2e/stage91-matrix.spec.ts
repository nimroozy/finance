/**
 * Stage 9.1 UI matrix smoke — EN + FA, desktop + mobile (via Playwright projects),
 * /apps launcher and one payments + tickets list. Mocked auth.
 */
import { test, expect, type Page, type Route } from "@playwright/test";

const ADMIN_PERMS = [
  "dashboard.view",
  "payments.view",
  "tickets.view",
  "tickets.create",
  "tasks.view",
  "customers.view",
  "notifications.view",
];

function mockUser() {
  return {
    id: 1,
    name: "Ops Admin",
    email: "ops@finance.mns.af",
    username: "ops",
    locale: "en",
    status: "active",
    roles: ["Super Administrator"],
    permissions: ADMIN_PERMS,
    force_password_change: false,
    branches: [{ id: 1, code: "KBL", name_en: "Kabul", name_fa: "کابل" }],
    ui_preferences: {
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

async function setupMatrixMocks(page: Page) {
  const user = mockUser();
  const prefs = {
    id: 1,
    user_id: user.id,
    locale: "en",
    theme: "system",
    default_app_id: null as string | null,
    favorite_app_ids: [] as string[],
    recent_app_ids: [] as string[],
    date_format: null,
    calendar_system: null,
    collapsed_nav_groups: null,
    bottom_nav_overrides: null,
    notification_prefs: null,
  };

  await page.addInitScript((u) => {
    window.localStorage.setItem(
      "auth-storage",
      JSON.stringify({ state: { token: "e2e-stage91-matrix", user: u }, version: 0 }),
    );
  }, user);

  await page.route("**/api/v1/**", async (route) => {
    const url = route.request().url();
    const method = route.request().method();

    if (url.includes("/auth/me")) return fulfillJson(route, user);
    if (url.includes("/me/ui-preferences") && method === "GET") {
      return fulfillJson(route, { ...prefs });
    }
    if (url.includes("/me/ui-preferences") && method === "PUT") {
      Object.assign(prefs, route.request().postDataJSON() as Record<string, unknown>);
      return fulfillJson(route, { ...prefs });
    }
    if (url.includes("/notifications")) {
      return fulfillJson(route, [], {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
      });
    }
    if (url.includes("/operations/search")) {
      return fulfillJson(route, { tickets: [], tasks: [], installations: [] });
    }
    if (url.includes("/payments") && !url.includes("/payments/")) {
      return fulfillJson(route, [], {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
      });
    }
    if (url.includes("/tickets") && !url.includes("/tickets/")) {
      return fulfillJson(route, [], {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
      });
    }
    return fulfillJson(route, []);
  });
}

for (const locale of ["en", "fa"] as const) {
  test.describe(`Stage 9.1 matrix (${locale})`, () => {
    test(`apps launcher smoke`, async ({ page }) => {
      await setupMatrixMocks(page);
      await page.goto(`/${locale}/apps`);
      await expect(page.locator("html")).toHaveAttribute(
        "dir",
        locale === "fa" ? "rtl" : "ltr",
      );
      await expect(page.getByTestId("apps-launcher")).toBeVisible();
      await expect(page.getByTestId("app-grid").first()).toBeVisible();
      await expect(page.getByTestId("launcher-button")).toBeVisible();
    });

    test(`payments list smoke`, async ({ page }) => {
      await setupMatrixMocks(page);
      await page.goto(`/${locale}/payments`);
      await expect(page.locator("html")).toHaveAttribute(
        "dir",
        locale === "fa" ? "rtl" : "ltr",
      );
      // Page shell renders (list may be empty under mocks)
      await expect(page.getByTestId("app-header")).toBeVisible();
      await expect(page).not.toHaveURL(/\/login/);
    });

    test(`tickets list smoke`, async ({ page }) => {
      await setupMatrixMocks(page);
      await page.goto(`/${locale}/tickets`);
      await expect(page.locator("html")).toHaveAttribute(
        "dir",
        locale === "fa" ? "rtl" : "ltr",
      );
      await expect(page.getByTestId("app-header")).toBeVisible();
      await expect(page).not.toHaveURL(/\/login/);
    });
  });
}
