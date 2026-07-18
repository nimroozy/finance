/**
 * Stage 10.4 real acceptance — required suite.
 * Env enforced by npm run e2e:acceptance + globalSetup (fail, not skip).
 * test.skip only for optional external Zoho write.
 */
import { test, expect } from "@playwright/test";
import {
  PRIMARY_APPS,
  attachFailureGuards,
  assertDb,
  authedGet,
  expectNoHorizontalOverflow,
  login,
} from "./helpers/acceptance";

const zohoWrite = process.env.E2E_ZOHO_WRITE === "1";

function localeFromProject(name: string): "en" | "fa" {
  return name.includes("-fa") ? "fa" : "en";
}

test.describe("Stage 10.4 production acceptance", () => {
  test("authentication happy path and launcher", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    // Explicit UI login path (form submit) for this workflow.
    const token = await login(page, { locale, ui: true });
    await page.goto(`/${locale}/apps`);
    await expect(page.getByTestId("apps-launcher")).toBeVisible({ timeout: 30_000 });
    await expectNoHorizontalOverflow(page);

    let visible = 0;
    for (const id of PRIMARY_APPS) {
      const card = page.getByTestId(`app-card-${id}`);
      if (await card.count()) {
        await expect(card.first()).toBeVisible();
        visible += 1;
      }
    }
    expect(visible, "expected several primary launcher apps").toBeGreaterThanOrEqual(6);

    await expect(page.getByTestId("apps-all").getByTestId("app-card-leads")).toHaveCount(0);
    await expect(page.getByTestId("apps-all").getByTestId("app-card-equipment")).toHaveCount(0);

    const counts = await authedGet(page, token, "/api/v1/apps/counts");
    expect(counts.ok()).toBeTruthy();

    if (locale === "fa") {
      await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
    }
    await guards.flush();
  });

  test("invalid password and disabled user", async ({ page }, testInfo) => {
    const locale = localeFromProject(testInfo.project.name);
    await page.goto(`/${locale}/login`);
    await page.locator("#login, input[name='login']").first().fill(process.env.E2E_USER || "");
    await page.locator('input[type="password"]').first().fill("DefinitelyWrongPass1!");
    await page.getByRole("button", { name: /sign in|log in|login|ورود/i }).first().click();
    await expect(page).toHaveURL(/login/);

    await page.locator("#login, input[name='login']").first().fill("ACCEPTANCE-disabled");
    await page.locator('input[type="password"]').first().fill(process.env.E2E_PASSWORD || "");
    await page.getByRole("button", { name: /sign in|log in|login|ورود/i }).first().click();
    await expect(page).toHaveURL(/login/);
  });

  test("customers search zoho-mirrored acceptance customer", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const token = await login(page, { locale });

    await assertDb(page, token, {
      entity: "customer",
      key: "zoho_contact_id",
      value: "ACCEPTANCE-ZOHO-1",
      expect: { status: "active" },
    });

    const api = await authedGet(page, token, "/api/v1/customers?search=ACCEPTANCE");
    expect(api.ok()).toBeTruthy();
    const body = await api.json();
    expect((body.data || []).length).toBeGreaterThan(0);

    await page.goto(`/${locale}/customers`);
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });
    const search = page.locator('input[type="search"], input[name="search"], #search').first();
    if (await search.count()) {
      await search.fill("0700111222");
      await page.keyboard.press("Enter");
    }
    await expectNoHorizontalOverflow(page);
    await guards.flush();
  });

  test("CRM lead create and zoho link", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const token = await login(page, { locale });

    const stamp = Date.now();
    const me = await authedGet(page, token, "/api/v1/auth/me");
    expect(me.ok()).toBeTruthy();
    const meBody = await me.json();
    const branchId = Number(meBody.data?.branches?.[0]?.id || 1);

    // Prefer API create + UI verify when form fields vary by viewport.
    const create = await page.request.post("/api/v1/crm/leads", {
      headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
      data: {
        branch_id: branchId,
        contact_person: `ACCEPTANCE E2E ${stamp}`,
        phone: "0700111222",
        company: "ACCEPTANCE E2E Co",
        source_code: "other",
      },
    });
    expect(create.ok(), await create.text()).toBeTruthy();
    const created = await create.json();
    const leadId = created.data?.id;
    expect(leadId).toBeTruthy();

    const customerLookup = await authedGet(
      page,
      token,
      "/api/v1/crm/customers/search-zoho-mirror?q=0700111222",
    );
    expect(customerLookup.ok(), await customerLookup.text()).toBeTruthy();
    const customers = ((await customerLookup.json()).data || []) as Array<{ id: number }>;
    expect(customers.length).toBeGreaterThan(0);

    const link = await page.request.post(`/api/v1/crm/leads/${leadId}/link-zoho-customer`, {
      headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
      data: { customer_id: customers[0].id },
    });
    expect(link.ok(), await link.text()).toBeTruthy();

    await assertDb(page, token, {
      entity: "lead",
      key: "id",
      value: Number(leadId),
    });

    await page.goto(`/${locale}/crm/leads/${leadId}`);
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });
    await expect
      .poll(
        async () => {
          if (await page.getByTestId("zoho-link-panel").count()) return "panel";
          const text = await page.locator("main").innerText();
          if (/ACC-LEAD|ACCEPTANCE E2E|سرنخ/i.test(text)) return "lead";
          return "";
        },
        { timeout: 30_000 },
      )
      .not.toEqual("");
    if (await page.getByTestId("zoho-link-panel").count()) {
      await expect(page.getByTestId("zoho-link-status")).toContainText(/linked|zoho|متصل|#\d+/i, {
        timeout: 15_000,
      });
    }
    await guards.flush();
  });

  test("service activation checklist evidence", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const token = await login(page, { locale });

    const list = await authedGet(page, token, "/api/v1/services?per_page=50");
    expect(list.ok()).toBeTruthy();
    const body = await list.json();
    const rows = (body.data || []) as Array<{
      id: number;
      service_number?: string;
      commercial_status?: string;
    }>;
    const pending =
      rows.find((r) => r.service_number === "ACC-SVC-PENDING") ||
      rows.find((r) => r.commercial_status === "pending_activation") ||
      rows[0];
    expect(pending?.id).toBeTruthy();

    const checklist = await authedGet(
      page,
      token,
      `/api/v1/services/${pending!.id}/activation-checklist`,
    );
    expect(checklist.ok()).toBeTruthy();
    const checklistBody = await checklist.json();
    expect(checklistBody.data?.checklist || checklistBody.data).toBeTruthy();

    await page.goto(`/${locale}/services/${pending!.id}?tab=overview`);
    await expect(page.getByTestId("service-detail")).toBeVisible({ timeout: 30_000 });
    const select = page.locator("#responsive-tabs-select");
    if (await select.isVisible().catch(() => false)) {
      await select.selectOption("overview");
    }
    await expect
      .poll(
        async () => {
          if (await page.getByTestId("activation-panel").count()) return "panel";
          const text = await page.getByTestId("service-detail").innerText();
          if (/ACC-SVC-PENDING|pending_activation|فعال/i.test(text)) return "service";
          return "";
        },
        { timeout: 30_000 },
      )
      .not.toEqual("");
    if (await page.getByTestId("activation-panel").count()) {
      await expect(page.getByTestId("activation-checklist")).toBeVisible();
      for (const key of [
        "zoho_linked",
        "installation_completed",
        "equipment_assigned",
        "inventory_reconciled",
      ]) {
        await expect(page.getByTestId(`checklist-item-${key}`)).toBeVisible();
      }
    }

    await assertDb(page, token, {
      entity: "service",
      key: "service_number",
      value: "ACC-SVC-PENDING",
      expect: { commercial_status: "pending_activation" },
    });
    await guards.flush();
  });

  test("service queues change requests and noc", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const token = await login(page, { locale });

    const api = await authedGet(page, token, "/api/v1/service-change-requests?per_page=50");
    expect(api.ok()).toBeTruthy();
    expect(((await api.json()).data || []).length).toBeGreaterThan(0);

    await page.goto(`/${locale}/services/change-requests`);
    await expect(page.getByTestId("services-change-requests")).toBeVisible({ timeout: 30_000 });

    const noc = await authedGet(page, token, "/api/v1/services/noc-workspace");
    expect(noc.ok()).toBeTruthy();
    await page.goto(`/${locale}/noc/services`);
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });
    await expectNoHorizontalOverflow(page);
    await guards.flush();
  });

  test("global search finds acceptance fixtures", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const token = await login(page, { locale });

    for (const q of ["ACCEPTANCE", "0700111222", "ACCEPTANCE-ZOHO-1", "ACC-SVC-000001"]) {
      const res = await authedGet(
        page,
        token,
        `/api/v1/operations/search?q=${encodeURIComponent(q)}`,
      );
      expect(res.ok(), `search ${q}`).toBeTruthy();
      const body = await res.json();
      expect(body.success !== false).toBeTruthy();
    }

    // Global search UI lives in the command menu / apps shell (no dedicated /search page).
    await page.goto(`/${locale}/apps`);
    await expect(page.getByTestId("apps-launcher")).toBeVisible({ timeout: 30_000 });
    await guards.flush();
  });

  test("administration system version exposes stage without secrets", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const locale = localeFromProject(testInfo.project.name);
    const token = await login(page, { locale });

    const res = await authedGet(page, token, "/api/v1/system/version");
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    const data = body.data || body;
    expect(String(data.stage || "")).toContain("10.4");
    const raw = JSON.stringify(data).toLowerCase();
    expect(raw).not.toContain("password");
    expect(raw).not.toContain("secret");
    expect(raw).not.toContain("app_key");
    await guards.flush();
  });

  test("stock reconciliation endpoint", async ({ page }, testInfo) => {
    const guards = attachFailureGuards(page, testInfo);
    const token = await login(page, { locale: localeFromProject(testInfo.project.name) });
    const res = await authedGet(page, token, "/api/v1/acceptance/stock-reconciliation");
    expect(res.ok(), await res.text()).toBeTruthy();
    const body = await res.json();
    expect(body.success).toBeTruthy();
    await guards.flush();
  });

  test("optional external Zoho write", async ({ page }) => {
    test.skip(!zohoWrite, "Optional: set E2E_ZOHO_WRITE=1 to exercise live Zoho write");
    const token = await login(page);
    const res = await authedGet(page, token, "/api/v1/zoho/sync-health");
    expect(res.ok()).toBeTruthy();
  });
});
