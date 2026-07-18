#!/usr/bin/env node
/**
 * Stage 10.4 route crawler — API login + seed crawl of real app routes.
 * Fail-closed on blank pages, 404/500, and unexpected console errors.
 */
import fs from "node:fs";
import path from "node:path";
import { chromium } from "@playwright/test";

function arg(name, fallback) {
  const idx = process.argv.indexOf(name);
  if (idx === -1) return fallback;
  return process.argv[idx + 1];
}

const baseURL = (arg("--base-url") || "").replace(/\/$/, "");
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

/** Real App Router seeds only (no /search, /collections, /services/noc aliases). */
const seedPaths = [
  "/apps",
  "/customers",
  "/payments",
  "/assignments",
  "/routes",
  "/visits",
  "/handovers",
  "/tasks",
  "/tickets",
  "/crm",
  "/installations",
  "/inventory/dashboard",
  "/inventory/stock",
  "/inventory/products",
  "/services",
  "/services/change-requests",
  "/noc/services",
  "/notifications",
  "/reports",
  "/users",
  "/settings",
  "/settings/system-version",
  "/audit-logs",
];

const CONSOLE_ALLOWLIST = [
  /Download the React DevTools/i,
  /favicon\.ico/i,
  /Failed to load resource: the server responded with a status of 401/i,
  /Failed to fetch RSC payload/i,
  /Falling back to browser navigation/i,
  /Failed to fetch/i,
  /net::ERR_/i,
];

const results = [];
let failures = 0;

async function apiLogin(request) {
  const res = await request.post(`${baseURL}/api/v1/auth/login`, {
    data: { login: user, password, device_name: "acceptance-route-crawler" },
    headers: { Accept: "application/json" },
  });
  if (!res.ok()) {
    throw new Error(`Route crawler login failed: ${res.status()} ${await res.text()}`);
  }
  const body = await res.json();
  return { token: body.data.token, user: body.data.user };
}

const browser = await chromium.launch();
for (const locale of locales) {
  for (const vp of viewports) {
    const context = await browser.newContext({
      viewport: { width: vp.width, height: vp.height },
      locale: locale === "fa" ? "fa-AF" : "en-US",
    });
    const { token, user: authUser } = await apiLogin(context.request);
    await context.addInitScript(
      ({ token: t, user: u }) => {
        localStorage.setItem(
          "auth-storage",
          JSON.stringify({ state: { token: t, user: u }, version: 0 }),
        );
      },
      { token, user: authUser },
    );

    const page = await context.newPage();
    const consoleErrors = [];
    page.on("console", (msg) => {
      if (msg.type() === "error") consoleErrors.push(msg.text());
    });
    page.on("pageerror", (err) => {
      consoleErrors.push(`pageerror:${err.message}`);
    });

    // Hydrate auth via launcher first.
    await page.goto(`${baseURL}/${locale}/apps`, { waitUntil: "domcontentloaded" });
    await page.waitForTimeout(500);

    const queue = [...seedPaths];
    const visited = new Set();

    while (queue.length && visited.size < 60) {
      const seed = queue.shift();
      const route = `/${locale}${seed}`;
      if (visited.has(route)) continue;
      visited.add(route);
      consoleErrors.length = 0;

      let status = 0;
      let render = "ok";
      let finalUrl = route;
      try {
        const resp = await page.goto(`${baseURL}${route}`, {
          waitUntil: "domcontentloaded",
          timeout: 30_000,
        });
        status = resp?.status() ?? 0;
        finalUrl = page.url().replace(baseURL, "") || route;
        await page.waitForTimeout(300);
        const bodyText = (await page.locator("body").innerText().catch(() => "")).trim();
        if (!bodyText) render = "blank";
        if (await page.getByText(/unexpected application error/i).count()) {
          render = "error";
        }
        // Soft-collect same-origin app links (bounded).
        const hrefs = await page.$$eval("a[href]", (as) =>
          as.map((a) => a.getAttribute("href")).filter(Boolean),
        );
        for (const href of hrefs) {
          if (!href || href.startsWith("http") || href.startsWith("mailto:")) continue;
          const normalized = (
            href.startsWith(`/${locale}/`)
              ? href
              : href.startsWith("/")
                ? `/${locale}${href}`
                : `/${locale}/${href}`
          ).split("?")[0].split("#")[0];
          const relative = normalized.replace(new RegExp(`^/${locale}`), "") || "/apps";
          // Stay on primary operational surfaces; skip dynamic detail ids.
          if (/\/\d+(\/|$)/.test(relative)) continue;
          if (!seedPaths.includes(relative) && visited.size + queue.length < 60) {
            if (
              [
                "/debtors",
                "/promises",
                "/receipts",
                "/cashboxes",
                "/roles",
                "/branches",
                "/inventory/receiving",
                "/inventory/transfers",
                "/crm/leads",
              ].includes(relative)
            ) {
              queue.push(relative);
            }
          }
        }
      } catch (e) {
        status = 0;
        render = `exception:${e.message}`;
      }

      const unexpectedConsole = consoleErrors.filter(
        (t) => !CONSOLE_ALLOWLIST.some((re) => re.test(t)),
      );

      // Next.js app routes often return 200 HTML even for client 403 — treat blank/error.
      const fail =
        status >= 500 ||
        status === 404 ||
        render !== "ok" ||
        unexpectedConsole.length > 0 ||
        /\/login(?:\?|$)/.test(finalUrl);

      if (fail) failures += 1;
      results.push({
        route,
        final_url: finalUrl,
        role: "ACCEPTANCE-admin",
        locale,
        viewport: vp.name,
        http_status: status,
        console_errors: unexpectedConsole,
        render,
        permission_result: /\/403/.test(finalUrl) ? "denied" : "allowed_or_redirect",
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
