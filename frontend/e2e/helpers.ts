import type { Page, Locator } from "@playwright/test";

/** Click a primary action inside main (avoids shell header controls). */
export async function clickMainAction(
  page: Page,
  name: string | RegExp,
  options?: { exact?: boolean },
) {
  const btn = page.locator("main").getByRole("button", {
    name,
    exact: options?.exact,
  });
  await btn.scrollIntoViewIfNeeded();
  await btn.click({ force: true });
}

/** Confirm the open ConfirmDialog / alertdialog (mobile-safe). */
export async function clickConfirmDialog(page: Page) {
  const dialog = page.getByRole("alertdialog");
  await dialog.waitFor({ state: "visible" });
  await dialog.getByRole("button", { name: /Confirm/i }).click({ force: true });
}

export async function clickVisible(locator: Locator) {
  await locator.scrollIntoViewIfNeeded();
  await locator.click({ force: true });
}
