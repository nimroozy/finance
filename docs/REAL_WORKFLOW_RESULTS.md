# Real workflow results — Stage 10.4 production acceptance closure

## Purpose

Document how to seed acceptance fixtures and run the **real** Playwright acceptance suite against a live API (no mocked network). Mocked UI regression stays on `npm run e2e:mocked`. Real acceptance does **not** count mocked results.

## Preferred: full isolated runner

```bash
cp .env.acceptance.example .env.acceptance
# fill DB_PASSWORD, REDIS_PASSWORD, APP_KEY, etc.
./scripts/run-acceptance.sh
```

This starts `docker-compose.acceptance.yml` (separate Postgres/Redis/volumes/queues), resets `collection_acceptance`, seeds permissions + `AcceptanceSeeder`, runs backend + mocked + real suites, DB assertions, route crawler, and console/network audit. Exits non-zero on any required failure.

## Seed acceptance fixtures (manual)

```bash
# Only when APP_ENV is acceptance|testing|local and DB name contains acceptance (for APP_ENV=acceptance)
cd backend
php artisan db:seed --class=AcceptanceSeeder
# or
php artisan acceptance:reset --force
```

Creates:

| Kind | Key |
|------|-----|
| Branch | code `ACC` |
| Users | `ACCEPTANCE-admin`, `ACCEPTANCE-manager`, `ACCEPTANCE-sales`, `ACCEPTANCE-noc`, `ACCEPTANCE-collector` |
| Edge users | `ACCEPTANCE-disabled`, `ACCEPTANCE-nobranch`, `ACCEPTANCE-forcepw` |
| Password | `AcceptancePass1!` |
| Zoho-mirrored customer | `zoho_contact_id=ACCEPTANCE-ZOHO-1` |
| Lead | `ACC-LEAD-000001` (approved, ready to link) |
| Services / queues | active + pending activation + change/relocation/hold/cancel fixtures |

**Hard rule:** `AcceptanceSeeder` throws if `APP_ENV=production`. Never run against the production database.

## Run real acceptance E2E

Required env (suite **fails** if missing — no silent skip of required flows):

| Variable | Required | Purpose |
|----------|----------|---------|
| `E2E_ACCEPTANCE=1` | Yes | Enables acceptance runner |
| `BASE_URL` or `PLAYWRIGHT_BASE_URL` | Yes | Frontend origin |
| `E2E_USER` | Yes | Login (e.g. `ACCEPTANCE-admin`) |
| `E2E_PASSWORD` | Yes | Password (e.g. `AcceptancePass1!`) |

Optional:

| Variable | Purpose |
|----------|---------|
| `E2E_ZOHO_WRITE=1` | Enables optional external Zoho **write** test (otherwise that single test is skipped and reported as optional) |

```bash
cd frontend
E2E_ACCEPTANCE=1 \
BASE_URL=http://127.0.0.1:18080 \
E2E_USER=ACCEPTANCE-admin \
E2E_PASSWORD='AcceptancePass1!' \
npm run e2e:acceptance
```

Projects: `desktop-en`, `desktop-fa`, `mobile-en`, `mobile-fa`, `small-mobile-en`, `small-mobile-fa`.

Required skipped count must be **0**.

## Coverage (acceptance.spec.ts)

1. Authentication (valid, invalid, disabled) + launcher primary apps
2. Customers — Zoho-mirrored search + DB assert
3. CRM lead create → Zoho link + DB assert
4. Service activation checklist evidence
5. Change-request queue + NOC workspace
6. Global search fixtures
7. Administration system version (stage, no secrets)
8. Stock reconciliation acceptance endpoint

Optional (skipped unless `E2E_ZOHO_WRITE=1`): external Zoho write probe.

## Mocked suite

```bash
cd frontend
npm run e2e:mocked
```

## Related

- Seeder: `backend/database/seeders/AcceptanceSeeder.php`
- Specs: `frontend/e2e-real/acceptance.spec.ts`
- Runner: `scripts/run-acceptance.sh`
- Results: `docs/STAGE_10_4_ACCEPTANCE_RESULTS.md`
