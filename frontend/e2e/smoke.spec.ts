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
  "custody_reversals.review",
  "customer_prefix_mapping.view",
  "customer_prefix_mapping.manage",
  "customer_prefix_mapping.apply",
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
    "/en/custody-reversals",
    "/en/invoices",
    "/en/collector/payments/new",
    "/en/settings/customer-prefix-mappings",
    "/en/settings/mapping-cleanup",
    "/en/branches",
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

test.describe("customer prefix mapping shells", () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript((user) => {
      window.localStorage.setItem(
        "auth-storage",
        JSON.stringify({ state: { token: "e2e-mock-token", user }, version: 0 }),
      );
    }, {
      ...MOCK_USER,
      permissions: [
        ...ALL_PERMISSIONS,
        "customer_prefix_mapping.view",
        "customer_prefix_mapping.manage",
        "customer_prefix_mapping.apply",
      ],
    });
    await page.route("**/api/v1/**", async (route) => {
      const url = route.request().url();
      if (url.includes("/customer-prefix-mappings/test")) {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            success: true,
            data: {
              normalized: "KBL-001254",
              match: { prefix: "KBL", branch_id: 1, ambiguous: false },
              branch: { id: 1, code: "KABUL", name_en: "Kabul" },
              unknown_prefix: false,
            },
          }),
        });
        return;
      }
      if (url.includes("/customer-prefix-mappings/conflicts")) {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({ success: true, data: [] }),
        });
        return;
      }
      if (url.includes("/customer-prefix-mappings/report")) {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            success: true,
            data: {
              by_branch: [],
              unknown_prefixes: 0,
              missing_customer_numbers: 0,
              admin_overrides: 0,
              protected_history_pending: 0,
              open_conflicts: 0,
            },
          }),
        });
        return;
      }
      if (url.includes("/customer-prefix-mappings") && !url.includes("/test")) {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            success: true,
            data: [
              {
                id: 1,
                branch_id: 1,
                prefix: "KBL",
                normalized_prefix: "KBL",
                active: true,
                priority: 100,
                branch: { id: 1, code: "KABUL", name_en: "Kabul" },
                matched_customers_estimate: 3,
                examples: ["KBL-001254"],
              },
            ],
          }),
        });
        return;
      }
      if (url.includes("/branches/") && url.includes("/prefix-metrics")) {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            success: true,
            data: {
              prefixes: [{ normalized_prefix: "KBL", active: true }],
              matched_by_prefix: 3,
              open_conflicts: 0,
              unmapped_customers: 1,
              last_prefix_mapping_run: null,
            },
          }),
        });
        return;
      }
      if (url.includes("/branches")) {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            success: true,
            data: [
              {
                id: 1,
                code: "KABUL",
                name_en: "Kabul",
                name_fa: "کابل",
                province_en: "Kabul",
                province_fa: "کابل",
                receipt_prefix: "KBL",
                is_active: true,
              },
            ],
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

  test("prefix mappings page en", async ({ page }) => {
    await page.goto("/en/settings/customer-prefix-mappings");
    await expect(page.getByRole("heading", { name: "Customer prefix mappings" })).toBeVisible();
    await expect(page.getByText("KBL → Kabul")).toBeVisible();
  });

  test("prefix mappings page fa RTL", async ({ page }) => {
    await page.goto("/fa/settings/customer-prefix-mappings");
    await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
    await expect(page.getByRole("heading", { name: "نگاشت پیشوند شماره مشتری" })).toBeVisible();
  });

  test("test customer number control", async ({ page }) => {
    await page.goto("/en/settings/customer-prefix-mappings");
    await page.getByPlaceholder("KBL-001254").fill("KBL-001254");
    await page.getByRole("button", { name: /Test number/i }).click();
    await expect(page.getByText(/KBL-001254 → KBL/)).toBeVisible();
  });

  test("branch page shows prefix metrics", async ({ page }) => {
    await page.goto("/en/branches");
    await expect(page.getByText("Customer prefixes")).toBeVisible();
    await expect(page.getByText(/Matched 3/)).toBeVisible();
  });

  test("mobile admin viewport", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/en/settings/customer-prefix-mappings");
    await expect(page.getByText("Add prefix")).toBeVisible();
  });
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
