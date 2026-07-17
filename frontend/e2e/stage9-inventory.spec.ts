import { test, expect, type Page, type Route } from "@playwright/test";

const STAGE9_PERMISSIONS = [
  "dashboard.view",
  "inventory.dashboard.view",
  "inventory.products.view",
  "inventory.products.manage",
  "inventory.stock.view",
  "inventory.equipment.view",
  "inventory.equipment.manage",
  "inventory.reservations.view",
  "inventory.reservations.manage",
  "inventory.transfers.view",
  "inventory.transfers.manage",
  "inventory.transfers.approve",
  "inventory.receiving.manage",
  "inventory.stock.count",
  "inventory.sites.view",
  "inventory.assets.view",
  "inventory.reports.view",
  "inventory.purchasing.view",
  "inventory.suppliers.view",
  "inventory.locations.view",
];

const MOCK_USER = {
  id: 1,
  name: "Inventory Admin",
  email: "inventory@finance.mns.af",
  username: "inventory",
  locale: "en",
  status: "active",
  roles: ["Super Administrator"],
  permissions: STAGE9_PERMISSIONS,
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
  on_hand: 100,
  reserved: 10,
  available: 90,
  in_transit: 5,
  low_stock_products: 2,
  open_reservations: 1,
  open_transfers: 1,
  equipment_in_stock: 8,
  equipment_installed: 3,
};

const MOCK_PRODUCT = {
  id: 10,
  code: "PRD-000010",
  name_en: "Fiber Patch Cord",
  tracking_mode: "quantity",
  is_active: true,
  uom: "pcs",
};

const MOCK_LOCATION = {
  id: 1,
  code: "WH-01",
  name: "Main Warehouse",
  type: "warehouse",
  branch_id: 1,
};

const MOCK_LOCATION_2 = {
  id: 2,
  code: "WH-02",
  name: "Branch Store",
  type: "warehouse",
  branch_id: 1,
};

let MOCK_RESERVATION = {
  id: 20,
  reservation_number: "RSV-000020",
  branch_id: 1,
  status: "draft",
  location_id: 1,
  items: [{ id: 1, reservation_id: 20, product_id: 10, qty: 2, product: MOCK_PRODUCT }],
};

type MockTransfer = {
  id: number;
  transfer_number: string;
  branch_id: number;
  from_location_id: number;
  to_location_id: number;
  status: string;
  items: Array<{ id: number; transfer_id: number; product_id: number; qty: number; product: typeof MOCK_PRODUCT }>;
};

const transfersById: Record<number, MockTransfer> = {
  30: {
    id: 30,
    transfer_number: "TRF-000030",
    branch_id: 1,
    from_location_id: 1,
    to_location_id: 2,
    status: "dispatched",
    items: [{ id: 1, transfer_id: 30, product_id: 10, qty: 5, product: MOCK_PRODUCT }],
  },
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

async function mockStage9Api(page: Page, user = MOCK_USER) {
  await page.addInitScript((u) => {
    window.localStorage.setItem(
      "auth-storage",
      JSON.stringify({ state: { token: "e2e-stage9-token", user: u }, version: 0 }),
    );
  }, user);

  const productsById: Record<number, typeof MOCK_PRODUCT> = {
    [MOCK_PRODUCT.id]: { ...MOCK_PRODUCT },
  };

  await page.route("**/api/v1/**", async (route) => {
    const req = route.request();
    const method = req.method();
    const url = req.url();

    if (url.includes("/auth/me") || url.endsWith("/me")) {
      await fulfillJson(route, user);
      return;
    }

    if (url.includes("/inventory/dashboard")) {
      await fulfillJson(route, MOCK_DASHBOARD);
      return;
    }

    if (url.includes("/inventory/locations")) {
      await fulfillJson(route, [MOCK_LOCATION, MOCK_LOCATION_2], {
        current_page: 1,
        last_page: 1,
        total: 2,
        per_page: 100,
      });
      return;
    }

    if (url.includes("/inventory/products") && method === "POST") {
      const body = req.postDataJSON() as Record<string, unknown>;
      const created = {
        ...MOCK_PRODUCT,
        id: 99,
        code: "PRD-000099",
        name_en: String(body.name_en || "New Product"),
        tracking_mode: String(body.tracking_mode || "quantity"),
        barcode: body.barcode ? String(body.barcode) : null,
      };
      productsById[99] = created;
      await fulfillJson(route, created, undefined, 201);
      return;
    }

    const productMatch = url.match(/\/inventory\/products\/(\d+)/);
    if (productMatch && method === "GET") {
      const id = Number(productMatch[1]);
      await fulfillJson(route, productsById[id] ?? { ...MOCK_PRODUCT, id });
      return;
    }

    if (url.includes("/inventory/products") && method === "GET") {
      await fulfillJson(route, Object.values(productsById), {
        current_page: 1,
        last_page: 1,
        total: Object.keys(productsById).length,
        per_page: 15,
      });
      return;
    }

    if (url.includes("/inventory/goods-receipts") && method === "POST") {
      await fulfillJson(route, { id: 50, receipt_number: "GRN-000050", branch_id: 1, location_id: 1 }, undefined, 201);
      return;
    }

    if (url.includes("/inventory/equipment/receive") && method === "POST") {
      const body = req.postDataJSON() as Record<string, unknown>;
      await fulfillJson(
        route,
        {
          id: 70,
          equipment_number: "EQ-000070",
          serial_number: String(body.serial_number || "SN-1"),
          product_id: Number(body.product_id || 10),
          branch_id: 1,
          location_id: 1,
          status: "in_stock",
          product: MOCK_PRODUCT,
        },
        undefined,
        201,
      );
      return;
    }

    if (url.includes("/inventory/reservations") && method === "POST" && !url.match(/\/reservations\/\d+/)) {
      MOCK_RESERVATION = {
        ...MOCK_RESERVATION,
        id: 21,
        reservation_number: "RSV-000021",
        status: "draft",
      };
      await fulfillJson(route, MOCK_RESERVATION, undefined, 201);
      return;
    }

    if (url.includes("/inventory/reservations/") && url.includes("/reserve") && method === "POST") {
      MOCK_RESERVATION = { ...MOCK_RESERVATION, status: "approved" };
      await fulfillJson(route, MOCK_RESERVATION);
      return;
    }

    const rsvMatch = url.match(/\/inventory\/reservations\/(\d+)/);
    if (rsvMatch && method === "GET") {
      await fulfillJson(route, { ...MOCK_RESERVATION, id: Number(rsvMatch[1]) });
      return;
    }

    if (url.includes("/inventory/reservations") && method === "GET") {
      await fulfillJson(route, [MOCK_RESERVATION], {
        current_page: 1,
        last_page: 1,
        total: 1,
        per_page: 15,
      });
      return;
    }

    if (url.includes("/inventory/transfers") && method === "POST" && !url.match(/\/transfers\/\d+/)) {
      const created = {
        id: 31,
        transfer_number: "TRF-000031",
        branch_id: 1,
        from_location_id: 1,
        to_location_id: 2,
        status: "draft",
        items: [{ id: 1, transfer_id: 31, product_id: 10, qty: 1, product: MOCK_PRODUCT }],
      };
      transfersById[31] = created;
      await fulfillJson(route, created, undefined, 201);
      return;
    }

    if (url.includes("/inventory/transfers/") && url.includes("/receive") && method === "POST") {
      const idMatch = url.match(/\/transfers\/(\d+)\/receive/);
      const id = idMatch ? Number(idMatch[1]) : 30;
      transfersById[id] = { ...(transfersById[id] || transfersById[30]), status: "received" };
      await fulfillJson(route, transfersById[id]);
      return;
    }

    const trfMatch = url.match(/\/inventory\/transfers\/(\d+)/);
    if (trfMatch && method === "GET") {
      const id = Number(trfMatch[1]);
      await fulfillJson(route, transfersById[id] ?? { ...transfersById[30], id });
      return;
    }

    if (url.includes("/inventory/transfers") && method === "GET") {
      const rows = Object.values(transfersById);
      await fulfillJson(route, rows, {
        current_page: 1,
        last_page: 1,
        total: rows.length,
        per_page: 15,
      });
      return;
    }

    if (url.includes("/inventory/stock/balances")) {
      await fulfillJson(
        route,
        [
          {
            id: 1,
            branch_id: 1,
            product_id: 10,
            location_id: 1,
            on_hand: 50,
            reserved: 5,
            available: 45,
            product: MOCK_PRODUCT,
            location: MOCK_LOCATION,
          },
        ],
        { current_page: 1, last_page: 1, total: 1, per_page: 25 },
      );
      return;
    }

    if (url.includes("/pickers/") || url.includes("/branches")) {
      await fulfillJson(route, [
        { id: 1, label: "Kabul", code: "KBL", name_en: "Kabul", name_fa: "کابل" },
      ], { current_page: 1, last_page: 1, total: 1, per_page: 15 });
      return;
    }

    // default empty success for other inventory GETs
    if (method === "GET" && url.includes("/inventory/")) {
      await fulfillJson(route, [], {
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: 15,
      });
      return;
    }

    await fulfillJson(route, {});
  });
}

test.describe("Stage 9 inventory", () => {
  test("dashboard loads metrics", async ({ page }) => {
    await mockStage9Api(page);
    await page.goto("/en/inventory/dashboard");
    await expect(page.getByTestId("inventory-dashboard")).toBeVisible();
    await expect(page.getByText("On hand")).toBeVisible();
    await expect(page.getByText("100")).toBeVisible();
  });

  test("create product", async ({ page }) => {
    await mockStage9Api(page);
    await page.goto("/en/inventory/products/new");
    await expect(page.getByTestId("new-product-page")).toBeVisible();
    await page.getByTestId("product-name").fill("Cat6 Cable");
    await page.getByTestId("product-barcode").fill("BC-123");
    await page.getByTestId("save-product").click();
    await expect(page).toHaveURL(/\/inventory\/products\/99/);
    await expect(page.getByTestId("product-detail")).toBeVisible();
  });

  test("receive quantity and serial", async ({ page }) => {
    await mockStage9Api(page);
    await page.goto("/en/inventory/receiving");
    await expect(page.getByTestId("inventory-receiving")).toBeVisible();

    await page.getByTestId("receive-location").selectOption("1");
    await page.getByTestId("receive-product").selectOption("10");
    await page.getByTestId("receive-qty").fill("3");
    await page.getByTestId("receive-submit").click();
    await expect(page.getByText("Quantity received into stock.")).toBeVisible();

    await page.getByTestId("receive-mode-serial").click();
    await page.getByTestId("receive-location").selectOption("1");
    await page.getByTestId("receive-product").selectOption("10");
    await page.getByTestId("receive-serial").fill("SN-ABC-001");
    await page.getByTestId("receive-barcode").fill("SCAN-001");
    await page.getByTestId("receive-submit").click();
    await expect(page.getByText("Serialized equipment received.")).toBeVisible();
  });

  test("create and reserve stock", async ({ page }) => {
    await mockStage9Api(page);
    await page.goto("/en/inventory/reservations");
    await page.getByTestId("new-reservation").click();
    await expect(page.getByTestId("create-reservation-form")).toBeVisible();
    await page.getByTestId("reservation-location").selectOption("1");
    await page.getByTestId("reservation-product").selectOption("10");
    await page.getByTestId("reservation-qty").fill("2");
    await page.getByTestId("save-reservation").click();
    await expect(page).toHaveURL(/\/inventory\/reservations\/21/);
    await page.getByTestId("reserve-action").click();
    await expect(page.getByText("approved", { exact: false })).toBeVisible();
  });

  test("create transfer and receive", async ({ page }) => {
    await mockStage9Api(page);
    await page.goto("/en/inventory/transfers");
    await page.getByTestId("new-transfer").click();
    await page.getByTestId("transfer-from").selectOption("1");
    await page.getByTestId("transfer-to").selectOption("2");
    await page.getByTestId("transfer-product").selectOption("10");
    await page.getByTestId("save-transfer").click();
    await expect(page).toHaveURL(/\/inventory\/transfers\/31/);

    // Force dispatched state for receive button by navigating to mocked dispatched transfer
    await page.goto("/en/inventory/transfers/30");
    await expect(page.getByTestId("transfer-detail")).toBeVisible();
    await page.getByTestId("transfer-receive").click();
    await expect(page.getByText("received", { exact: false })).toBeVisible();
  });

  test("Persian RTL inventory dashboard", async ({ page }) => {
    await mockStage9Api(page);
    await page.goto("/fa/inventory/dashboard");
    await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
    await expect(page.getByTestId("inventory-dashboard")).toBeVisible();
    await expect(page.getByText("داشبورد انبار")).toBeVisible();
  });

  test("permission denial on receiving", async ({ page }) => {
    await mockStage9Api(page, LIMITED_USER);
    await page.goto("/en/inventory/receiving");
    await expect(page.getByTestId("permission-denied")).toBeVisible();
    await expect(page.getByText(/do not have permission/i)).toBeVisible();
  });
});
