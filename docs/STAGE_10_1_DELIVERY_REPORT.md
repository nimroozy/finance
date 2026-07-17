# Stage 10.1 Delivery Report

**Date (UTC):** 2026-07-17  
**Branch:** `cursor/stage-10-1-integrated-stable`  
**Draft PR:** https://github.com/nimroozy/finance/pull/17  
**VPS:** `root@209.38.194.184` (`/opt/collection-system`)  
**Deploy stamp:** `20260717T195940Z`  
**Tip SHA deployed:** `2d990ea4b45401897771829e36813e5a18196a54`  
**Do not merge until human review.** **Stage 11 not started.** **No Radius.**  
**KEY_KEPT** (`~/.ssh/id_ed25519` preserved — not deleted).

---

## Summary

Stage **10.1 integrated stable** is live on production: Stages 7–10 + 9.1 shell on one tip, regression gates green, financial/inventory counts unchanged, version label `10.1-integrated-stable`.

Superseded drafts (preserve for audit — **not closed**):

- PR [#15](https://github.com/nimroozy/finance/pull/15) Stage 10 only — recommend close later as superseded by #17
- PR [#16](https://github.com/nimroozy/finance/pull/16) Stage 9.1 only — recommend close later as superseded by #17

---

## Local verification

| Gate | Result |
|------|--------|
| `php artisan test --filter='Stage7\|Stage71\|Stage8\|Stage9\|Stage91\|Stage10\|Authentication\|WhatsApp'` | **109 passed** (483 assertions) |
| `npm run lint` | **OK** |
| `npm run build` | **OK** |
| `npm run i18n:check` | **OK** (1795 keys) |
| Playwright stage7/71/8/9/91/10 (desktop+mobile) | **88 passed** |

### Playwright / shell fixes on tip

- Mobile Create/Confirm clicks intercepted by Stage 9.1 shell / bottom nav → `frontend/e2e/helpers.ts` + ConfirmDialog `z-[60]` / `pb-24`
- Shell `quickCreate` renamed to **Quick create** / **ایجاد سریع** (duplicate Create a11y name)

---

## Backups

| Phase | Path | Status |
|-------|------|--------|
| Pre-deploy | `/opt/collection-backups/20260717T195940Z-stage10-1-predeploy/` | **Done** — `postgres.dump` (~2.2MB) |
| Post-deploy (labeled) | `/opt/collection-backups/20260717T195940Z-stage10-1-postdeploy/` | **Done** — `postgres.dump` (~2.2MB) |
| Post-deploy (script) | `/opt/collection-backups/20260717T200446Z` | **Done** — `./scripts/backup.sh` |

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

---

## Deploy steps

1. BEFORE `.deployed-sha` = `71ee2d7a371cba353efd4ccffbb33444eb7ab7cb`
2. Pre backup + counts
3. `./scripts/sync-to-vps.sh` from tip (code `53f278b…`, docs tip `2d990ea…`)
4. VPS `./scripts/deploy.sh` — migrate **Nothing to migrate**; RolePermissionSeeder; admin install skipped
5. Catalog seeders (all **SEED_OK**): RolePermission, Stage10 types/tech/SLA, Stage9 categories/locations, Stage8 lead sources, Stage7 org/SLA/types/templates/escalation
6. Wrote host `.deployed-sha` = tip; set `APP_STAGE=10.1-integrated-stable`, `APP_COMMIT_SHA`, `APP_BRANCH`; recreated backend/queue/scheduler; copied `.deployed-sha` into container
7. Post counts **MATCH**; post backup

---

## Version / smoke

| Check | Result |
|-------|--------|
| `/api/v1/health` → `deployment.stage` | **`10.1-integrated-stable`** |
| `/api/v1/system/version` (auth) stage | **`10.1-integrated-stable`** |
| git_sha / commit_sha | `2d990ea4b45401897771829e36813e5a18196a54` |
| branch | `cursor/stage-10-1-integrated-stable` |
| migration_batch | **18** (ui_preferences latest) |
| `/en/apps` `/fa/apps` `/en/services/dashboard` | **200** (HTTPS; unauthenticated OK / login redirect OK) |
| Radius in `.env` | **empty** |
| Compose | backend/frontend healthy; nginx/postgres/redis/queue/scheduler up |

---

## Explicit non-goals

- Does not merge PRs or close #15/#16
- Does not start Stage 11
- Does not enable Radius
- Does not alter payment/wallet/handover/cashbox math
- Does not delete SSH keys (**KEY_KEPT**)

## Related docs

- [STAGE_10_1_INTEGRATED_STABLE.md](STAGE_10_1_INTEGRATED_STABLE.md)
- [BRANCH_INTEGRATION_HISTORY.md](BRANCH_INTEGRATION_HISTORY.md)
- [REGRESSION_TEST_MATRIX.md](REGRESSION_TEST_MATRIX.md)
- [PRODUCTION_SMOKE_TEST.md](PRODUCTION_SMOKE_TEST.md)
