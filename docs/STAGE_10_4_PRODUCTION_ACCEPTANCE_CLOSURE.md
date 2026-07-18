# Stage 10.4 — Production acceptance closure

**Branch:** `cursor/stage-10-4-production-acceptance-closure`  
**Target base:** `cursor/stage-10-3-functional-acceptance`  
**Stage label:** `10.4-production-acceptance-closure`  
**Do not begin Stage 11.**

## Objective

Close remaining production-acceptance gaps by proving existing features on a **real** stack:

* Real frontend, Laravel backend, PostgreSQL, Redis, queue worker, scheduler
* Real authentication, permissions, API requests, DB persistence
* English + Persian; desktop + mobile viewports

Mocked Playwright remains for fast regression and **does not** count as production acceptance.

## Hard rules

* No Stage 11 / new business modules / SAS Radius
* No changes to financial calculations, wallet/cashbox/handover totals, payment reconciliation, or immutable inventory-ledger rules
* Zoho Books remains SoT for customers, invoices, payments, credit notes, accounting balances
* ERP remains SoT for CRM, tasks, support, NOC, installations, inventory, service lifecycle, equipment, operational history
* `AcceptanceSeeder` never runs in production
* Required real E2E skipped count must be **0**

## Acceptance environment

Isolated via `docker-compose.acceptance.yml` + `.env.acceptance`:

| Setting | Value |
|---------|-------|
| `APP_ENV` | `acceptance` |
| `DB_DATABASE` | `collection_acceptance` |
| `CACHE_PREFIX` | `collection_acceptance` |
| Queues | prefixed `collection_acceptance` |
| Storage | dedicated acceptance volumes |
| URL | `http://127.0.0.1:18080` (default) |
| `WHATSAPP_SEND_ENABLED` | `false` |
| `RADIUS_ENABLED` | `false` |
| `E2E_ACCEPTANCE` | `1` |
| Zoho | `ZOHO_WRITE_MODE=test_adapter` (sandbox/test adapter — not uncontrolled production writes) |

## Runner

```bash
cp .env.acceptance.example .env.acceptance
# fill secrets
./scripts/run-acceptance.sh
```

The runner validates env, starts the acceptance stack, resets DB, migrates, seeds permissions + `AcceptanceSeeder`, verifies health, runs backend tests, mocked E2E, real acceptance (six projects), DB assertions, route crawler, console/network audit, exports artifacts, resets fixtures, and exits non-zero on failure.

## Suite separation

| Suite | Command |
|-------|---------|
| Backend | `php artisan test` |
| Mocked Playwright | `npm run e2e:mocked` |
| Real acceptance | `npm run e2e:acceptance` |

Reports list totals **separately**. Required real acceptance skipped count must be zero.

## Browser matrix

| Project | Viewport | Locale |
|---------|----------|--------|
| desktop-en | 1440×900 | en |
| desktop-fa | 1440×900 | fa |
| mobile-en | 390×844 | en |
| mobile-fa | 390×844 | fa |
| small-mobile-en | 320×700 | en |
| small-mobile-fa | 320×700 | fa |

## Verification helpers

* Acceptance-only API under `/api/v1/acceptance/*` (404 outside `acceptance|testing|local`)
* CLI: `php artisan acceptance:assert`, `php artisan acceptance:reset --force`
* Playwright helpers: DB assert, console/network guards, overflow checks

## Documentation set

* `STAGE_10_4_STARTING_SHA.md`
* `STAGE_10_4_SHA_VERIFICATION.md`
* `STAGE_10_4_ACCEPTANCE_RESULTS.md`
* `STAGE_10_4_ROUTE_RESULTS.md`
* `STAGE_10_4_CONSOLE_NETWORK_RESULTS.md`
* `STAGE_10_4_DATABASE_ASSERTIONS.md`
* `STAGE_10_4_STOCK_RECONCILIATION.md`
* `STAGE_10_4_KNOWN_ISSUES.md`
* `STAGE_10_4_DELIVERY_REPORT.md`
