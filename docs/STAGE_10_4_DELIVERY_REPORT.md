# Stage 10.4 — Delivery report

**PR title:** Stage 10.4 production acceptance closure  
**Branch:** `cursor/stage-10-4-production-acceptance-closure`  
**PR target:** `cursor/stage-10-3-functional-acceptance`  
**Stage label:** `10.4-production-acceptance-closure`  
**Draft PR:** _(fill)_  
**Final SHA:** _(fill after deploy)_  

## Starting point

Verified in `STAGE_10_4_STARTING_SHA.md`:

* Expected / actual SHA: `87f04ab63c0c4ffa50e7cdc264ad35212938d01f`
* Production stage before change: `10.3-functional-acceptance`
* Result: **MATCH** — no production correction required

## What shipped

1. Isolated acceptance Docker stack (`docker-compose.acceptance.yml`, `.env.acceptance.example`)
2. `./scripts/run-acceptance.sh` end-to-end runner (fail-closed, no silent required skips)
3. Acceptance-only verify API + CLI (`acceptance:assert`, `acceptance:reset`) with production blocks
4. `AcceptanceSeeder` production/database guards + auth edge-case users
5. Real Playwright matrix: desktop/mobile/small-mobile × en/fa
6. Console/network guards, DB assertions, route crawler, artifact summaries
7. Stage label `10.4-production-acceptance-closure` + frontend commit SHA build arg
8. Documentation set `docs/STAGE_10_4_*` + roadmap/matrix updates

## Acceptance environment details

| Item | Value |
|------|-------|
| Compose project | collection-acceptance |
| Database | collection_acceptance |
| Cache/queue prefix | collection_acceptance |
| URL | http://127.0.0.1:18080 |
| AcceptanceSeeder on production | **never** |
| Zoho | test_adapter / sandbox — no uncontrolled production writes |
| WhatsApp send | false |
| Radius | false |

## Suite totals

| Suite | Passed | Failed | Skipped |
|-------|--------|--------|---------|
| Backend | _(fill)_ | _(fill)_ | _(fill)_ |
| Mocked Playwright | _(fill)_ | _(fill)_ | _(fill)_ |
| Real acceptance | _(fill)_ | _(fill)_ | **0 required** |
| Optional Zoho | — | — | separate |

## Browser results

| Project | Result |
|---------|--------|
| desktop-en | _(fill)_ |
| desktop-fa | _(fill)_ |
| mobile-en | _(fill)_ |
| mobile-fa | _(fill)_ |
| small-mobile-en | _(fill)_ |
| small-mobile-fa | _(fill)_ |

## Production verification (post-deploy)

| Probe | Value |
|-------|-------|
| Production SHA | _(fill)_ |
| `.deployed-sha` | _(fill)_ |
| Health SHA | _(fill)_ |
| System-version SHA | _(fill)_ |
| Backend-container SHA | _(fill)_ |
| Frontend-build SHA | _(fill)_ |
| Backup paths | _(fill)_ |
| Financial counts | _(fill MATCH)_ |
| Inventory on_hand | _(fill MATCH)_ |
| Stock ledger reconcile | _(fill)_ |
| Zoho scheduler | _(fill)_ |
| Queues | _(fill)_ |
| Demo/AcceptanceSeeder on prod | **not run** |

## Defects

* Critical: none known
* High: none known
* Medium / cosmetic: see `STAGE_10_4_KNOWN_ISSUES.md`

## Deferred

Stage 11 dashboards / reporting — **not started**. Radius — **not connected**.
