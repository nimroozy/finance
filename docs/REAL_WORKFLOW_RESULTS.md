# Real workflow results — Stage 10.3 functional acceptance

## Purpose

Document how to seed acceptance fixtures and run the **real** Playwright acceptance suite against a live API (no mocked network). Mocked UI regression stays on `npm run e2e:mocked`.

## Seed acceptance fixtures

From the backend app directory (roles must already exist or will be seeded):

```bash
cd backend
php artisan db:seed --class=AcceptanceSeeder
```

Creates:

| Kind | Key |
|------|-----|
| Branch | code `ACC` |
| Users | `ACCEPTANCE-admin`, `ACCEPTANCE-manager`, `ACCEPTANCE-sales`, `ACCEPTANCE-noc` |
| Password | `AcceptancePass1!` |
| Zoho-mirrored customer | `zoho_contact_id=ACCEPTANCE-ZOHO-1` |
| Lead | `ACC-LEAD-000001` (approved, ready to link) |
| Services / queues | active + pending activation + change request, relocation, finance hold, cancellation fixtures |

Do **not** run this seeder against production customer data without a dedicated acceptance tenant.

## Run real acceptance E2E

Required env (suite **fails** if missing — no silent skip of required flows):

| Variable | Required | Purpose |
|----------|----------|---------|
| `E2E_ACCEPTANCE=1` | Yes | Enables acceptance runner |
| `BASE_URL` or `PLAYWRIGHT_BASE_URL` | Yes | Frontend origin |
| `E2E_USER` | Yes | Login (e.g. `ACCEPTANCE-manager`) |
| `E2E_PASSWORD` | Yes | Password (e.g. `AcceptancePass1!`) |

Optional:

| Variable | Purpose |
|----------|---------|
| `E2E_ZOHO_WRITE=1` | Enables optional external Zoho **write** test (otherwise that single test is skipped) |
| `E2E_API_URL` | API origin if different from frontend proxy |

```bash
cd frontend
E2E_ACCEPTANCE=1 \
BASE_URL=http://127.0.0.1:3000 \
E2E_USER=ACCEPTANCE-manager \
E2E_PASSWORD='AcceptancePass1!' \
npm run e2e:acceptance
```

Without `E2E_ACCEPTANCE=1` or required credentials, `npm run e2e:acceptance` exits non-zero immediately.

## Coverage (acceptance.spec.ts)

1. Login
2. Apps launcher (primary apps grid)
3. Create lead → link Zoho-mirrored customer (acceptance fixtures)
4. Service activation checklist UI (evidence from API)
5. Change-request queue loads real workflow records

Optional (skipped unless `E2E_ZOHO_WRITE=1`): external Zoho write probe.

## Mocked suite

```bash
cd frontend
npm run e2e:mocked
# or a subset, e.g.
npx playwright test e2e/stage91-launcher.spec.ts e2e/stage10-services.spec.ts
```

## Related

- Seeder: `backend/database/seeders/AcceptanceSeeder.php`
- Specs: `frontend/e2e-real/acceptance.spec.ts`
- Mocked smoke history: `docs/REAL_E2E_TESTING.md`
