# Stage 4 — Payments

Payments, receipts, collector cash wallets, Zoho payment push, reversals, and daily reconciliation.

## Scope

| In Stage 4 | Not in Stage 4 |
|------------|----------------|
| Draft → confirm payments with invoice allocations | Cash handovers (Stage 5) |
| Receipts (issue, PDF, print log, public verify) | Handover settle / deposit APIs |
| Collector wallets for cash methods | WhatsApp / offline payment queue |
| Zoho customerpayment sync (+ dry-run) | Live Zoho void of reversed payments (fields exist; no full Stage 5 flow) |
| Reversal request / approve / reject | `DELETE` payment endpoints |
| Payments summary + sync-failure reports | |

## Payment statuses (`Payment` model)

| Constant | Value | Meaning |
|----------|-------|---------|
| `STATUS_DRAFT` | `draft` | Unconfirmed; expires after `PAYMENT_DRAFT_EXPIRY_HOURS` (default 24) |
| `STATUS_CONFIRMED_LOCAL` | `confirmed_local` | Confirmed locally (transition target / intermediate) |
| `STATUS_PENDING_ZOHO_SYNC` | `pending_zoho_sync` | Non-cash confirm; awaiting Zoho push |
| `STATUS_SETTLED_PENDING_HANDOVER` | `settled_pending_handover` | Cash confirm (`affects_cash_wallet`); wallet credited. **Stage 5 handovers not implemented** — status is held here after cash confirm |
| `STATUS_SYNCED` | `synced` | Zoho push succeeded (or dry-run treated as synced for non-cash status path) |
| `STATUS_SYNC_FAILED` | `sync_failed` | Zoho push failed; retry via `retry-sync` |
| `STATUS_REVERSED` | `reversed` | Reversal approved |
| `STATUS_EXPIRED` | `expired` | Draft confirmed after `draft_expires_at` |

**Confirmed statuses** (count toward invoice available balance): `confirmed_local`, `pending_zoho_sync`, `settled_pending_handover`, `synced`, `sync_failed`.

## Zoho sync statuses

| Constant | Value |
|----------|-------|
| `ZOHO_PENDING` | `pending` |
| `ZOHO_SYNCING` | `syncing` |
| `ZOHO_SYNCED` | `synced` |
| `ZOHO_FAILED` | `failed` |
| `ZOHO_DRY_RUN` | `dry_run` |
| `ZOHO_SKIPPED` | `skipped` |

## Payment methods (migration seed)

| Code | Cash wallet | Reference required |
|------|-------------|--------------------|
| `cash` | yes | no |
| `bank_transfer` | no | yes |
| `card` | no | yes |
| `mobile_money` | no | yes |
| `cheque` | no | yes (+ evidence flag) |
| `other` | no | no |

## Config

- `config/payments.php` — draft expiry, max amount, currency, idempotency TTL, receipt template, GPS thresholds, wallet + reconciliation flags.
- `config/zoho.php` → `payments.dry_run` / `ZOHO_PAYMENT_DRY_RUN` — see [ZOHO_PAYMENT_SYNC.md](ZOHO_PAYMENT_SYNC.md).

## Permissions (`RolePermissionSeeder`)

`payments.view|create|confirm|manage|export|retry_sync|reconcile` · `receipts.view|print|manage` · `wallets.view|manage` · `reversals.request|approve` · `payment_settings.manage`

Collectors typically: view/create/confirm payments, receipts view/print, wallets view, reversals request. Managers/finance: reverse approve, retry sync, settings.

## Docs

- [PAYMENT_WORKFLOW.md](PAYMENT_WORKFLOW.md)
- [RECEIPTS.md](RECEIPTS.md)
- [COLLECTOR_WALLETS.md](COLLECTOR_WALLETS.md)
- [ZOHO_PAYMENT_SYNC.md](ZOHO_PAYMENT_SYNC.md)
- [PAYMENT_REVERSALS.md](PAYMENT_REVERSALS.md)
- [PAYMENT_RECONCILIATION.md](PAYMENT_RECONCILIATION.md)
- [API.md](API.md) — endpoint tables
