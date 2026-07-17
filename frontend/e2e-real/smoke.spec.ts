/**
 * Real-backend Playwright smoke.
 * Skips unless E2E_REAL=1 and BASE_URL (or PLAYWRIGHT_BASE_URL) are set.
 */
import { test, expect } from "@playwright/test";

const enabled =
  process.env.E2E_REAL === "1" &&
  Boolean(process.env.BASE_URL || process.env.PLAYWRIGHT_BASE_URL);

const user = process.env.E2E_USER || "";
const password = process.env.E2E_PASSWORD || "";

test.describe("Stage 10.2 real E2E smoke", () => {
  test.skip(!enabled, "Set E2E_REAL=1 and BASE_URL to run real backend E2E");

  test("login, apps launcher, and services create flow", async ({ page }) => {
    test.skip(!user || !password, "Set E2E_USER and E2E_PASSWORD");

    await page.goto("/en/login");
    await page.getByLabel(/email|username|user/i).first().fill(user);
    await page.locator('input[type="password"]').first().fill(password);
    await page.getByRole("button", { name: /sign in|log in|login/i }).first().click();

    await expect(page).not.toHaveURL(/\/login/, { timeout: 30_000 });

    await page.goto("/en/apps");
    await expect(page.getByTestId("apps-launcher")).toBeVisible({ timeout: 30_000 });

    // Counts endpoint should be reachable for authenticated users
    const counts = await page.request.get("/api/v1/apps/counts");
    expect(counts.ok()).toBeTruthy();

    await page.goto("/en/services");
    await expect(page.locator("main")).toBeVisible({ timeout: 30_000 });

    // Best-effort create flow: open create if the UI exposes it
    const createBtn = page.getByRole("button", { name: /create|new service/i }).first();
    if (await createBtn.isVisible().catch(() => false)) {
      await createBtn.click();
      await expect(page.locator("form, [data-testid='service-create']").first()).toBeVisible({
        timeout: 15_000,
      });
    }
  });
});
