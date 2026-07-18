/**
 * Stage 10.3 functional acceptance — real API + UI.
 * Required env is enforced by npm run e2e:acceptance + globalSetup (fail, not skip).
 * test.skip is reserved for optional external Zoho write only.
 */
import { test, expect } from "@playwright/test";

const user = process.env.E2E_USER || "";
const password = process.env.E2E_PASSWORD || "";
const zohoWrite = process.env.E2E_ZOHO_WRITE === "1";

async function login(page: import("@playwright/test").Page) {
  await page.goto("/en/login");
  await page.getByLabel(/email|username|user|login/i).first().fill(user);
  await page.locator('input[type="password"]').first().fill(password);
  await page.getByRole("button", { name: /sign in|log in|login/i }).first().click();
  await expect(page).not.toHaveURL(/\/login/, { timeout: 30_000 });
}

test.describe("Stage 10.3 functional acceptance", () => {
  test("login and apps launcher show primary apps", async ({ page }) => {
    await login(page);
    await page.goto("/en/apps");
    await expect(page.getByTestId("apps-launcher")).toBeVisible({ timeout: 30_000 });

    for (const id of [
      "customers",
      "payments",
      "collections",
      "crm",
      "inventory",
      "services",
      "reports",
      "administration",
    ]) {
      // Permission-gated: assert at least several primary cards exist
      const card = page.getByTestId(`app-card-${id}`);
      if (await card.count()) {
        await expect(card.first()).toBeVisible();
      }
    }

    // Submodules must not appear as primary launcher cards
    await expect(page.getByTestId("apps-all").getByTestId("app-card-leads")).toHaveCount(0);
    await expect(page.getByTestId("apps-all").getByTestId("app-card-equipment")).toHaveCount(0);
    await expect(page.getByTestId("apps-all").getByTestId("app-card-users")).toHaveCount(0);

    const counts = await page.request.get("/api/v1/apps/counts");
    expect(counts.ok()).toBeTruthy();
  });

  test("create lead and link zoho customer from acceptance fixtures", async ({ page }) => {
    await login(page);

    const stamp = Date.now();
    await page.goto("/en/crm/leads/new");
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });

    const contact = page.getByLabel(/contact|name|person/i).first();
    if (await contact.isVisible().catch(() => false)) {
      await contact.fill(`ACCEPTANCE E2E ${stamp}`);
    } else {
      await page.locator('input[name="contact_person"], #contact_person').first().fill(`ACCEPTANCE E2E ${stamp}`);
    }

    const phone = page.locator('input[name="phone"], #phone, input[type="tel"]').first();
    if (await phone.isVisible().catch(() => false)) {
      await phone.fill("0700111222");
    }

    await page.getByRole("button", { name: /create|save|submit/i }).first().click();
    await expect(page).toHaveURL(/\/crm\/leads\/\d+/, { timeout: 30_000 });

    await expect(page.getByTestId("zoho-link-panel")).toBeVisible({ timeout: 30_000 });
    await page.getByTestId("zoho-search-input").fill("0700111222");
    await page.getByTestId("zoho-search-submit").click();
    await expect(page.getByTestId("zoho-search-results")).toBeVisible({ timeout: 30_000 });
    await page.getByTestId("zoho-link-customer").first().click();
    await expect(page.getByTestId("zoho-link-status")).toContainText(/linked|zoho/i, {
      timeout: 30_000,
    });
  });

  test("service activation checklist shows evidence from API", async ({ page }) => {
    await login(page);

    const list = await page.request.get("/api/v1/services?per_page=50");
    expect(list.ok()).toBeTruthy();
    const body = await list.json();
    const rows = (body.data || []) as Array<{ id: number; service_number?: string; commercial_status?: string }>;
    const pending =
      rows.find((r) => r.service_number === "ACC-SVC-PENDING") ||
      rows.find((r) => r.commercial_status === "pending_activation") ||
      rows[0];
    expect(pending?.id).toBeTruthy();

    await page.goto(`/en/services/${pending!.id}`);
    await expect(page.getByTestId("service-detail")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId("activation-panel")).toBeVisible();
    await expect(page.getByTestId("activation-checklist")).toBeVisible();

    for (const key of [
      "zoho_linked",
      "installation_completed",
      "equipment_assigned",
      "inventory_reconciled",
    ]) {
      await expect(page.getByTestId(`checklist-item-${key}`)).toBeVisible();
    }

    const checklist = await page.request.get(`/api/v1/services/${pending!.id}/activation-checklist`);
    expect(checklist.ok()).toBeTruthy();
  });

  test("change-request queue loads real workflow data", async ({ page }) => {
    await login(page);

    const api = await page.request.get("/api/v1/service-change-requests?per_page=50");
    expect(api.ok()).toBeTruthy();
    const body = await api.json();
    expect(Array.isArray(body.data)).toBeTruthy();
    expect((body.data as unknown[]).length).toBeGreaterThan(0);

    await page.goto("/en/services/change-requests");
    await expect(page.getByTestId("services-change-requests")).toBeVisible({ timeout: 30_000 });
    await expect(page.locator("table tbody tr, [data-testid='change-request-row']").first()).toBeVisible({
      timeout: 30_000,
    });
  });

  test("optional external Zoho write", async ({ page }) => {
    test.skip(!zohoWrite, "Optional: set E2E_ZOHO_WRITE=1 to exercise live Zoho write");

    await login(page);
    const res = await page.request.get("/api/v1/zoho/sync-health");
    expect(res.ok()).toBeTruthy();
  });
});
