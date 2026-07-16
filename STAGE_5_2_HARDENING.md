# Stage 5.2 Hardening

## Delivered

1. **Effective invoice balance**
   - API invoices + payment preview expose `zoho_synced_balance`, `local_pending_allocation`, `effective_balance`, `balance_last_synced_at`, `awaiting_zoho_refresh`.
   - Collector payment UI and invoices list use payable `effective_balance`.
   - Successful Zoho customer payment queues `RefreshZohoInvoicesJob` for affected invoice IDs.
   - Refresh failure sets `balance_sync_status=refresh_failed` while keeping effective balance correct.

2. **Location / account preflight**
   - Before Zoho HTTP, invoice location and currency must match branch payment mapping.
   - Bilingual `LocalizedInvalidArgumentException` (`message` + `message_fa`).

3. **Custody-aware reversal**
   - Handed-over payments create `custody_conflicts`.
   - `/api/v1/custody-reversals*` + UI `/custody-reversals`.
   - Approval: compensating cashbox debit, reverse allocations, reverse payment/receipt, preserve handover, void Zoho (idempotent), queue invoice refresh, reconciliation + audit.
   - Insufficient cashbox → `manual_review` (no Zoho void).
   - Local OK / Zoho fail → `zoho_void_status=pending` + retry job.
   - Zoho already voided / local recovery → `critical_recovery` path.

## Verification

- Backend PHPUnit (SQLite): **158 passed**
- Frontend lint + build: pass
- Playwright: **40 passed** (2 skipped health)
- OpenAPI: valid (warnings only)
