# Manager guide

Day-to-day ops for **Branch Manager**, **Central Finance**, and **Super Administrator** after Stage 3.

Production: https://finance.mns.af

## Assignment dashboard

| Path | Use |
|------|-----|
| `/assignments` | Filter by status / active / collector / priority |
| `/assignments/unassigned` | Debtors with no active assignment |
| `/assignments/new` | Assign one customer → collector |
| `/assignments/[id]` | Detail, history, reassign / cancel |
| `/assignments/bulk` | Multi-customer assign + auto preview/confirm |
| `/assignments/workload` | Collector load vs `max_active_assignments` |
| `/collectors` | Create/update collector profiles |

Rules & sources: [STAGE3_ASSIGNMENTS.md](STAGE3_ASSIGNMENTS.md).

## Bulk assign & auto

1. Pick customers (or use unassigned list) and a collector → **Bulk**.
2. Or **Auto preview** → review distribution → **Confirm**.
3. Large bulk (> `COLLECTION_BULK_SYNC_THRESHOLD`, default 50) runs on the queue — watch `queue-worker` logs if counts lag.

## Workload

Use `/assignments/workload` (API: `GET /collectors/workload`) before heavy assign days. Inactive collectors and collectors at max active load are skipped by eligibility checks.

## Routes

| Path | Use |
|------|-----|
| `/routes` · `/routes/new` · `/routes/[id]` | Draft → publish → collector starts/completes |

Managers need `routes.manage` to create/edit/publish/cancel/reorder stops. Collectors with `routes.view` start and complete. Details: [ROUTES_AND_VISITS.md](ROUTES_AND_VISITS.md).

## Promises — fulfill & supersede

UI: `/promises`

- **Fulfill** (`promises.manage`) — manual confirmation only. No payment posting in Stage 3.
- **Supersede** — closes old as `superseded`, opens a new promise.
- Collectors may create/cancel; they cannot fulfill.
- Daily job keeps `due_soon` / `due_today` / `overdue` in sync.

See [PROMISE_TO_PAY.md](PROMISE_TO_PAY.md).

## Escalations

Visits with outcomes `requested_manager` / `disputed_balance` (or explicit flag) notify branch managers and set `escalation_flag`. UI: `/escalations`. Permissions `escalations.view` / `escalations.manage` are seeded for managers.

## Visits & evidence

- Review `/visits` and `/visits/[id]` — outcomes, GPS risk (`ok` / `warning` / `high_risk`), distance.
- Managers (`visits.manage`) can add a **correction note** (does not rewrite the original visit).
- Download evidence via secure API download (not public links).

## Reports

UI: `/reports/collection`

| Report API | Permission |
|------------|------------|
| Assignments by collector | `reports.assignments` |
| Unassigned debtors | `reports.assignments` |
| Visits by outcome | `reports.visits` |
| GPS mismatch | `reports.visits` |
| Overdue promises | `reports.promises` |

## Role cheat sheet

| Capability | Branch Manager | Central Finance | Collector |
|------------|----------------|-----------------|-----------|
| Assign / bulk / auto | ✓ | ✓ | — |
| Reassign | ✓ | ✓ | — |
| Cancel assignment | ✓ | — | — |
| Fulfill promise | ✓ | ✓ | — |
| Routes manage | ✓ | view only | start/complete |
| Reports | ✓ | ✓ | — |

Stage 4 (payments / receipts / wallets) is **not** available yet — do not promise cashiers or receipt print from this release.
