# Zoho payment sync

Pushes confirmed local payments to Zoho Books `customerpayments` (endpoint configurable).

## Config

| Key | Env | Default |
|-----|-----|---------|
| `zoho.payments.dry_run` | `ZOHO_PAYMENT_DRY_RUN` | `false` |
| `zoho.payments.endpoint` | `ZOHO_PAYMENT_ENDPOINT` | `customerpayments` |

Runtime mirror: system setting / `/payment-settings` key `zoho_payment_dry_run` (read by settings API; sync service uses `config('zoho.payments.dry_run')` — set env or force via smoke `--dry-run-zoho`).

## Dry-run (recommended until go-live)

When dry-run is on, `ZohoPaymentSyncService`:

1. Does **not** call Zoho HTTP.
2. Writes a `PaymentSyncAttempt` with `is_dry_run=true`.
3. Sets `zoho_payment_id` / `zoho_reference` to `DRYRUN-…`.
4. Sets `zoho_sync_status=dry_run`.
5. For non-cash statuses, transitions payment `status` toward `synced` with reason `zoho_dry_run`. Cash `settled_pending_handover` keeps that status; only Zoho fields update.

Stage 4 smoke: pass `--dry-run-zoho` so test confirms never post live payments.

## Live sync

`POST` to Books via `ZohoApiClient` with invoice allocations + payment mode mapping (`ZohoPaymentModeMapping`). Success → `zoho_sync_status=synced`. Failure → `failed` + `last_sync_error`; payment may move to `sync_failed`.

## Triggers

- After confirm: `SyncPaymentToZohoJob`.
- Manual: `POST /api/v1/payments/{uuid}/retry-sync` (`payments.retry_sync`).
- Sync detail: `GET /api/v1/payments/{uuid}/sync-status`.
- Failures list: `GET /api/v1/reports/payments-sync-failures`.

## Zoho sync status values

`pending` · `syncing` · `synced` · `failed` · `dry_run` · `skipped` — see [STAGE4_PAYMENTS.md](STAGE4_PAYMENTS.md).
