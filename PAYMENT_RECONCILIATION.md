# Payment reconciliation

Lightweight daily foundation in `PaymentReconciliationService` + `RunPaymentReconciliationJob`.

## Schedule

`routes/console.php` — daily job `payment-reconciliation-daily` (`RunPaymentReconciliationJob`), without overlapping. Requires Compose `scheduler` + `queue-worker`.

Config flag: `PAYMENT_RECONCILIATION_DAILY` / `payments.reconciliation.daily_enabled` (job is still registered; use env when wiring conditional enablement).

## What it computes

For a calendar day (and optionally per branch + a global null-branch summary):

| Metric | Source |
|--------|--------|
| `local_confirmed_count` / amount | Payments confirmed that day (confirmed or reversed), not soft-deleted |
| `zoho_synced_count` / amount | Same-day rows with `zoho_sync_status` in `synced` or `dry_run` |
| `sync_failed_count` | `zoho_sync_status=failed` |
| `reversed_count` | `status=reversed` |
| `variance_amount` | local confirmed amount − synced amount |

Stored in `payment_reconciliation_records` (`status=completed`, `details` includes failed/reversed payment ids).

## Reports (API)

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/reports/payments-summary` | `payments.view` (or export) — group by `status` + `zoho_sync_status`; optional `branch_id`, `from`, `to` |
| GET | `/api/v1/reports/payments-sync-failures` | `payments.view` \| `payments.retry_sync` — up to 200 failed sync rows |

There is no Stage 4 public “run reconciliation now” HTTP endpoint; rely on the scheduler or call the service from Artisan/ops as needed.

## Dry-run note

Dry-run Zoho rows count as synced for variance (same bucket as live `synced`). Variance against live Books still requires `ZOHO_PAYMENT_DRY_RUN=false` and successful push.
