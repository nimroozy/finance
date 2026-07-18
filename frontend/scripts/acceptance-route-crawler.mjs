#!/usr/bin/env node
/**
 * Stage 10.4 route crawler — logs in and crawls visible app links.
 */
import fs from "node:fs";
import path from "node:path";
import { chromium } from "@playwright/test";

function arg(name, fallback) {
  const idx = process.argv.indexOf(name);
  if (idx === -1) return fallback;
  return process.argv[idx + 1];
}

const baseURL = arg("--base-url");
const user = arg("--user");
const password = arg("--password");
const out = arg("--out", "route-results.json");

if (!baseURL || !user || !password) {
  console.error("Required: --base-url --user --password");
  process.exit(1);
}

const locales = ["en", "fa"];
const viewports = [
  { name: "desktop", width: 1440, height: 900 },
  { name: "mobile", width: 390, height: 844 },
  { name: "small-mobile", width: 320, height: 700 },
];

const seedPaths = [
  "/apps",
  "/customers",
  "/payments",
  "/collections",
  "/tasks",
  "/tickets",
  "/crm",
  "/installations",
  "/inventory",
  "/services",
  "/services/noc",
  "/reports",
  "/settings/users",
  "/search",
];

const results = [];
let failures = 0;

const browser = await chromium.launch();
for (const locale of locales) {
  for (const vp of viewports) {
    const context = await browser.newContext({
      viewport: { width: vp.width, height: vp.height },
      locale: locale === "fa" ? "fa-AF" : "en-US",
    });
    const page = await context.newPage();
    const consoleErrors = [];
    page.on("console", (msg) => {
      if (msg.type() === "error") consoleErrors.push(msg.text());
    });

    await page.goto(`${baseURL}/${locale}/login`);
    await page.locator("#login, input[name='login']").first().fill(user);
    await page.locator('input[type="password"]').first().fill(password);
    await page.getByRole("button", { name: /sign in|log in|login|ورود/i }).first().click();
    await page.waitForURL((url) => !url.pathname.includes("/login"), { timeout: 30_000 });

    const visited = new Set();
    for (const seed of seedPaths) {
      const path = `/${locale}${seed}`;
      if (visited.has(path)) continue;
      visited.add(path);
      consoleErrors.length = 0;
      let status = 0;
      let render = "ok";
      try {
        const resp = await page.goto(`${baseURL}${path}`, { waitUntil: "domcontentloaded" });
        status = resp?.status() ?? 0;
        const bodyText = (await page.locator("body").innerText()).trim();
        if (!bodyText) render = "blank";
        if (await page.getByText(/unexpected application error|hydration/i).count()) {
          render = "error";
        }
        // Collect same-origin nav links
        const hrefs = await page.$$eval("a[href]", (as) =>
          as.map((a) => a.getAttribute("href")).filter(Boolean),
        );
        for (const href of hrefs) {
          if (!href.startsWith(`/${locale}/`) && !href.startsWith("/")) continue;
          if (href.startsWith("http")) continue;
          const normalized = href.startsWith(`/${locale}`)
            ? href.split("?")[0]
            : `/${locale}${href.startsWith("/") ? href : `/${href}`}`.split("?")[0];
          if (!visited.has(normalized) && visited.size < 80) {
            seedPaths.push(normalized.replace(new RegExp(`^/${locale}`), "") || "/apps");
          }
        }
      } catch (e) {
        status = 0;
        render = `exception:${e.message}`;
      }

      const unexpectedConsole = consoleErrors.filter(
        (t) => !/Download the React DevTools/i.test(t),
      );
      const fail =
        status >= 500 ||
        status === 404 ||
        render !== "ok" ||
        unexpectedConsole.length > 0;

      if (fail) failures += 1;
      results.push({
        route: path,
        role: "ACCEPTANCE-admin",
        locale,
        viewport: vp.name,
        http_status: status,
        console_errors: unexpectedConsole,
        render,
        permission_result: status === 403 ? "denied" : "allowed_or_redirect",
        ok: !fail,
      });
    }
    await context.close();
  }
}
await browser.close();

fs.mkdirSync(path.dirname(out), { recursive: true });
fs.writeFileSync(
  out,
  JSON.stringify(
    {
      generated_at: new Date().toISOString(),
      failures,
      results,
    },
    null,
    2,
  ),
);
console.log(`Route crawler wrote ${out} failures=${failures}`);
process.exit(failures ? 1 : 0);
