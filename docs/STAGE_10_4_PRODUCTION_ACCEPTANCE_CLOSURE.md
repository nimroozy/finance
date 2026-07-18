# Stage 10.4 — Production acceptance closure

Closes remaining production-acceptance gaps for the integrated Stage 10 stack without starting Stage 11, without Radius, and without mutating production financial or inventory truth via AcceptanceSeeder.

## Objective

Prove existing features with real frontend, Laravel, PostgreSQL, Redis, queue worker, scheduler, auth, permissions, API persistence, English/Persian, and desktop/mobile viewports. Mocked Playwright remains for fast regression but does not count as production acceptance.

## Starting SHA

Verified MATCH at `87f04ab63c0c4ffa50e7cdc264ad35212938d01f` — see `STAGE_10_4_STARTING_SHA.md`.

## Acceptance isolation

* Compose: `docker-compose.acceptance.yml` (full) and `docker-compose.acceptance.sidecar.yml` (VPS low-memory)
* Env: `APP_ENV=acceptance`, DB `collection_acceptance`, `CACHE_PREFIX=collection_acceptance`, Redis namespace/DB separate from production
* `AcceptanceSeeder` refuses `APP_ENV=production` and non-acceptance DB names
* Acceptance verify routes middleware `acceptance.env` → **404 in production**

## Runner

`./scripts/run-acceptance.sh` validates env, starts stack, resets DB, migrates, seeds permissions + AcceptanceSeeder, verifies health, runs backend tests, mocked E2E, real acceptance (six projects), DB assertions, route crawler, console/network audit, exports artifacts, resets fixtures, exits non-zero on failure. On VPS (no host Node), Playwright/crawler run inside `mcr.microsoft.com/playwright` on `collection-acceptance-net`.

## Suites

* Backend: `php artisan test` / PHPUnit — **301/0/0**
* Mocked: `npm run e2e:mocked` — **60/0/2 optional**
* Real: `npm run e2e:acceptance` — **108/0/0 required** (+6 optional Zoho)

## Final SHA

See `STAGE_10_4_SHA_VERIFICATION.md` and `STAGE_10_4_DELIVERY_REPORT.md`.
