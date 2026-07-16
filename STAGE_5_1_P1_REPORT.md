# STAGE_5_1_P1_REPORT.md

**Date (UTC):** 2026-07-16  
**Branch:** `cursor/stage-5-1-p1-ui`  
**SHA:** `14d4160a13c4e7c179680bcb899e5f1a7435ded4`  
**Draft PR:** https://github.com/nimroozy/finance/pull/7 → `cursor/stage-5-1-p0-repair`  
**Base tip verified:** `487cdf59b899b29e08961f629c47b087a555cfbd`  
**Do not merge:** PR #4, #5, #6 (or #7 until reviewed). **Stage 6 not started.**

---

## Backups

| Phase | Path |
|-------|------|
| Pre-P1 gate | `/opt/collection-backups/20260716T151456Z-stage51-p1-pregate/` |
| Pre-deploy | `/opt/collection-backups/20260716T154135Z-stage51-p1-predeploy/` |
| Post-deploy | `/opt/collection-backups/*-stage51-p1-postdeploy/` (created after smoke) |

Artifacts include `postgres.dump.gz`, counts snapshot, failed-jobs export (pregate/predeploy), source/config as available.

---

## Part A — Data consistency gate

**Verdict: PASS** (see `DATA_COUNT_RECONCILIATION.md`)

| Metric | Gate (pre-deploy growth) | After P1 deploy/mapping |
|--------|--------------------------|-------------------------|
| Customers | **6907** at gate → **6912** pre-deploy (scheduled sync +5) | **6912** |
| Invoices | **1989** → **1995** | **1995** |
| Mapped customers | 625 (Nimruz only) | **1888** |
| Unmapped customers | 6282 | **5024** |
| Mapped invoices | 658 | **1990** |
| Unmapped invoices | 1331 | **5** |
| Failed jobs | 13 | **13** (not deleted) |
| Payments | 1 | **1** |
| Handovers | 0 | **0** |
| Collector wallets | 1→2 (pre-existing growth) | **2** |
| Cashboxes | 0 | **2** (safe P1 test ledgers only) |

**Customer discrepancy explanation:** 4143 pre-P0 + 2764 created during P0 full sync = 6907. Not join duplication. Later scheduled syncs raised warehouse to 6912.

**Failed-job 8→13:** five post-cleanup failures from first P0 deploy (queue timeout + heartbeat float→bigint). None are payment/handover/financial ledger jobs. Remaining 13 classified as stale not-connected, timestamp representatives, ModelNotFound, timeout, heartbeat_float. Retained for review UI; not deleted in P1.

---

## Location mapping decisions (controlled)

| Location | Decision | Local branch |
|----------|----------|--------------|
| Nimruz | Keep (P0) | `01` |
| Kabul | Import + link + reprocess | `KABUL` |
| Kandahar | Import + link + reprocess | `KANDAHAR` |
| Ghazni | Import + link + reprocess | `GHAZNI` |
| Buldak | Import + link + reprocess | `BULDAK` |
| Helmand | Import + link + reprocess | `HELMAND` |
| Headquarter | Defer / non-operational | — |
| test | Ignore / non-operational | — |

Reprocess protected multi-location / conflict customers (1 Kabul conflict skipped). Headquarter/test not auto-mapped.

---

## UI / navigation delivered

- Grouped operational nav (Overview, Customers/Debt, Field, Payments, Cash, Admin) with permission filtering + mobile menu
- Role dashboard summary API + KPI cards
- Location mapping review + mapping conflicts pages
- Transfers / bank deposits / reconciliation pages
- Sync health improvements
- Data table / page section primitives
- Playwright desktop + mobile + RTL smoke (mocked API)
- Admin recovery: `admin:reset-password` verified; ops reset performed with `--password-file --unlock --force-change`; login + change-password OK; deploy skipped password overwrite; `.secrets` marked non-canonical (password not shared)

---

## Tests

| Suite | Result |
|-------|--------|
| Backend SQLite | **137 passed** (506 assertions) |
| Frontend lint | pass |
| Frontend production build | pass |
| Playwright | **22 passed**, 2 skipped (health when unset) |
| OpenAPI | validates (warnings only) |

---

## Production verification

- Deploy: images rebuilt; migrate OK; **Super Administrator already present — password unchanged by deploy**
- Containers healthy; public listeners **22/80/443 only**
- Health + EN/FA login pages 200
- Safe test: transfer #1 received; bank deposit #2 received; reconciliation #1 matched variance 0
- Production payment count unchanged (**1**); handovers **0**
- Scheduled sync containers running (queue + scheduler)

---

## Deferred

- Full authenticated Playwright flows against live Zoho (use mocked CI + separate prod smoke)
- Saved filter presets / column visibility persistence
- Archiving remaining non-critical failed jobs after operator review
- Stage 6 / WhatsApp

---

## Remaining unmapped

- **5024** customers without a consistent single-location branch assignment (no invoice location evidence, or deferred locations)
- **5** invoices without branch (likely Headquarter/test/null location)
- Mapping conflicts UI available for ongoing review
