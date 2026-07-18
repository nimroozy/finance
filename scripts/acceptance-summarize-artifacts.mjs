#!/usr/bin/env node
/**
 * Aggregate console/network audit artifacts written by Playwright acceptance helpers.
 */
import fs from "node:fs";
import path from "node:path";

function arg(name, fallback = undefined) {
  const idx = process.argv.indexOf(name);
  if (idx === -1) return fallback;
  return process.argv[idx + 1];
}

const resultsDir = arg("--results-dir");
const out = arg("--out");
if (!resultsDir || !out) {
  console.error("Usage: acceptance-summarize-artifacts.mjs --results-dir DIR --out FILE");
  process.exit(1);
}

const consoleDir = path.join(resultsDir, "console");
const networkDir = path.join(resultsDir, "network");
fs.mkdirSync(consoleDir, { recursive: true });
fs.mkdirSync(networkDir, { recursive: true });

function readJsonFiles(dir) {
  if (!fs.existsSync(dir)) return [];
  return fs
    .readdirSync(dir)
    .filter((f) => f.endsWith(".json"))
    .map((f) => {
      try {
        return JSON.parse(fs.readFileSync(path.join(dir, f), "utf8"));
      } catch {
        return null;
      }
    })
    .filter(Boolean);
}

const consoleFiles = readJsonFiles(consoleDir);
const networkFiles = readJsonFiles(networkDir);

const consoleErrors = consoleFiles.flatMap((f) => f.errors || f.console_errors || []);
const networkErrors = networkFiles.flatMap((f) => f.errors || f.network_errors || []);

const allowlist = [
  /Download the React DevTools/i,
  /favicon\.ico/i,
];

const unexpectedConsole = consoleErrors.filter(
  (e) => !allowlist.some((re) => re.test(String(e.message || e))),
);
const unexpectedNetwork = networkErrors.filter((e) => {
  const status = Number(e.status || 0);
  return [500, 401, 403, 404].includes(status) && !e.expected;
});

const summary = {
  generated_at: new Date().toISOString(),
  console_error_count: unexpectedConsole.length,
  network_error_count: unexpectedNetwork.length,
  allowlisted_patterns: allowlist.map((r) => r.source),
  console_errors: unexpectedConsole.slice(0, 100),
  network_errors: unexpectedNetwork.slice(0, 100),
  ok: unexpectedConsole.length === 0 && unexpectedNetwork.length === 0,
};

fs.writeFileSync(out, JSON.stringify(summary, null, 2));
console.log(JSON.stringify({ ok: summary.ok, console: summary.console_error_count, network: summary.network_error_count }));
process.exit(summary.ok ? 0 : 1);
