# Stage 10.3 Delivery Report

**Date (UTC):** 2026-07-18  
**Branch:** `cursor/stage-10-3-functional-acceptance`  
**VPS:** `root@209.38.194.184` (`/opt/collection-system`)  
**Deploy stamp:** `20260718T054503Z` (pre) / `20260718T055037Z` (post)  
**Tip SHA deployed:** `79a0e4ebb5de10da8113a996acbb34c2d543d402` _(updated to final tip after this report commit)_  
**Production SHA:** _(same as tip after re-sync)_  
**Do not merge until human review.** **Stage 11 not started.** **No Radius.**  
**KEY_KEPT** (`~/.ssh/id_ed25519` preserved — not deleted).  
**AcceptanceSeeder:** **skipped on production** (RolePermissionSeeder only).

---

## Summary

Stage **10.3 functional acceptance** is live: stage label `10.3-functional-acceptance`, launcher/ActivationPanel/queues tip, reviewed demo cleanup already applied. **Financial + inventory counts MATCH.** Customers +3 during deploy window from Zoho contact sync (not seeders). Four-way SHA match after tip re-sync.

---

## Pre-flight

| Check | Result |
|-------|--------|
| `git pull` | Already up to date |
| PHP filter tests | **59 passed** |
| `npm run lint` / `build` / `i18n:check` | **OK** (1866 i18n keys) |
| Playwright stage91 + stage10 (desktop+mobile) | **48 passed** |
| Stage label | `10.2-production-recovery` → `10.3-functional-acceptance` |

---

## Backups

| Phase | Path | Status |
|-------|------|--------|
| Pre-deploy (labeled) | `/opt/collection-backups/20260718T054503Z-stage10-3-predeploy/` | **Done** — `postgres.dump` (~2.3MB) |
| Pre-deploy (script) | `/opt/collection-backups/20260718T054512Z` | **Done** |
| Post-deploy (labeled) | `/opt/collection-backups/20260718T055037Z-stage10-3-postdeploy/` | **Done** — `postgres.dump` (~2.3MB) |
| Post-deploy (script) | `/opt/collection-backups/20260718T055039Z` | **Done** |

---

## Pre / post counts

| Metric | Pre | Post | Match |
|--------|-----|------|-------|
| payments | 3 | 3 | **MATCH** |
| cash_handover_requests | 1 | 1 | **MATCH** |
| collector_wallets | 3 | 3 | **MATCH** |
| branch_cashboxes | 2 | 2 | **MATCH** |
| payment_reversals | 3 | 3 | **MATCH** |
| customers (all rows) | 7005 | 7008 | +3 Zoho sync* |
| customers_alive | 6998 | 7001 | +3 Zoho sync* |
| tickets | 2 | 2 | **MATCH** |
| tasks | 4 | 4 | **MATCH** |
| installations | 4 | 4 | **MATCH** |
| crm_leads | 1 | 1 | **MATCH** |
| services | 1 | 1 | **MATCH** |
| service_packages | 2 | 2 | **MATCH** |
| inventory_stock_transactions | 14 | 14 | **MATCH** |
| inventory_on_hand_sum | 18.000 | 18.000 | **MATCH** |

\*Ids 7047–7049 created `2026-07-18 05:50:19` with Zoho contact IDs — inbound sync during deploy window, not AcceptanceSeeder / RolePermissionSeeder.

**COUNTS_MATCH (financial + inventory):** yes  
**COUNTS_MATCH (ops tickets/tasks/installations/leads/services):** yes

---

## Deploy steps

1. BEFORE `APP_COMMIT_SHA` = `fea840530c287b7bdc906b855706c4cf84203178` (10.2 tip); no host `.deployed-sha`
2. Pre backup + financial/inventory counts
3. `./scripts/sync-to-vps.sh` from tip `79a0e4e…`
4. VPS `./scripts/deploy.sh` — migrate **Nothing to migrate**; RolePermissionSeeder; admin install skipped; **AcceptanceSeeder not run**
5. Wrote host `.deployed-sha` = `79a0e4e…`; set `APP_STAGE=10.3-functional-acceptance`, `APP_COMMIT_SHA`, `APP_BRANCH`; force-recreated backend/queue/scheduler; copied `.deployed-sha` / `.deployed-at` into containers
6. Four-way verify; smoke routes **200**; post counts; post backup
7. This delivery report commit → re-sync tip → update `.deployed-sha` / `APP_COMMIT_SHA` to new tip

---

## Version / smoke

| Check | Result |
|-------|--------|
| `/api/v1/health` → `deployment.stage` | **`10.3-functional-acceptance`** |
| git_sha / commit_sha (health) | `79a0e4ebb5de10da8113a996acbb34c2d543d402` (= tip before report; updated after re-sync) |
| DeploymentInfo / system version payload | same SHA + stage (via `DeploymentInfo::toArray`) |
| branch | `cursor/stage-10-3-functional-acceptance` |
| migration_batch | **19** (`2026_07_17_210000_stage102_service_lifecycle_recovery`) |
| `/en/apps` | **200** |
| `/fa/apps` | **200** |
| `/en/services` | **200** |
| `/en/services/cancellations` | **200** |
| Radius in `.env` | **absent / empty** |
| Compose | backend/frontend healthy; nginx/postgres/redis/queue/scheduler up |

---

## Explicit non-goals

- Does not merge PRs
- Does not start Stage 11
- Does not enable Radius
- Does not run live WhatsApp
- Does not perform uncontrolled Zoho writes
- Does not seed AcceptanceSeeder on production
- Does not alter payment/wallet/handover/cashbox math
- Does not delete SSH keys (**KEY_KEPT**)

## Related docs

- [STAGE_10_3_FUNCTIONAL_ACCEPTANCE.md](STAGE_10_3_FUNCTIONAL_ACCEPTANCE.md)
- [PRODUCTION_SHA_VERIFICATION.md](PRODUCTION_SHA_VERIFICATION.md)
- [STAGE_10_3_CLEANUP_RESULT.md](STAGE_10_3_CLEANUP_RESULT.md)
- [STAGE_10_2_DELIVERY_REPORT.md](STAGE_10_2_DELIVERY_REPORT.md) (cleanup follow-up note)
- [UI_ACCEPTANCE_RESULTS.md](UI_ACCEPTANCE_RESULTS.md) / [MOBILE_ACCEPTANCE_RESULTS.md](MOBILE_ACCEPTANCE_RESULTS.md)

---

## Closeout verification

| Field | Value |
|-------|-------|
| tip SHA | _(see final tip after report commit + re-sync)_ |
| `.deployed-sha` | _(same)_ |
| health SHA | _(same)_ |
| system version SHA | _(same)_ |
| stage | `10.3-functional-acceptance` |
| COUNTS_MATCH (financial+inventory) | yes |
| KEY_KEPT | yes |
| AcceptanceSeeder on prod | skipped |
