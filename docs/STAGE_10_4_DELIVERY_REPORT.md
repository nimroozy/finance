# Stage 10.4 — Delivery report

**PR:** https://github.com/nimroozy/finance/pull/20  
**Branch:** `cursor/stage-10-4-production-acceptance-closure`  
**PR target:** `cursor/stage-10-3-functional-acceptance`  
**Stage label:** `10.4-production-acceptance-closure`  
**Final SHA:** _(filled after deploy verify)_  

## Starting point

Verified in `STAGE_10_4_STARTING_SHA.md`:

* Expected / actual SHA: `87f04ab63c0c4ffa50e7cdc264ad35212938d01f`
* Production stage before change: `10.3-functional-acceptance`
* Result: **MATCH**

## What shipped

1. Isolated acceptance stack + VPS sidecar compose  
2. `./scripts/run-acceptance.sh` (fail-closed)  
3. Acceptance-only verify API/CLI + production guards  
4. Real Playwright matrix (6 projects) — **54 passed, 0 required skipped**  
5. Route crawler, console/network guards, DB/stock asserts  
6. Stage label `10.4-production-acceptance-closure`  
7. Docs `docs/STAGE_10_4_*`

## Acceptance environment

| Item | Value |
|------|-------|
| Mode | sidecar (shared postgres/redis, isolated DB/prefix) |
| Database | `collection_acceptance` |
| Cache/queue prefix | `collection_acceptance` |
| URL | `http://127.0.0.1:18080` |
| AcceptanceSeeder on production | **never** (0 ACCEPTANCE users on prod) |
| Zoho | test_adapter |
| WhatsApp / Radius | false |

## Suite totals

| Suite | Passed | Failed | Skipped |
|-------|--------|--------|---------|
| Backend | 301 | 0 | 0 |
| Real acceptance | 54 | 0 | **0 required** (+6 optional Zoho) |

## Browser results

All six projects **passed**.

## Production verification

| Probe | Value |
|-------|-------|
| Pre backup | `/opt/collection-backups/20260718T075711Z-stage10-4-predeploy/` |
| Pre financial | payments 3, handovers 1, wallets 3, cashboxes 2, reversals 3 |
| Pre inventory | txns 14, on_hand 18.000 |
| AcceptanceSeeder on prod | skipped |
| Post values | _(fill)_ |

## Defects

* Critical / high: none known  
* Medium / cosmetic: `STAGE_10_4_KNOWN_ISSUES.md`

## Deferred

Stage 11 not started. Radius not connected.
