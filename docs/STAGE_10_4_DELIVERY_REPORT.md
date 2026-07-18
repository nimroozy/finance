# Stage 10.4 — Delivery report

**PR:** https://github.com/nimroozy/finance/pull/20  
**Branch:** `cursor/stage-10-4-production-acceptance-closure`  
**PR target:** `cursor/stage-10-3-functional-acceptance`  
**Stage label:** `10.4-production-acceptance-closure`  
**Final SHA:** `a0831c99e57d5624bef4561d233b865cc33246aa`

## Starting point

* Expected / actual starting SHA: `a0831c99e57d5624bef4561d233b865cc33246aa` — **MATCH**  
* Prior stage: `10.3-functional-acceptance`

## What shipped

Isolated acceptance environment (full compose + VPS sidecar), fail-closed `./scripts/run-acceptance.sh`, acceptance-only verify API/CLI with production blocks, six-project real Playwright matrix (**54 passed / 0 required skipped**), route crawler, console/network guards, DB/stock asserts, stage label `10.4-production-acceptance-closure`, docs `STAGE_10_4_*`.

## Acceptance environment

| Item | Value |
|------|-------|
| Sidecar URL | `http://127.0.0.1:18080` |
| Database | `collection_acceptance` |
| Cache/queue prefix | `collection_acceptance` |
| Zoho | test_adapter |
| WhatsApp / Radius | false |
| AcceptanceSeeder on production | **never** (0 ACCEPTANCE users) |

## Suite totals

| Suite | Passed | Failed | Skipped |
|-------|--------|--------|---------|
| Backend | 301 | 0 | 0 |
| Real acceptance | 54 | 0 | **0 required** (+6 optional Zoho) |

## Browser matrix

desktop-en/fa, mobile-en/fa, small-mobile-en/fa — **all passed**.

## Production verification

| Probe | Value |
|-------|-------|
| Production / health / `.deployed-sha` / `APP_COMMIT_SHA` / frontend_version | `a0831c99e57d5624bef4561d233b865cc33246aa` |
| Stage | `10.4-production-acceptance-closure` |
| Pre backup | `/opt/collection-backups/20260718T075711Z-stage10-4-predeploy/` |
| Post backup | `/opt/collection-backups/20260718T080756Z-stage10-4-postdeploy/` |
| payments / handovers / wallets / cashboxes / reversals | 3 / 1 / 3 / 2 / 3 — **MATCH** |
| inventory txns / on_hand | 14 / 18.000 — **MATCH** |
| `/api/v1/acceptance/*` in production | **404** |
| Queue / scheduler | up |
| KEY_KEPT | yes |

## Defects

Critical/high: none. Medium/cosmetic: `STAGE_10_4_KNOWN_ISSUES.md`.

## Deferred

Stage 11 not started. Radius not connected.
