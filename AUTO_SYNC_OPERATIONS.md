# AUTO_SYNC_OPERATIONS.md

**Stage:** 5.1 P0 — operator runbook

## Quick health check

```bash
cd /opt/collection-system
docker compose exec backend php artisan zoho:scheduler-tick
docker compose exec backend php artisan tinker --execute="echo App\\Models\\ZohoSyncHeartbeat::pluck('status','component');"
docker compose logs --tail=100 scheduler queue-worker
```

UI: `/en/zoho/sync-health`

## Heartbeat components

| Component | Meaning |
|-----------|---------|
| scheduler | Minute tick ran |
| queue_worker | Worker processed a Zoho job recently |
| customer_sync / invoice_sync | Entity sync start/complete/fail |
| structure_sync | Org structure sync |
| token_refresh | Token job |
| retry | Retry processor |

Statuses: `healthy`/`success`, `delayed`, `failed`, `paused`, `disabled`, `never_run`, `queued`, `running`

Healthy ≠ “container Up”. Require recent success heartbeats and successful sync jobs.

## Manual sync

```bash
docker compose exec backend php artisan tinker --execute="App\\Jobs\\SyncZohoCustomersJob::dispatch(null, true);"
docker compose exec backend php artisan tinker --execute="App\\Jobs\\SyncZohoInvoicesJob::dispatch(null, true);"
```

Or UI Sync Health / Zoho hub.

## Circuit breaker

If open: fix root cause → Test connection → Resume circuit (`POST /api/v1/zoho/circuit-breakers/{type}/resume`) → re-run sync.

## Failed jobs

See `FAILED_JOB_CLEANUP.md`.

## Mapping ops

1. Sync structure  
2. Preview auto-match  
3. Apply exact/probable confirmed matches (Nimruz first)  
4. Dry-run reprocess → apply small batch → full apply  

See `ZOHO_LOCATION_MAPPING.md`.
