# Payment workflow

End-to-end flow implemented in `PaymentService` + `PaymentController`.

## Flow

1. **Preview** — `POST /api/v1/payments/preview`  
   Validates customer, method, amount, allocations; returns effective available balances and warnings. Does not persist.

2. **Draft** — `POST /api/v1/payments/draft`  
   Requires `idempotency_key`. Creates `status=draft`, persists allocations, sets `draft_expires_at`. Scoped by branch + collector ownership.

3. **Confirm** — `POST /api/v1/payments/{uuid}/confirm`  
   Only `draft` → confirmed. Re-validates allocations against invoice available balance. Assigns `payment_reference`. Optional confirm idempotency key.

4. **Side effects on confirm**
   - Cash (`affects_cash_wallet`): status → `settled_pending_handover`; wallet credit if `PAYMENT_WALLET_ENABLED`.
   - Non-cash: status → `pending_zoho_sync`.
   - Receipt issued when method `receipt_enabled`.
   - Linked promise may be updated if present.
   - `SyncPaymentToZohoJob` dispatched.

5. **Zoho sync** — async (or `POST …/retry-sync`). See [ZOHO_PAYMENT_SYNC.md](ZOHO_PAYMENT_SYNC.md). Cash rows may stay `settled_pending_handover` while `zoho_sync_status` updates.

6. **Reversal** (optional) — request → approve/reject. See [PAYMENT_REVERSALS.md](PAYMENT_REVERSALS.md).

## Confirm rules

- Customer mapped + active.
- Collector owns an **active** assignment for that customer.
- Amount > 0 and ≤ `PAYMENT_MAX_AMOUNT`.
- Method active; collectors only for `collector_allowed` methods.
- Methods with `requires_reference` need `external_reference`.
- Collectors may only confirm their own drafts.

## Idempotency

| Action | Key |
|--------|-----|
| Draft | Request `idempotency_key` (required), scoped to user, TTL `PAYMENT_IDEMPOTENCY_TTL_HOURS` (48h) |
| Confirm | Optional `idempotency_key` → stored as `confirm:{uuid}:{key}` |

Same key + same payload → original result. Same key + different payload → error.

## Status transitions

Enforced by `PaymentStatusTransitionService::ALLOWED`. Terminal: `reversed`, `expired`. Cash confirm lands on `settled_pending_handover` (handover completion is Stage 5 — not implemented).

## Allocations

`allocations[]` with `invoice_id` + `amount` required. Sum must match payment amount; each line ≤ invoice effective available (Zoho balance minus other confirmed allocations). On reverse, allocations are unwound.

## List / detail filters

`GET /payments` — optional `status`, `customer_id`, `collector_id`, `branch_id`, `zoho_sync_status`.  
`GET /payments/{uuid}` — method, customer, allocations, receipt, status history, sync attempts, latest reversal.  
`GET /payments/{uuid}/sync-status` — sync fields + attempts.
