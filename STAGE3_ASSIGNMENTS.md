# Stage 3 — Assignments

How customer debt assignments work in the Collection Management System.

## Workflow

1. Customer is mapped to a branch (Stage 2) and has outstanding invoices.
2. Manager assigns customer → collector (`status=assigned`, `is_active=true`).
3. Collector accepts / first-views; field visits move status to `in_progress`.
4. Manager may **reassign** (old row inactive + new active) or **cancel**.
5. Terminal inactive statuses: `closed`, `cancelled`, `reassigned`, `fully_resolved`.

UI: `/assignments`, `/assignments/new`, `/assignments/unassigned`, `/assignments/bulk`, `/assignments/workload`, `/assignments/{id}`.

## Sources (`assignment_source`)

| Value | When |
|-------|------|
| `manual` | Single `POST /assignments` (default) |
| `bulk` | `POST /assignments/bulk` |
| `auto` | Confirmed after `auto-preview` / `auto-confirm` |
| `reassign` | Created by reassignment |

## Priorities

`low` · `normal` (default) · `high` · `urgent`

## Statuses vs active flag

Active (`is_active=true`): `assigned`, `accepted`, `in_progress`

Inactive (`is_active=false`): `closed`, `cancelled`, `reassigned`, `fully_resolved`

One customer may only have **one active** assignment at a time (enforced on create).

## Rules

- Customer must be branch-mapped, not inactive/archived.
- Collector must be active and share the customer’s branch.
- Collector `max_active_assignments` (if set) is enforced.
- Debt snapshot frozen at assign time (not live Zoho):
  - `debt_snapshot_outstanding`, `debt_snapshot_overdue`, `debt_snapshot_invoice_count`, `debt_snapshot_currency`
  - `oldest_due_date_snapshot`, `days_overdue_snapshot`
- `allow_shared_assignment` is stored; concurrent active assignments are still blocked.

## Reassignment

`POST /assignments/{id}/reassign` (`assignments.reassign`):

- Requires an **active** assignment.
- Marks old as `reassigned` / inactive; creates a new active assignment for the new collector (`assignment_source=reassign`, `reassigned_from_id` set).

## Cancel

`POST /assignments/{id}/cancel` (`assignments.cancel`) — active only → `cancelled` / inactive.

## Bulk & auto-assign

- **Bulk** — `POST /assignments/bulk` with `customer_ids` + `collector_id`. Batches over `COLLECTION_BULK_SYNC_THRESHOLD` (default 50) are queued (`ProcessBulkAssignmentJob`); smaller runs sync.
- **Auto** — `POST /assignments/auto-preview` then `POST /assignments/auto-confirm` with `operation_id`. Distributes unassigned debtors across eligible collectors (workload-aware).

## Debtors helpers

- `GET /assignments/unassigned-debtors` — active customers with no active assignment.
- `GET /collectors/workload` — active assignment counts vs caps.
- `GET /assignments/export` — CSV of assignments.

## Permissions (RolePermissionSeeder)

| Permission | Typical roles |
|------------|---------------|
| `assignments.view` | All collection roles |
| `assignments.manage` | Super Admin, Central Finance, Branch Manager |
| `assignments.reassign` | Super Admin, Central Finance, Branch Manager |
| `assignments.cancel` | Super Admin, Branch Manager |
| `assignments.export` | Seeded for managers/finance (export route gated by `assignments.view`) |

Collectors: view own assignments; accept/viewed endpoints; no manage/reassign/cancel.
