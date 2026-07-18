/**
 * Stage 10.4 real acceptance — write-path workflows with DB asserts.
 * Required (no silent skip). Optional Zoho live write stays in acceptance.spec.ts.
 */
import { test, expect } from "@playwright/test";
import {
  attachFailureGuards,
  assertDb,
  apiLogin,
  authedGet,
  authedPost,
  ensureActiveAssignment,
  expectNoHorizontalOverflow,
  login,
} from "./helpers/acceptance";

function localeFromProject(name: string): "en" | "fa" {
  return name.includes("-fa") ? "fa" : "en";
}

async function fixtureIds(page: import("@playwright/test").Page, token: string) {
  const customers = await authedGet(page, token, "/api/v1/customers?search=ACC-CUST-001");
  expect(customers.ok()).toBeTruthy();
  const customerRows = ((await customers.json()).data || []) as Array<Record<string, unknown>>;
  const customer =
    customerRows.find((c) => c.zoho_contact_id === "ACCEPTANCE-ZOHO-1") || customerRows[0];
  expect(customer?.id).toBeTruthy();

  const collectors = await authedGet(page, token, "/api/v1/collectors?per_page=50");
  expect(collectors.ok(), await collectors.text()).toBeTruthy();
  const collectorRows = ((await collectors.json()).data || []) as Array<{
    id: number;
    employee_code?: string;
    user?: { name?: string };
  }>;
  const collector =
    collectorRows.find((c) => c.employee_code === "ACC-COL-1") ||
    collectorRows.find((c) => /ACCEPTANCE/i.test(c.user?.name || "")) ||
    collectorRows[0];
  expect(collector?.id, "acceptance collector fixture").toBeTruthy();

  const open = await authedGet(page, token, `/api/v1/invoices?customer_id=${customer.id}&per_page=50`);
  expect(open.ok(), await open.text()).toBeTruthy();
  const invoiceRows = ((await open.json()).data || []) as Array<{ id: number; balance?: string }>;
  const invoice = invoiceRows.find((r) => Number(r.balance || 0) > 0) || invoiceRows[0];
  expect(invoice?.id, "acceptance open invoice").toBeTruthy();

  const methods = await authedGet(page, token, "/api/v1/payment-methods");
  expect(methods.ok(), await methods.text()).toBeTruthy();
  const methodRows = ((await methods.json()).data || []) as Array<{ id: number; code?: string }>;
  const cash = methodRows.find((m) => m.code === "cash") || methodRows[0];
  expect(cash?.id).toBeTruthy();

  return {
    customerId: Number(customer.id),
    collectorId: Number(collector!.id),
    invoiceId: Number(invoice!.id),
    paymentMethodId: Number(cash!.id),
    branchId: Number(customer.branch_id || 1),
  };
}

test.describe("Stage 10.4 acceptance workflows", () => {
  test("auth edges: force password, no branch, logout, unauthorized route", async ({
    page,
  }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);

    const forced = await page.request.post("/api/v1/auth/login", {
      data: {
        login: "ACCEPTANCE-forcepw",
        password: process.env.E2E_PASSWORD,
        device_name: "acceptance-forcepw",
      },
    });
    expect(forced.ok(), await forced.text()).toBeTruthy();
    const forcedBody = await forced.json();
    expect(forcedBody.data?.force_password_change || forcedBody.data?.user?.force_password_change).toBeTruthy();

    const nobranch = await page.request.post("/api/v1/auth/login", {
      data: {
        login: "ACCEPTANCE-nobranch",
        password: process.env.E2E_PASSWORD,
        device_name: "acceptance-nobranch",
      },
    });
    // May succeed with empty branches; must not 500.
    expect([200, 401, 403, 422]).toContain(nobranch.status());

    // Use a dedicated token so logout does not invalidate the suite-wide admin token.
    const sessionToken = await apiLogin(page, process.env.E2E_USER || "ACCEPTANCE-admin");
    const logout = await authedPost(page, sessionToken, "/api/v1/auth/logout");
    expect(logout.ok()).toBeTruthy();
    await page.goto(`/${locale}/login`);
    await page.evaluate(() => localStorage.removeItem("auth-storage"));

    await page.goto(`/${locale}/settings/system-version`);
    // After logout, app should bounce to login or 403 — not a blank crash.
    await expect
      .poll(async () => {
        const url = page.url();
        if (/\/login/.test(url)) return "login";
        if (/\/403/.test(url)) return "403";
        const text = await page.locator("body").innerText();
        if (/sign in|ورود|unauthor|forbidden|403/i.test(text)) return "denied";
        return "";
      }, { timeout: 20_000 })
      .not.toEqual("");

    await guards.flush();
  });

  test("collections assignment accept route and visit", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const adminToken = await login(page, { locale });
    const ids = await fixtureIds(page, adminToken);

    const assignmentId = await ensureActiveAssignment(
      page,
      adminToken,
      ids.customerId,
      ids.collectorId,
    );
    await assertDb(page, adminToken, {
      entity: "assignment",
      key: "id",
      value: assignmentId,
      expect: { is_active: 1 },
    });

    const collectorToken = await apiLogin(page, "ACCEPTANCE-collector");
    const accept = await authedPost(page, collectorToken, `/api/v1/assignments/${assignmentId}/accept`);
    expect(accept.ok(), await accept.text()).toBeTruthy();

    const route = await authedPost(page, adminToken, "/api/v1/routes", {
      branch_id: ids.branchId,
      collector_id: ids.collectorId,
      name: `ACCEPTANCE route ${Date.now()}`,
      route_date: new Date().toISOString().slice(0, 10),
      stops: [{ customer_id: ids.customerId, sequence: 1 }],
    });
    expect(route.ok(), await route.text()).toBeTruthy();
    const routeId = (await route.json()).data?.id;
    expect(routeId).toBeTruthy();

    expect((await authedPost(page, adminToken, `/api/v1/routes/${routeId}/publish`)).ok()).toBeTruthy();
    expect(
      (await authedPost(page, collectorToken, `/api/v1/routes/${routeId}/start`)).ok(),
    ).toBeTruthy();

    const visit = await authedPost(page, collectorToken, "/api/v1/visits", {
      assignment_id: assignmentId,
      customer_id: ids.customerId,
      outcome: "customer_met",
      notes: "ACCEPTANCE visit",
      latitude: 34.5554,
      longitude: 69.2076,
    });
    expect(visit.ok(), await visit.text()).toBeTruthy();

    expect(
      (await authedPost(page, collectorToken, `/api/v1/routes/${routeId}/complete`)).ok(),
    ).toBeTruthy();

    await page.goto(`/${locale}/assignments`);
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });
    await expectNoHorizontalOverflow(page);
    await guards.flush();
  });

  test("payments draft confirm reverse with test adapter", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const adminToken = await login(page, { locale });
    const ids = await fixtureIds(page, adminToken);
    await ensureActiveAssignment(page, adminToken, ids.customerId, ids.collectorId);

    const collectorToken = await apiLogin(page, "ACCEPTANCE-collector");
    const amount = "10.0000";
    const idempotency = `acc-pay-${testInfo.project.name}-${Date.now()}`;
    const payload = {
      customer_id: ids.customerId,
      collector_id: ids.collectorId,
      payment_method_id: ids.paymentMethodId,
      amount,
      currency: "AFN",
      allocations: [{ invoice_id: ids.invoiceId, amount }],
      idempotency_key: idempotency,
    };

    const preview = await authedPost(page, collectorToken, "/api/v1/payments/preview", payload);
    expect(preview.ok(), await preview.text()).toBeTruthy();

    const draft = await authedPost(page, collectorToken, "/api/v1/payments/draft", payload);
    expect(draft.ok(), await draft.text()).toBeTruthy();
    const draftBody = await draft.json();
    const uuid = draftBody.data?.uuid;
    const paymentId = draftBody.data?.id;
    expect(uuid).toBeTruthy();

    // Double-submit same idempotency must not create a second payment.
    const dup = await authedPost(page, collectorToken, "/api/v1/payments/draft", payload);
    expect(dup.status() === 200 || dup.status() === 201 || dup.status() === 409 || dup.status() === 422).toBeTruthy();

    const confirm = await authedPost(page, collectorToken, `/api/v1/payments/${uuid}/confirm`, {
      idempotency_key: `${idempotency}-confirm`,
    });
    expect(confirm.ok(), await confirm.text()).toBeTruthy();

    await assertDb(page, adminToken, {
      entity: "payment",
      key: "id",
      value: Number(paymentId),
    });

    const ping = await authedGet(page, adminToken, "/api/v1/acceptance/ping");
    expect(ping.ok()).toBeTruthy();
    const zohoMode = String((await ping.json()).data?.zoho_mode || "");
    expect(zohoMode).toMatch(/test_adapter|sandbox|read_only/i);

    const reversal = await authedPost(page, collectorToken, `/api/v1/payments/${uuid}/reversal-request`, {
      reason: "ACCEPTANCE reverse",
    });
    expect(reversal.ok(), await reversal.text()).toBeTruthy();
    const reversalId = (await reversal.json()).data?.id;
    expect(reversalId).toBeTruthy();

    const approve = await authedPost(page, adminToken, `/api/v1/reversals/${reversalId}/approve`);
    expect(approve.ok(), await approve.text()).toBeTruthy();

    await assertDb(page, adminToken, {
      entity: "payment",
      key: "id",
      value: Number(paymentId),
      expect: { status: "reversed" },
    });

    await page.goto(`/${locale}/payments`);
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });
    await expectNoHorizontalOverflow(page);
    await guards.flush();
  });

  test("support ticket lifecycle with timeline", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const token = await login(page, { locale });
    const ids = await fixtureIds(page, token);
    const me = await (await authedGet(page, token, "/api/v1/auth/me")).json();
    const agentId = Number(me.data?.id || me.data?.user?.id);

    const create = await authedPost(page, token, "/api/v1/tickets", {
      branch_id: ids.branchId,
      type_code: "billing_inquiry",
      subject: `ACCEPTANCE ticket ${Date.now()}`,
      customer_id: ids.customerId,
      priority: "normal",
      source: "manual",
    });
    expect(create.ok(), await create.text()).toBeTruthy();
    const ticketId = (await create.json()).data?.id;
    expect(ticketId).toBeTruthy();

    await assertDb(page, token, {
      entity: "ticket",
      key: "id",
      value: Number(ticketId),
      expect: { status: "new" },
    });

    expect(
      (await authedPost(page, token, `/api/v1/tickets/${ticketId}/assign`, { user_id: agentId })).ok(),
    ).toBeTruthy();
    expect(
      (
        await authedPost(page, token, `/api/v1/tickets/${ticketId}/transition`, {
          status: "in_progress",
        })
      ).ok(),
    ).toBeTruthy();
    expect(
      (
        await authedPost(page, token, `/api/v1/tickets/${ticketId}/resolve`, {
          resolution_summary: "ACCEPTANCE resolved",
        })
      ).ok(),
    ).toBeTruthy();
    expect((await authedPost(page, token, `/api/v1/tickets/${ticketId}/close`)).ok()).toBeTruthy();
    expect(
      (
        await authedPost(page, token, `/api/v1/tickets/${ticketId}/reopen`, {
          reason: "ACCEPTANCE reopen",
        })
      ).ok(),
    ).toBeTruthy();

    await assertDb(page, token, {
      entity: "ticket",
      key: "id",
      value: Number(ticketId),
      expect: { status: "reopened" },
    });
    await assertDb(page, token, {
      entity: "audit",
      key: "action",
      value: "ticket.created",
    });

    await page.goto(`/${locale}/tickets/${ticketId}`);
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });
    await expectNoHorizontalOverflow(page);
    await guards.flush();
  });

  test("task offer accept start complete", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const token = await login(page, { locale });
    const ids = await fixtureIds(page, token);

    const usersRes = await authedGet(page, token, "/api/v1/users?per_page=100");
    expect(usersRes.ok(), await usersRes.text()).toBeTruthy();
    const techUser = (((await usersRes.json()).data || []) as Array<{ id: number; username?: string }>).find(
      (u) => u.username === "ACCEPTANCE-collector",
    );
    expect(techUser?.id).toBeTruthy();

    const create = await authedPost(page, token, "/api/v1/tasks", {
      branch_id: ids.branchId,
      title: `ACCEPTANCE task ${Date.now()}`,
      type: "field",
      customer_id: ids.customerId,
    });
    expect(create.ok(), await create.text()).toBeTruthy();
    const taskId = (await create.json()).data?.id;
    expect(taskId).toBeTruthy();

    expect(
      (await authedPost(page, token, `/api/v1/tasks/${taskId}/offer`, { user_id: techUser!.id })).ok(),
    ).toBeTruthy();

    const collectorToken = await apiLogin(page, "ACCEPTANCE-collector");
    expect((await authedPost(page, collectorToken, `/api/v1/tasks/${taskId}/accept`)).ok()).toBeTruthy();
    expect((await authedPost(page, collectorToken, `/api/v1/tasks/${taskId}/start`)).ok()).toBeTruthy();
    expect((await authedPost(page, collectorToken, `/api/v1/tasks/${taskId}/complete`)).ok()).toBeTruthy();

    await assertDb(page, token, {
      entity: "task",
      key: "id",
      value: Number(taskId),
      expect: { status: "completed" },
    });

    await page.goto(`/${locale}/tasks/${taskId}`);
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });
    await expectNoHorizontalOverflow(page);
    await guards.flush();
  });

  test("inventory product receive and stock reconcile", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const token = await login(page, { locale });
    const ids = await fixtureIds(page, token);

    const locations = await authedGet(
      page,
      token,
      `/api/v1/inventory/locations?branch_id=${ids.branchId}`,
    );
    expect(locations.ok(), await locations.text()).toBeTruthy();
    const locRows = ((await locations.json()).data || []) as Array<{
      id: number;
      code?: string;
      type?: string;
    }>;
    const wh = locRows.find((l) => l.code === "MAIN-WH") || locRows[0];
    expect(wh?.id).toBeTruthy();

    const code = `ACC-P-${Date.now().toString(36).slice(-6)}`;
    const product = await authedPost(page, token, "/api/v1/inventory/products", {
      code,
      name_en: "ACCEPTANCE Product",
      name_fa: "محصول پذیرش",
      tracking_mode: "quantity",
      min_stock: 1,
      is_installable: true,
    });
    expect(product.ok(), await product.text()).toBeTruthy();
    const productId = (await product.json()).data?.id;
    expect(productId).toBeTruthy();

    await assertDb(page, token, {
      entity: "product",
      key: "id",
      value: Number(productId),
      expect: { code },
    });

    const supplier = await authedPost(page, token, "/api/v1/inventory/suppliers", {
      code: `ACC-S-${Date.now().toString(36).slice(-5)}`,
      name: "ACCEPTANCE Supplier",
    });
    expect(supplier.ok(), await supplier.text()).toBeTruthy();
    const supplierId = (await supplier.json()).data?.id;

    const receipt = await authedPost(page, token, "/api/v1/inventory/goods-receipts", {
      branch_id: ids.branchId,
      location_id: wh!.id,
      supplier_id: supplierId,
      items: [{ product_id: productId, qty: 5, unit_cost: 10 }],
    });
    expect(receipt.ok(), await receipt.text()).toBeTruthy();

    const stock = await authedGet(page, token, "/api/v1/acceptance/stock-reconciliation");
    expect(stock.ok(), await stock.text()).toBeTruthy();
    expect((await stock.json()).success).toBeTruthy();

    await page.goto(`/${locale}/inventory/stock`);
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });
    await expectNoHorizontalOverflow(page);
    await guards.flush();
  });

  test("notifications list and mark read", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const token = await login(page, { locale });

    const list = await authedGet(page, token, "/api/v1/notifications?per_page=20");
    expect(list.ok(), await list.text()).toBeTruthy();
    const body = await list.json();
    const rows = (body.data || []) as Array<{ id: number; body?: string; title?: string }>;
    for (const n of rows) {
      const blob = `${n.title || ""} ${n.body || ""}`.toLowerCase();
      expect(blob).not.toContain("placeholder");
      expect(blob).not.toContain("demo");
    }
    if (rows[0]?.id) {
      expect((await authedPost(page, token, `/api/v1/notifications/${rows[0].id}/read`)).ok()).toBeTruthy();
    }
    expect((await authedPost(page, token, "/api/v1/notifications/read-all")).ok()).toBeTruthy();

    await page.goto(`/${locale}/notifications`);
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });
    await expectNoHorizontalOverflow(page);
    await guards.flush();
  });

  test("attachment upload download and denial", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const token = await login(page, { locale });
    const ids = await fixtureIds(page, token);

    const ticket = await authedPost(page, token, "/api/v1/tickets", {
      branch_id: ids.branchId,
      type_code: "billing_inquiry",
      subject: `ACCEPTANCE attach ${Date.now()}`,
      customer_id: ids.customerId,
      priority: "normal",
      source: "manual",
    });
    expect(ticket.ok(), await ticket.text()).toBeTruthy();
    const ticketId = (await ticket.json()).data?.id;

    // Minimal valid 1×1 PNG (allowed MIME).
    const png = Buffer.from(
      "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==",
      "base64",
    );
    const upload = await page.request.post("/api/v1/attachments", {
      headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
      multipart: {
        file: {
          name: "پذیرش-photo.png",
          mimeType: "image/png",
          buffer: png,
        },
        attachable_type: "ticket",
        attachable_id: String(ticketId),
        kind: "photo",
      },
    });
    expect(upload.ok(), await upload.text()).toBeTruthy();
    const uploaded = await upload.json();
    const downloadUrl = uploaded.data?.download_url as string | undefined;
    expect(downloadUrl).toBeTruthy();

    const dl = await page.request.get(downloadUrl!);
    expect(dl.ok()).toBeTruthy();

    const anon = await page.request.get(`/api/v1/attachments/${uploaded.data?.attachment?.uuid || "x"}/download`);
    expect([401, 403, 404]).toContain(anon.status());

    const bad = await page.request.post("/api/v1/attachments", {
      headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
      multipart: {
        file: {
          name: "evil.exe",
          mimeType: "application/octet-stream",
          buffer: Buffer.from("MZ"),
        },
        attachable_type: "ticket",
        attachable_id: String(ticketId),
      },
    });
    expect([422, 415, 400]).toContain(bad.status());

    await page.goto(`/${locale}/tickets/${ticketId}`);
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });
    await guards.flush();
  });

  test("administration users and zoho status pages", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const token = await login(page, { locale });

    const users = await authedGet(page, token, "/api/v1/users?per_page=20");
    expect(users.ok()).toBeTruthy();

    const zoho = await authedGet(page, token, "/api/v1/zoho/status");
    const zohoText = await zoho.text();
    expect(zoho.ok(), zohoText).toBeTruthy();
    const raw = zohoText.toLowerCase();
    expect(raw).not.toContain("client_secret");
    expect(raw).not.toContain("app_key");

    await page.goto(`/${locale}/users`);
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });
    await page.goto(`/${locale}/settings/system-version`);
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });
    await expectNoHorizontalOverflow(page);
    await guards.flush();
  });
});
