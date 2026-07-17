import { test, expect, type Page, type Route } from "@playwright/test";

const STAGE10_PERMISSIONS = [
  "dashboard.view",
  "services.view",
  "services.create",
  "services.update",
  "services.activate",
  "services.suspend",
  "services.reactivate",
  "services.cancel",
  "services.change",
  "services.relocate",
  "services.packages.view",
  "services.packages.manage",
  "services.locations.view",
  "services.contracts.view",
  "services.finance_holds.manage",
  "services.billing.view",
  "services.sla.manage",
  "services.dashboard.view",
  "services.noc.view",
  "services.reports.view",
  "customers.view",
  "branches.view",
];

const MOCK_USER = {
  id: 1,
  name: "Services Admin",
  email: "services@finance.mns.af",
  username: "services",
  locale: "en",
  status: "active",
  roles: ["Super Administrator"],
  permissions: STAGE10_PERMISSIONS,
  force_password_change: false,
  branches: [{ id: 1, code: "KBL", name_en: "Kabul", name_fa: "کابل" }],
};

const LIMITED_USER = {
  ...MOCK_USER,
  id: 2,
  name: "Limited",
  email: "limited@finance.mns.af",
  permissions: ["dashboard.view"],
  roles: ["Viewer"],
};

const MOCK_DASHBOARD = {
  total: 12,
  by_commercial_status: { active: 8, pending_activation: 2, suspended: 1, draft: 1 },
  by_operational_status: { online: 7, pending_install: 2, ready: 3 },
  active_mrr: 4500,
  suspended: 1,
  pending_activation: 2,
  expiring_30_days: 1,
  radius_enabled: false,
};

const MOCK_PACKAGE = {
  id: 5,
  code: "PKG-FIBER-50",
  name: "Fiber 50",
  service_type_code: "internet",
  access_technology_code: "ftth",
  download_mbps: 50,
  upload_mbps: 20,
  monthly_price: 1200,
  is_active: true,
  versions: [{ id: 1, package_id: 5, monthly_price: 1200, is_current: true }],
};

const MOCK_SERVICE = {
  id: 40,
  service_number: "KBL-SVC-000040",
  customer_id: 9,
  branch_id: 1,
  package_id: 5,
  service_type_code: "internet",
  access_technology_code: "ftth",
  mrr: 1200,
  commercial_status: "pending_activation",
  operational_status: "ready",
  billing_status: "not_billed",
  customer: { id: 9, contact_name: "Ahmad Customer", customer_number: "C-9" },
  package: MOCK_PACKAGE,
  location: { id: 3, name: "Home site" },
  allowed_commercial_transitions: ["active", "cancelled", "draft"],
  allowed_operational_transitions: ["online", "offline", "suspended", "decommissioned"],
};

const MOCK_BILLING = {
  service_id: 40,
  service_number: "KBL-SVC-000040",
  customer_id: 9,
  billing_status: "not_billed",
  mrr: 1200,
  source_of_truth: "zoho",
  read_only: true,
  invoices: [],
  payments: [],
  note: "read-only",
};

async function fulfillJson(route: Route, data: unknown, meta?: Record<string, number>, status = 200) {
  await route.fulfill({
    status,
    contentType: "application/json",
    body: JSON.stringify({
      success: true,
      data,
      ...(meta ? { meta } : {}),
    }),
  });
}

async function mockStage10Api(page: Page, user = MOCK_USER) {
  await page.addInitScript((u) => {
    window.localStorage.setItem(
      "auth-storage",
      JSON.stringify({ state: { token: "e2e-stage10-token", user: u }, version: 0 }),
    );
  }, user);

  const packagesById: Record<number, typeof MOCK_PACKAGE> = {
    [MOCK_PACKAGE.id]: { ...MOCK_PACKAGE },
  };
  const servicesById: Record<number, typeof MOCK_SERVICE> = {
    [MOCK_SERVICE.id]: { ...MOCK_SERVICE },
  };

  await page.route("**/api/v1/**", async (route) => {
    const req = route.request();
    const method = req.method();
    const url = req.url();

    if (url.includes("/auth/me") || url.endsWith("/me")) {
      await fulfillJson(route, user);
      return;
    }

    if (url.includes("/services/dashboard")) {
      await fulfillJson(route, MOCK_DASHBOARD);
      return;
    }

    if (url.includes("/services/noc-workspace")) {
      await fulfillJson(route, {
        attention_queue: [{ ...MOCK_SERVICE, commercial_status: "active", operational_status: "offline" }],
        pending_activation: [MOCK_SERVICE],
        radius_automation: false,
        note: "NOC workspace is operational lifecycle only — no Radius provisioning.",
      });
      return;
    }

    if (url.includes("/services/reports/status")) {
      await fulfillJson(route, {
        commercial: [{ commercial_status: "active", count: 8, mrr: 4500 }],
        operational: [{ operational_status: "online", count: 7 }],
        billing: [{ billing_status: "current", count: 6 }],
        by_type: [{ service_type_code: "internet", count: 10 }],
      });
      return;
    }

    if (url.includes("/service-types")) {
      await fulfillJson(route, [{ id: 1, code: "internet", name: "Internet", is_active: true }]);
      return;
    }

    if (url.includes("/service-access-technologies")) {
      await fulfillJson(route, [{ id: 1, code: "ftth", name: "FTTH", is_active: true }]);
      return;
    }

    if (url.includes("/service-sla-templates")) {
      await fulfillJson(route, [{ id: 1, code: "std", name: "Standard", response_minutes: 60, resolve_minutes: 240 }]);
      return;
    }

    if (url.includes("/service-locations")) {
      await fulfillJson(route, [{ id: 3, name: "Home site", customer_id: 9, branch_id: 1 }], {
        current_page: 1,
        last_page: 1,
        total: 1,
        per_page: 100,
      });
      return;
    }

    if (url.includes("/service-contracts")) {
      await fulfillJson(route, [], { current_page: 1, last_page: 1, total: 0, per_page: 50 });
      return;
    }

    if (url.includes("/service-packages") && method === "POST" && !url.includes("/versions")) {
      const body = req.postDataJSON() as Record<string, unknown>;
      const created = {
        ...MOCK_PACKAGE,
        id: 99,
        code: String(body.code || "PKG-NEW"),
        name: String(body.name || "New Package"),
      };
      packagesById[99] = created;
      await fulfillJson(route, created, undefined, 201);
      return;
    }

    if (url.includes("/service-packages/") && url.includes("/versions") && method === "POST") {
      await fulfillJson(route, { id: 2, package_id: 5, monthly_price: 1300, is_current: true }, undefined, 201);
      return;
    }

    if (url.includes("/service-packages") && method === "GET") {
      await fulfillJson(route, Object.values(packagesById), {
        current_page: 1,
        last_page: 1,
        total: Object.keys(packagesById).length,
        per_page: 100,
      });
      return;
    }

    if (url.includes("/inventory/customer-equipment")) {
      await fulfillJson(route, [], { current_page: 1, last_page: 1, total: 0, per_page: 50 });
      return;
    }

    if (url.match(/\/services\/\d+\/activate/) && method === "POST") {
      const idMatch = url.match(/\/services\/(\d+)\/activate/);
      const id = idMatch ? Number(idMatch[1]) : MOCK_SERVICE.id;
      servicesById[id] = {
        ...servicesById[id],
        commercial_status: "active",
        operational_status: "online",
        billing_status: "current",
      };
      await fulfillJson(route, servicesById[id]);
      return;
    }

    if (url.match(/\/services\/\d+\/suspend/) && method === "POST") {
      const idMatch = url.match(/\/services\/(\d+)\/suspend/);
      const id = idMatch ? Number(idMatch[1]) : MOCK_SERVICE.id;
      servicesById[id] = {
        ...servicesById[id],
        commercial_status: "suspended",
        operational_status: "suspended",
      };
      await fulfillJson(route, servicesById[id]);
      return;
    }

    if (url.match(/\/services\/\d+\/finance-holds/) && method === "POST") {
      const idMatch = url.match(/\/services\/(\d+)\/finance-holds/);
      const id = idMatch ? Number(idMatch[1]) : MOCK_SERVICE.id;
      servicesById[id] = { ...servicesById[id], billing_status: "on_hold" };
      await fulfillJson(route, { id: 1, service_id: id, status: "active", reason: "overdue" }, undefined, 201);
      return;
    }

    if (url.match(/\/services\/\d+\/billing/)) {
      await fulfillJson(route, MOCK_BILLING);
      return;
    }

    if (url.match(/\/services\/\d+\/timeline/)) {
      await fulfillJson(route, [
        {
          type: "status_transition",
          at: "2026-07-17T10:00:00Z",
          data: { from_commercial: "draft", to_commercial: "pending_activation", reason: "ready" },
        },
      ]);
      return;
    }

    if (url.includes("/services") && method === "POST" && !url.match(/\/services\/\d+/)) {
      const body = req.postDataJSON() as Record<string, unknown>;
      const created = {
        ...MOCK_SERVICE,
        id: 41,
        service_number: "KBL-SVC-000041",
        customer_id: Number(body.customer_id || 9),
        branch_id: Number(body.branch_id || 1),
        package_id: body.package_id ? Number(body.package_id) : 5,
        commercial_status: "draft",
        operational_status: "not_provisioned",
        mrr: body.mrr ?? 1200,
      };
      servicesById[41] = created;
      await fulfillJson(route, created, undefined, 201);
      return;
    }

    const serviceMatch = url.match(/\/services\/(\d+)(?:\?|$)/);
    if (serviceMatch && method === "GET" && !url.includes("/dashboard")) {
      const id = Number(serviceMatch[1]);
      await fulfillJson(route, servicesById[id] ?? { ...MOCK_SERVICE, id });
      return;
    }

    if (url.includes("/services") && method === "GET") {
      const u = new URL(url);
      let rows = Object.values(servicesById);
      const commercial = u.searchParams.get("commercial_status");
      const operational = u.searchParams.get("operational_status");
      const billing = u.searchParams.get("billing_status");
      if (commercial) rows = rows.filter((r) => r.commercial_status === commercial);
      if (operational) rows = rows.filter((r) => r.operational_status === operational);
      if (billing) rows = rows.filter((r) => r.billing_status === billing);
      await fulfillJson(route, rows, {
        current_page: 1,
        last_page: 1,
        total: rows.length,
        per_page: 15,
      });
      return;
    }

    if (url.includes("/pickers/")) {
      if (url.includes("/pickers/customers")) {
        await fulfillJson(route, [
          {
            id: 9,
            contact_name: "Ahmad Customer",
            customer_number: "C-9",
            company_name: "Ahmad Co",
            branch_id: 1,
          },
        ]);
        return;
      }
      if (url.includes("/pickers/branches")) {
        await fulfillJson(route, [
          { id: 1, code: "KBL", name_en: "Kabul", name_fa: "کابل" },
        ]);
        return;
      }
      await fulfillJson(route, []);
      return;
    }

    if (url.includes("/branches") && method === "GET") {
      await fulfillJson(route, [
        { id: 1, code: "KBL", name_en: "Kabul", name_fa: "کابل" },
      ]);
      return;
    }

    await fulfillJson(route, {});
  });
}

test.describe("Stage 10 service lifecycle", () => {
  test("dashboard shows lifecycle metrics and radius deferred", async ({ page }) => {
    await mockStage10Api(page);
    await page.goto("/en/services/dashboard");
    await expect(page.getByTestId("services-dashboard")).toBeVisible();
    await expect(page.getByText(/Radius automation is deferred/i)).toBeVisible();
    await expect(page.getByRole("link", { name: /Total services/i })).toContainText("12");
  });

  test("create service navigates to detail", async ({ page }) => {
    await mockStage10Api(page);
    await page.goto("/en/services/new");
    await expect(page.getByTestId("new-service-page")).toBeVisible();

    const branchInput = page.getByRole("combobox", { name: /^Branch$/i });
    await branchInput.click();
    await expect(page.getByRole("option", { name: /Kabul/i })).toBeVisible();
    await page.getByRole("option", { name: /Kabul/i }).click();

    const customerInput = page.getByRole("combobox", { name: /^Customer$/i });
    await customerInput.click();
    await customerInput.fill("Ahm");
    await expect(page.getByRole("option", { name: /Ahmad/i })).toBeVisible();
    await page.getByRole("option", { name: /Ahmad/i }).click();

    await page.getByTestId("service-mrr").fill("1500");
    await page.getByTestId("create-service").click();
    await expect(page).toHaveURL(/\/services\/41/);
    await expect(page.getByTestId("service-detail")).toBeVisible();
  });

  test("activate service from command center", async ({ page }) => {
    await mockStage10Api(page);
    await page.goto("/en/services/40");
    await expect(page.getByTestId("service-detail")).toBeVisible();
    await page.getByRole("button", { name: /Activate/i }).click();
    await page.getByRole("button", { name: /Confirm/i }).click();
    await expect(page.getByText(/Service activated/i)).toBeVisible();
  });

  test("suspend service requires reason", async ({ page }) => {
    await mockStage10Api(page);
    // Start from active service
    await page.route("**/api/v1/services/40", async (route) => {
      if (route.request().method() === "GET") {
        await fulfillJson(route, { ...MOCK_SERVICE, commercial_status: "active", operational_status: "online" });
        return;
      }
      await route.fallback();
    });
    await page.goto("/en/services/40");
    await page.getByRole("button", { name: /Suspend/i }).click();
    await page.locator("textarea").fill("Non-payment");
    await page.getByRole("button", { name: /Confirm/i }).click();
    await expect(page.getByText(/Service suspended/i)).toBeVisible();
  });

  test("package list and finance hold queue", async ({ page }) => {
    await mockStage10Api(page);
    await page.goto("/en/services/packages");
    await expect(page.getByTestId("services-packages")).toBeVisible();
    await expect(page.getByRole("link", { name: "PKG-FIBER-50" }).first()).toBeVisible();

    await page.goto("/en/services/40?tab=hold");
    await page.getByTestId("hold-reason").fill("Overdue invoices");
    await page.getByRole("button", { name: /Place finance hold/i }).click();
    await expect(page.getByText(/Finance hold placed/i)).toBeVisible();

    await page.goto("/en/services/finance-hold");
    await expect(page.getByTestId("services-finance-hold")).toBeVisible();
  });

  test("NOC services workspace", async ({ page }) => {
    await mockStage10Api(page);
    await page.goto("/en/noc/services");
    await expect(page.getByTestId("noc-services")).toBeVisible();
    await expect(page.getByText(/Radius automation is deferred/i)).toBeVisible();
    await expect(page.getByText("KBL-SVC-000040").first()).toBeVisible();
  });

  test("RTL fa locale renders services dashboard", async ({ page }) => {
    await mockStage10Api(page);
    await page.goto("/fa/services/dashboard");
    await expect(page.getByTestId("services-dashboard")).toBeVisible();
    await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
  });

  test("permission denial on new service", async ({ page }) => {
    await mockStage10Api(page, LIMITED_USER);
    await page.goto("/en/services/new");
    await expect(page.getByTestId("permission-denied")).toBeVisible();
  });
});
