# AUTO_SYNC_REPAIR.md

**Stage:** 5.1 P0  
**Branch:** `cursor/stage-5-1-p0-repair`

## Problem

Incremental Zoho sync sent `last_modified_time` with PHP format `P` (`+00:00`). Zoho Books rejected it (HTTP 400, code 2). Scheduler and queue were healthy; API failed; `RetryFailedZohoSyncJob` amplified into ~1800+ `failed_jobs`.

## Fix

Central formatter: `App\Services\Zoho\ZohoDateTime::formatQueryTimestamp()`

- Always UTC
- Format: `Y-m-d\TH:i:sO` → e.g. `2026-07-15T19:28:16+0000`
- **Never** send colon offsets (`+00:00`)

Used by customer and invoice incremental sync. Do not add ad hoc Zoho query date formatting elsewhere.

## Cursor safety

Table `zoho_sync_cursors` stores durable successful cursors.

- Cursor advances only after a fully successful pagination run
- Configurable overlap: `zoho.sync.cursor_overlap_minutes` (default **2**)
- Job stats include requested_from/to, page_count, fetched/created/updated/skipped/failed

## Error classification & circuit breaker

`ZohoErrorClassifier` classes include `permanent_validation`, `invalid_request`, retryable network/timeout/rate_limit/server, authentication, etc.

- Permanent HTTP 400 validation is **not** rethrown into endless `failed_jobs`
- `ZohoCircuitBreaker` opens after repeated permanent failures; resume via API or cooldown
- Sync jobs use `$tries = 1` for permanent paths

## Cleanup

```bash
php artisan zoho:failed-jobs-cleanup          # dry-run
php artisan zoho:failed-jobs-cleanup --apply  # remove redundant timestamp failures
```

## Schedules (runtime via `zoho:scheduler-tick` every minute)

| Job | Default interval |
|-----|------------------|
| Invoices incremental | 5 minutes |
| Customers incremental | 15 minutes |
| Token refresh | 10 minutes |
| Failed retry | 15 minutes |
| Organization structure | 6 hours |
| Full customer/invoice | Nightly 02:00 UTC |

Overrides: `system_settings` keys `zoho.schedule.*` via `ZohoConfig::scheduleMinutes()`.

## Health UI

`/en/zoho/sync-health` — heartbeats, cursors, circuit breakers, actions.
