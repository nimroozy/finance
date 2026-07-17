#!/usr/bin/env node
/**
 * Translation key parity check — compares nested keys in en.json vs fa.json.
 * Fails (exit 1) when either locale is missing keys present in the other.
 *
 * Usage: node scripts/check-i18n-keys.mjs
 * Or:    npm run i18n:check
 */
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const messagesDir = join(__dirname, "../src/i18n/messages");

function flattenKeys(value, prefix = "") {
  const keys = [];
  if (value === null || typeof value !== "object" || Array.isArray(value)) {
    if (prefix) keys.push(prefix);
    return keys;
  }
  for (const [k, v] of Object.entries(value)) {
    const path = prefix ? `${prefix}.${k}` : k;
    if (v !== null && typeof v === "object" && !Array.isArray(v)) {
      keys.push(...flattenKeys(v, path));
    } else {
      keys.push(path);
    }
  }
  return keys;
}

function load(locale) {
  const path = join(messagesDir, `${locale}.json`);
  return JSON.parse(readFileSync(path, "utf8"));
}

const en = load("en");
const fa = load("fa");
const enKeys = new Set(flattenKeys(en));
const faKeys = new Set(flattenKeys(fa));

const missingInFa = [...enKeys].filter((k) => !faKeys.has(k)).sort();
const missingInEn = [...faKeys].filter((k) => !enKeys.has(k)).sort();

const criticalPrefixes = ["apps.", "launcher.", "shell.", "common.", "nav."];
const criticalMissing = [...missingInFa, ...missingInEn].filter((k) =>
  criticalPrefixes.some((p) => k === p.slice(0, -1) || k.startsWith(p)),
);

let failed = false;

if (missingInFa.length) {
  failed = true;
  console.error(`Missing in fa.json (${missingInFa.length}):`);
  for (const k of missingInFa) console.error(`  - ${k}`);
}

if (missingInEn.length) {
  failed = true;
  console.error(`Missing in en.json (${missingInEn.length}):`);
  for (const k of missingInEn) console.error(`  - ${k}`);
}

if (criticalMissing.length) {
  failed = true;
  console.error(`Critical launcher/shell/apps key gaps (${criticalMissing.length}):`);
  for (const k of criticalMissing) console.error(`  ! ${k}`);
}

if (failed) {
  console.error("\ni18n:check failed — translation key parity required.");
  process.exit(1);
}

console.log(
  `i18n:check OK — ${enKeys.size} keys match between en.json and fa.json.`,
);
