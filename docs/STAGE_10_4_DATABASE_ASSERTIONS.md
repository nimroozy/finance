# Stage 10.4 — Database assertions

## Helpers

| Mechanism | Availability |
|-----------|----------------|
| `POST /api/v1/acceptance/assert` | acceptance\|testing\|local only (404 in production) |
| `GET /api/v1/acceptance/stock-reconciliation` | same |
| `php artisan acceptance:assert` | blocked in production |
| Playwright `assertDb()` | calls acceptance API |

## Latest acceptance run

| Assertion | Result |
|-----------|--------|
| Customer `zoho_contact_id=ACCEPTANCE-ZOHO-1` status=active | **ok** |
| Lead create + Zoho link persistence | **ok** (assert after write) |
| Service `ACC-SVC-PENDING` commercial_status=pending_activation | **ok** |
| Stock reconciliation | **ok** (0=0) |
| Production ACCEPTANCE-% users | **0** (seeder never on prod) |
