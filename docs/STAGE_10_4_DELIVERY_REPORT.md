# Stage 10.4 — Delivery report

**PR:** https://github.com/nimroozy/finance/pull/20  
**Branch:** `cursor/stage-10-4-production-acceptance-closure`  
**PR target:** `cursor/stage-10-3-functional-acceptance`  
**Stage label:** `10.4-production-acceptance-closure`  
**Final SHA:** `f22cadc8eeb4df60917c503b6335e1137c90e36a`  

## Starting point

* Expected / actual starting SHA: `87f04ab63c0c4ffa50e7cdc264ad35212938d01f` — **MATCH**  
* Prior stage `f22cadc8eeb4df60917c503b6335e1137c90e36a` — **MATCH**  
* Prior stage: `10.3-functional-acceptance`

## What shipped

Isolated acceptance environment (full compose + VPS sidecar), fail-closed `./scripts/run-acceptance.sh` (PHPUnit + Playwright Docker path for host-without-Node), acceptance-only verify API/CLI with production blocks, expanded AcceptanceSeeder fixtures (collector, invoice, ticket types, inventory locations), six-project real Playwright matrix (**108 passed / 0 required skipped**), hardened route crawler (**0 failures / 192 routes**), console/network guards, DB/stock asserts, stage label `10.4-production-acceptance-closure`, docs `STAGE_10_4_*`.

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
| Mocked Playwright | 60 | 0 | 2 optional |
| Real acceptance | 108 | 0 | **0 required** (+6 optional Zoho) |

## Browser matrix

desktop-en/fa, mobile-en/fa, small-mobile-en/fa — **all passed**.

## Production verification

| Probe | Value |
|-------|-------|
| Production / health / `.deployed-sha` / `APP_COMMIT_SHA` / frontend_version | `f22cadc8eeb4df60917c503b6335e1137c90e36a` |
| Stage | `10.4-production-acceptance-closure` |
| Pre backup | `/opt/collection-backups/20260718T091544Z-stage10-4-final-predeploy/` |
| Post backup | see SHA verification after deploy |
| payments / handovers / wallets / cashboxes / reversals | 3 / 1 / 3 / 2 / 3 — **MATCH** |
| inventory txns / on_hand | 14 / 18.000 — **MATCH** |
| `/api/v1/acceptance/*` in production | **404** |
| Queue / scheduler | up |
| KEY_KEPT | yes |

## Defects

Critical/high: none. Medium/cosmetic: `STAGE_10_4_KNOWN_ISSUES.md`.

## Deferred

Stage 11 not started. Radius not connected.
