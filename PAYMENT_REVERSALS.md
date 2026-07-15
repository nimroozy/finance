# Payment reversals

Confirmed payments are **never deleted**. Reversals mark `status=reversed`, unwind allocations, void receipts, and debit cash wallets when applicable (`PaymentReversalService`).

## Reversal statuses

| Value | Meaning |
|-------|---------|
| `pending` | Awaiting manager/finance review |
| `approved` | Applied |
| `rejected` | Closed without reversing the payment |

## API

| Method | Path | Permission |
|--------|------|------------|
| POST | `/api/v1/payments/{uuid}/reversal-request` | `reversals.request` — body `{ "reason": "…" }` (min 3 chars) |
| POST | `/api/v1/reversals/{id}/approve` | `reversals.approve` |
| POST | `/api/v1/reversals/{id}/reject` | `reversals.approve` — body `{ "reason": "…" }` |

## Rules

- Only payments in confirmed statuses can be requested.
- Already reversed → rejected.
- One pending reversal per payment.
- Approve (transaction): reverse allocations → transition to `reversed` → set `reversed_at` → wallet debit if cash → void receipt → mark reversal approved.
- Reject stores `rejection_reason`; payment stays confirmed.

## Flags on `payment_reversals`

`wallet_reversed`, `zoho_void_attempted`, `zoho_void_error` — wallet debit is implemented; full Zoho payment void is not a completed Stage 4 operator workflow (local reverse is authoritative).

## Permissions

Collectors / managers: `reversals.request`. Approve: Super Admin, Central Finance, Branch Manager (per seeder). No hard-delete permission or route.
