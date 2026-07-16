import { test, expect } from "@playwright/test";

const locales = ["en", "fa"] as const;

const ALL_PERMISSIONS = [
  "dashboard.view",
  "zoho.view",
  "zoho.configure",
  "customers.view",
  "debtors.view",
  "invoices.view",
  "assignments.view",
  "routes.view",
  "visits.view",
  "promises.view",
  "payments.view",
  "payments.create",
  "payments.manage",
  "payments.retry_sync",
  "receipts.view",
  "reversals.request",
  "reversals.approve",
  "wallets.view",
  "handovers.view",
  "handovers.review",
  "handovers.create",
  "handovers.submit",
  "cashboxes.view",
  "cashbox_transfers.view",
  "bank_deposits.view",
  "cash_reconciliation.view",
  "branches.view",
  "branches.manage",
  "users.view",
  "users.manage",
  "roles.view",
  "settings.manage",
  "audit.view",
  "notifications.view",
];

const MOCK_USER = {
  id: 1,
  name: "Admin",
  email: "admin@finance.mns.af",
  username: "admin",
  locale: "en",
  status: "active",
  roles: ["Super Administrator"],
  permissions: ALL_PERMISSIONS,
  force_password_change: false,
  branches: [],
};

test.describe("public shells", () => {
  for (const locale of locales) {
    test(`${locale} login page renders`, async ({ page }) => {
      await page.goto(`/${locale}/login`);
      await expect(page.locator("body")).toBeVisible();
      const html = page.locator("html");
      if (locale === "fa") {
        await expect(html).toHaveAttribute("dir", "rtl");
      } else {
        await expect(html).toHaveAttribute("dir", "ltr");
      }
    });
  }
});

test.describe("mocked authenticated shells", () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript((user) => {
      window.localStorage.setItem(
        "auth-storage",
        JSON.stringify({ state: { token: "e2e-mock-token", user }, version: 0 }),
      );
    }, MOCK_USER);

    await page.route("**/api/v1/**", async (route) => {
      const url = route.request().url();
      if (url.includes("/auth/me")) {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({ success: true, data: MOCK_USER }),
        });
        return;
      }
      if (url.includes("/dashboard/summary")) {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            success: true,
            data: {
              role: "super_admin",
              customers: { total: 0, unmapped: 0 },
              invoices: { total: 0, open: 0, overdue: 0, outstanding_amount: 0 },
              generated_at: new Date().toISOString(),
            },
          }),
        });
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ success: true, data: [] }),
      });
    });
  });

  test("en dashboard shell loads", async ({ page }) => {
    await page.goto("/en/dashboard");
    await expect(page.locator("body")).toBeVisible();
    // Desktop shows aside nav; mobile shows Menu toggle.
    const desktopNav = page.locator("aside nav");
    const mobileMenu = page.getByRole("button", { name: /^Menu$/i });
    await expect(desktopNav.or(mobileMenu).first()).toBeAttached({ timeout: 15000 });
  });

  test("fa dashboard RTL shell loads", async ({ page }) => {
    await page.goto("/fa/dashboard");
    await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
  });

  for (const path of [
    "/en/zoho/sync-health",
    "/en/zoho/location-mapping",
    "/en/zoho/mapping-conflicts",
    "/en/transfers",
    "/en/bank-deposits",
    "/en/reconciliation",
    "/en/alerts",
  ]) {
    test(`route ${path} renders without crash`, async ({ page }) => {
      await page.goto(path);
      await expect(page.locator("body")).toBeVisible();
    });
  }
});

test("health endpoint is reachable via same host when configured", async ({
  request,
}) => {
  const base = process.env.PLAYWRIGHT_API_BASE;
  test.skip(!base, "PLAYWRIGHT_API_BASE not set");
  const res = await request.get(`${base}/api/v1/health`);
  expect(res.ok()).toBeTruthy();
});

test.describe("stage 5.2 ownership shells", () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript((user) => {
      window.localStorage.setItem(
        "auth-storage",
        JSON.stringify({ state: { token: "e2e-mock-token", user }, version: 0 }),
      );
    }, {
      id: 1, name: "Admin", email: "admin@finance.mns.af", username: "admin", locale: "en", status: "active",
      roles: ["Super Administrator"], permissions: [
        "dashboard.view","customer_ownership.view","customer_ownership.create","temporary_assignments.view",
        "branch_payment_mapping.view","receivables_dashboard.view","ownership_conflicts.view",
        "zoho.view","zoho.configure","assignments.view"
      ], force_password_change: false, branches: [],
    });
    await page.route("**/api/v1/**", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: [] }) });
    });
  });
  for (const path of [
    "/en/customer-ownership",
    "/en/temporary-assignments",
    "/en/settings/branch-payment-mappings",
    "/en/reports/branch-receivables",
    "/en/collector/permanent-customers",
    "/en/collector/debtors",
  ]) {
    test(`route ${path}`, async ({ page }) => {
      await page.goto(path);
      await expect(page.locator("body")).toBeVisible();
    });
  }
});
