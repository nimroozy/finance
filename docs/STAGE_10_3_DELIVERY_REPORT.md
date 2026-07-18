# Stage 10.3 Delivery Report

**Date (UTC):** 2026-07-18  
**Branch:** `cursor/stage-10-3-functional-acceptance`  
**VPS:** `root@209.38.194.184` (`/opt/collection-system`)  
**Deploy stamp:** _(filled after deploy)_  
**Tip SHA deployed:** _(filled after deploy)_  
**Production SHA:** _(filled after deploy)_  
**Do not merge until human review.** **Stage 11 not started.** **No Radius.**  
**KEY_KEPT** (`~/.ssh/id_ed25519` preserved — not deleted).  
**AcceptanceSeeder:** **skipped on production** (RolePermissionSeeder only).

---

## Summary

Stage **10.3 functional acceptance** deploy to production: stage label `10.3-functional-acceptance`, launcher/ActivationPanel/queues tip, reviewed demo cleanup already applied (see cleanup docs). Financial/inventory counts must **MATCH** pre/post. Four-way SHA match required.

---

## Pre-flight

| Check | Result |
|-------|--------|
| `git pull` | Already up to date |
| PHP filter tests | **59 passed** |
| `npm run lint` / `build` / `i18n:check` | **OK** |
| Playwright stage91 + stage10 (desktop+mobile) | **48 passed** |
| Stage label | `10.2-production-recovery` → `10.3-functional-acceptance` |

---

## Backups

| Phase | Path | Status |
|-------|------|--------|
| Pre-deploy (labeled) | `/opt/collection-backups/<STAMP>-stage10-3-predeploy/` | _(pending)_ |
| Post-deploy (labeled) | `/opt/collection-backups/<STAMP>-stage10-3-postdeploy/` | _(pending)_ |

---

## Pre / post counts

| Metric | Pre | Post | Match |
|--------|-----|------|-------|
| payments | | | |
| cash_handover_requests | | | |
| collector_wallets | | | |
| branch_cashboxes | | | |
| payment_reversals | | | |
| customers | | | |
| tickets | | | |
| tasks | | | |
| installations | | | |
| crm_leads | | | |
| services | | | |
| service_packages | | | |
| inventory_on_hand_sum | | | |
| stock_transactions | | | |

**COUNTS_MATCH:** _(pending)_

---

## Deploy steps

1. BEFORE `.deployed-sha` / `APP_COMMIT_SHA` = `fea840530c287b7bdc906b855706c4cf84203178` (10.2 tip)
2. Pre backup + financial/inventory counts
3. `./scripts/sync-to-vps.sh` from tip
4. VPS `./scripts/deploy.sh` — migrate; RolePermissionSeeder; **skip AcceptanceSeeder**
5. Write host `.deployed-sha` = tip; set `APP_STAGE=10.3-functional-acceptance`, `APP_COMMIT_SHA`, `APP_BRANCH`; recreate backend/queue/scheduler; copy `.deployed-sha` into containers
6. Four-way SHA verify; smoke routes; post counts; post backup
7. If delivery report updated after deploy → commit + re-sync + update `.deployed-sha` to new tip

---

## Version / smoke

| Check | Result |
|-------|--------|
| `/api/v1/health` → `deployment.stage` | _(pending)_ `10.3-functional-acceptance` |
| git_sha / commit_sha | _(pending)_ |
| branch | `cursor/stage-10-3-functional-acceptance` |
| `/en/apps` | _(pending)_ |
| `/fa/apps` | _(pending)_ |
| `/en/services` | _(pending)_ |
| `/en/services/cancellations` | _(pending)_ |

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

---

## Closeout verification

| Field | Value |
|-------|-------|
| tip SHA | _(pending)_ |
| `.deployed-sha` | _(pending)_ |
| health SHA | _(pending)_ |
| system version SHA | _(pending)_ |
| stage | `10.3-functional-acceptance` |
| COUNTS_MATCH | _(pending)_ |
| KEY_KEPT | yes |
| AcceptanceSeeder on prod | skipped |
