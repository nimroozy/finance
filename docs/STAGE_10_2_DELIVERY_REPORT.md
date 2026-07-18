# Stage 10.2 Delivery Report

**Date (UTC):** 2026-07-17  
**Branch:** `cursor/stage-10-2-production-recovery`  
**VPS:** `root@209.38.194.184` (`/opt/collection-system`)  
**Deploy stamp:** `20260717T211618Z`  
**Tip SHA deployed:** `d12e08b4990220e36ad26b1daa3b5113834de81f`  
**Production SHA:** `d12e08b4990220e36ad26b1daa3b5113834de81f`  
**Code deploy SHA:** `50c6e6619ac025ef13d6a96c00f3c00c1c7d9b05`  
**Do not merge until human review.** **Stage 11 not started.** **No Radius.**  
**KEY_KEPT** (`~/.ssh/id_ed25519` preserved — not deleted).

---

## Summary

Stage **10.2 production recovery** is live on production: service API completeness, global search, launcher counts, branch integrity, demo-cleanup CLI, and stage label `10.2-production-recovery`. Financial/inventory counts unchanged (**MATCH**). Demo cleanup left at **dry-run only** (ambiguous candidates — see below).

---

## Pre-flight

| Check | Result |
|-------|--------|
| `git pull` | Already up to date |
| Tip before label commit | `04052f64b3a8d0493345360133adce942e46ce29` |
| Stage label update | `10.1-integrated-stable` → `10.2-production-recovery` in `DeploymentInfo`, `config/app.php`, `Stage71PickersTest` |
| Label commit + push | `50c6e6619ac025ef13d6a96c00f3c00c1c7d9b05` |
| Delivery report commits | tip advanced after report; re-synced to VPS |

---

## Backups

| Phase | Path | Status |
|-------|------|--------|
| Pre-deploy (labeled) | `/opt/collection-backups/20260717T211618Z-stage10-2-predeploy/` | **Done** — `postgres.dump` (~2.2MB) |
| Pre-deploy (script) | `/opt/collection-backups/20260717T211618Z` | **Done** — `./scripts/backup.sh` |
| Post-deploy (labeled) | `/opt/collection-backups/20260717T211618Z-stage10-2-postdeploy/` | **Done** — `postgres.dump` (~2.2MB) |
| Post-deploy (script) | `/opt/collection-backups/20260717T212113Z` | **Done** — `./scripts/backup.sh` |

---

## Pre / post counts (MATCH)

| Metric | Pre | Post | Match |
|--------|-----|------|-------|
| payments | 3 | 3 | **MATCH** |
| cash_handover_requests | 1 | 1 | **MATCH** |
| collector_wallets | 3 | 3 | **MATCH** |
| branch_cashboxes | 2 | 2 | **MATCH** |
| payment_reversals | 3 | 3 | **MATCH** |
| customers | 6964 | 6964 | **MATCH** |
| tickets | 2 | 2 | **MATCH** |
| tasks | 4 | 4 | **MATCH** |
| installations | 4 | 4 | **MATCH** |
| crm_leads | 1 | 1 | **MATCH** |
| services | 1 | 1 | **MATCH** |
| service_packages | 2 | 2 | **MATCH** |
| inventory_on_hand_sum | 18.000 | 18.000 | **MATCH** |

**COUNTS_MATCH:** yes

---

## Deploy steps

1. BEFORE `.deployed-sha` = `f8b31bb4bd0a2195563a77328c2aa10be2da8357`
2. Pre backup + financial/inventory counts
3. `./scripts/sync-to-vps.sh` from code tip `50c6e66…`
4. VPS `./scripts/deploy.sh` — migrate **Nothing to migrate**; RolePermissionSeeder; admin install skipped (Super Admin present)
5. Re-seeded `RolePermissionSeeder` (idempotent)
6. Wrote host `.deployed-sha` = `50c6e66…`; set `APP_STAGE=10.2-production-recovery`, `APP_COMMIT_SHA`, `APP_BRANCH`; force-recreated backend/queue/scheduler; copied `.deployed-sha` / `.deployed-at` into containers
7. Post counts **MATCH**; post backup
8. Committed delivery report; re-synced tip; updated `.deployed-sha` / `APP_COMMIT_SHA` to final tip

---

## Version / smoke

| Check | Result |
|-------|--------|
| `/api/v1/health` → `deployment.stage` | **`10.2-production-recovery`** |
| git_sha / commit_sha | `d12e08b4990220e36ad26b1daa3b5113834de81f` (= tip) |
| branch | `cursor/stage-10-2-production-recovery` |
| migration_batch | **19** (`2026_07_17_210000_stage102_service_lifecycle_recovery`) |
| `/en/apps` | **200** |
| `/fa/apps` | **200** |
| `/en/services` | **200** |
| Radius in `.env` | **absent / empty** |
| Compose | backend/frontend healthy; nginx/postgres/redis/queue/scheduler up |

---

## Demo cleanup (`stage102:cleanup-demo`)

| Mode | Result |
|------|--------|
| `--dry-run` | **23 candidates** reported |
| `--apply` | **NOT applied** |

Dry-run candidates include clear `STAGE* TEST` / `DO NOT USE` rows (customers, lead, branches, users, tickets, tasks, installations), but also ambiguous matches:

- `tasks` id 3 — label `01-TSK-2026-000001` (matched on description only)
- `installations` id 1 — label `1` (matched on notes only)
- `customers` id 4183 — `STAGE5 TEST CUSTOMER - DO NOT USE` with `payments_count: 3` (command would not touch payments, but still flagged for human review)

Per deploy rule: apply only when candidates are **clearly** STAGE* TEST / DO NOT USE **and** safe. Ambiguous rows present → left dry-run only for human review.

Protected: payments and stock_transactions never deleted by this command.

---

## Explicit non-goals

- Does not merge PRs
- Does not start Stage 11
- Does not enable Radius
- Does not alter payment/wallet/handover/cashbox math
- Does not delete SSH keys (**KEY_KEPT**)
- Does not auto-apply demo cleanup with ambiguous candidates

## Related docs

- [STAGE_10_2_PRODUCTION_RECOVERY.md](STAGE_10_2_PRODUCTION_RECOVERY.md)
- [DEMO_PLACEHOLDER_CLEANUP.md](DEMO_PLACEHOLDER_CLEANUP.md)
- [STAGE_10_1_DELIVERY_REPORT.md](STAGE_10_1_DELIVERY_REPORT.md)

---

## Closeout verification

| Field | Value |
|-------|-------|
| tip SHA | `d12e08b4990220e36ad26b1daa3b5113834de81f` |
| production SHA (`/api/v1/health`) | `d12e08b4990220e36ad26b1daa3b5113834de81f` |
| stage | `10.2-production-recovery` |
| COUNTS_MATCH | yes |
| KEY_KEPT | yes |
| demo cleanup --apply | skipped (ambiguous dry-run candidates) |

---

## Follow-up: Stage 10.3 cleanup applied

Ambiguous Stage 10.2 dry-run candidates were **human-reviewed** and applied under Stage 10.3 via `stage103:cleanup-demo --apply` with manifest `docs/manifests/stage103-demo-cleanup.json` (2026-07-18). Payments and stock_transactions unchanged. See [STAGE_10_3_CLEANUP_RESULT.md](STAGE_10_3_CLEANUP_RESULT.md) and [STAGE_10_3_DEMO_DATA_REVIEW.md](STAGE_10_3_DEMO_DATA_REVIEW.md).

