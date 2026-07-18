/**
 * Stage 10.5 visual review capture. Not a pass/fail suite — it drives the
 * real UI to each reviewed screen and writes screenshots plus a manifest
 * consumed by docs/STAGE_10_5_VISUAL_REVIEW.md. Only runs when
 * SCREENSHOTS=1 so it never adds cost to the acceptance gate.
 */
import { test, type Page } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { authedGet, login } from "./helpers/acceptance";

const CAPTURE = process.env.SCREENSHOTS === "1";
const shotRoot =
  process.env.SCREENSHOT_DIR ||
  path.join(process.cwd(), "..", "docs", "stage-10-5-screenshots");

type ShotMeta = {
  filename: string;
  route: string;
  locale: string;
  viewport: string;
  theme: string;
  role: string;
  notes: string;
};

const manifest: ShotMeta[] = [];

function localeFromProject(name: string): "en" | "fa" {
  return name.includes("-fa") ? "fa" : "en";
}

function viewportTag(name: string): string {
  if (name.startsWith("small-mobile")) return "320x700";
  if (name.startsWith("mobile")) return "390x844";
  return "1440x900";
}

async function setTheme(page: Page, token: string, theme: "light" | "dark") {
  // The app shell fetches /me/ui-preferences on mount and writes the server
  // value into the localStorage cache, so a cache-only override is clobbered.
  // Persist through the real preference API so server + cache agree.
  await page.request.put("/api/v1/me/ui-preferences", {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    data: { theme },
  });
  await page.evaluate((t) => {
    const raw = localStorage.getItem("ui-preferences-cache");
    const prefs = raw ? JSON.parse(raw) : {};
    prefs.theme = t;
    localStorage.setItem("ui-preferences-cache", JSON.stringify(prefs));
  }, theme);
}

async function shot(
  page: Page,
  projectName: string,
  route: string,
  slug: string,
  theme: "light" | "dark",
  notes: string,
) {
  const locale = localeFromProject(projectName);
  const viewport = viewportTag(projectName);
  const filename = `${slug}__${locale}__${viewport}__${theme}.png`;
  const dir = path.join(shotRoot, projectName);
  fs.mkdirSync(dir, { recursive: true });
  await page.screenshot({ path: path.join(dir, filename), fullPage: true });
  manifest.push({
    filename: path.join(projectName, filename),
    route,
    locale,
    viewport,
    theme,
    role: "ACCEPTANCE-admin",
    notes,
  });
}

test.describe("Stage 10.5 visual review", () => {
  test.skip(!CAPTURE, "Set SCREENSHOTS=1 to capture visual-review screenshots");

  test("capture reviewed screens", async ({ page }, testInfo) => {
    const projectName = testInfo.project.name;
    const locale = localeFromProject(projectName);

    // Login screen — captured before authenticating.
    await page.goto(`/${locale}/login`);
    await page.waitForLoadState("networkidle").catch(() => {});
    await shot(page, projectName, `/${locale}/login`, "login", "light", "Login form");

    const adminToken = await login(page, { locale });
    const bcmath = (await authedGet(page, adminToken, "/api/v1/invoices?per_page=1")).ok();

    // Resolve the acceptance customer id for detail-page captures.
    const custRes = await authedGet(page, adminToken, "/api/v1/customers?search=ACC-CUST-001");
    const custId = ((await custRes.json()).data?.[0]?.id as number) || 1;

    const screens: Array<{ route: string; slug: string; notes: string; both?: boolean }> = [
      { route: `/${locale}/apps`, slug: "launcher", notes: "App launcher", both: true },
      { route: `/${locale}/customers`, slug: "customer-list", notes: "Customer list", both: true },
      { route: `/${locale}/customers/${custId}`, slug: "customer-detail", notes: "Customer detail overview" },
      { route: `/${locale}/collector/payments/new`, slug: "payment-workflow", notes: "Payment customer step", both: true },
      { route: `/${locale}/assignments`, slug: "collections-assignments", notes: "Collections team assignments" },
      { route: `/${locale}/routes`, slug: "route-list", notes: "Route list" },
      { route: `/${locale}/promises`, slug: "promises", notes: "Promises to pay" },
      { route: `/${locale}/visits`, slug: "visits", notes: "Visits list" },
      { route: `/${locale}/ownership-conflicts`, slug: "ownership-conflict", notes: "Ownership conflicts" },
      { route: `/${locale}/handovers`, slug: "handover", notes: "Cash handovers" },
      { route: `/${locale}/collectors/performance`, slug: "collector-performance", notes: "Collector performance" },
    ];

    for (const s of screens) {
      // bcmath-dependent detail page still renders its shell + honest error;
      // capture it regardless so the visual record shows the degraded state.
      void bcmath;
      await setTheme(page, adminToken, "light");
      await page.goto(s.route);
      await page.waitForLoadState("networkidle").catch(() => {});
      await page.waitForTimeout(600);
      await shot(page, projectName, s.route, s.slug, "light", s.notes);

      if (s.both) {
        await setTheme(page, adminToken, "dark");
        await page.reload();
        await page.waitForLoadState("networkidle").catch(() => {});
        await page.waitForTimeout(600);
        await shot(page, projectName, s.route, s.slug, "dark", `${s.notes} (dark)`);
      }
    }

    fs.mkdirSync(shotRoot, { recursive: true });
    const manifestPath = path.join(shotRoot, `manifest.${projectName}.json`);
    fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
  });
});
