# Stage 10.4 — Acceptance results

**Stage:** `10.4-production-acceptance-closure`  
**Runner:** `./scripts/run-acceptance.sh`  
**Artifacts:** `artifacts/acceptance/` (gitignored binaries; lightweight summaries in docs)

## Environment

| Item | Value |
|------|-------|
| APP_ENV | acceptance |
| DB | collection_acceptance (isolated) |
| Redis prefix | collection_acceptance |
| WhatsApp send | false |
| Radius | false |
| Zoho mode | test_adapter (not uncontrolled production writes) |

## Suite totals

Populate from the latest `./scripts/run-acceptance.sh` run. Do **not** combine suites.

| Suite | Passed | Failed | Skipped |
|-------|--------|--------|---------|
| Backend (`php artisan test`) | _(fill)_ | _(fill)_ | _(fill)_ |
| Mocked Playwright (`npm run e2e:mocked`) | _(fill)_ | _(fill)_ | _(fill)_ |
| Real acceptance (`npm run e2e:acceptance`) | _(fill)_ | _(fill)_ | **0 required** |

Optional external Zoho write: skipped unless `E2E_ZOHO_WRITE=1` (reported separately).

## Browser matrix

| Project | Result |
|---------|--------|
| desktop-en | _(fill)_ |
| desktop-fa | _(fill)_ |
| mobile-en | _(fill)_ |
| mobile-fa | _(fill)_ |
| small-mobile-en | _(fill)_ |
| small-mobile-fa | _(fill)_ |

## Workflow coverage (real acceptance)

| Area | Covered in suite | Notes |
|------|------------------|-------|
| Authentication | yes | valid login, invalid password, disabled user; RTL via fa projects |
| App launcher | yes | primary apps, counts API, no submodule cards |
| Customers | yes | Zoho-mirrored search + DB assert |
| Payments | partial | API/permission surfaces via route crawler; Zoho writes via test adapter only |
| Collections | partial | route crawl + backend RouteWorkflowTest |
| Tasks / Support | partial | routes + backend Stage7 suites |
| CRM | yes | lead create + Zoho link + DB assert |
| Installations | partial | routes + backend |
| Inventory | yes | stock reconciliation endpoint |
| Services | yes | activation checklist + queues |
| NOC | yes | workspace load |
| Administration | yes | system version / stage / no secrets |
| Global search | yes | fixture queries |
| Notifications / attachments | partial | backend coverage + crawler |

Deep write-path coverage for every bullet in the Stage 10.4 brief continues to expand; known gaps are listed in `STAGE_10_4_KNOWN_ISSUES.md`.
