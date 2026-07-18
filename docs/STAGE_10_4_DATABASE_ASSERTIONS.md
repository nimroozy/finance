# Stage 10.4 — Database assertions

## Helpers

| Mechanism | Availability |
|-----------|----------------|
| `POST /api/v1/acceptance/assert` | `APP_ENV` ∈ acceptance\|testing\|local only |
| `GET /api/v1/acceptance/stock-reconciliation` | same |
| `php artisan acceptance:assert` | blocked in production |
| Playwright `assertDb()` | calls acceptance API |

Production requests receive **404**.

## After-write checks

Workflow tests assert:

* Record exists / updated
* Status, branch, customer, actor where applicable
* No unauthorized side effects (via entity expectations)
* Stock ledger identity via reconciliation helper

## Fixture anchors

| Entity | Key |
|--------|-----|
| Customer | `zoho_contact_id=ACCEPTANCE-ZOHO-1` |
| Lead | `lead_number=ACC-LEAD-000001` |
| Services | `ACC-SVC-000001`, `ACC-SVC-PENDING`, `ACC-SVC-CANCEL` |
| Users | `ACCEPTANCE-*` |

## Latest run

| Assertion | Result |
|-----------|--------|
| Acceptance customer active | _(fill)_ |
| Pending service commercial_status | _(fill)_ |
| Stock reconciliation | see `STAGE_10_4_STOCK_RECONCILIATION.md` |
