# Inventory Ledger Model

## Principle

Every stock quantity change is an **append-only** `stock_transactions` row. On-hand / reserved / in-transit balances are projections (materialized `stock_balances`) rebuilt from the ledger.

## Transaction types

| Type | Effect |
|------|--------|
| `receipt` | Increase on-hand at location |
| `transfer_out` / `transfer_in` | Move between locations (may use in-transit) |
| `reservation` | Increase reserved (reduce available) |
| `reservation_release` | Release reserved |
| `sale` / `installation` | Issue stock |
| `return` | Return to stock |
| `repair` / `damage` / `loss` / `scrap` | Lifecycle write-downs |
| `adjustment` | Controlled correction from stock count / approve path |

## Balance fields

- `on_hand` — physical quantity at location
- `reserved` — held for reservations
- `in_transit` — dispatched not yet received
- **available** = `on_hand - reserved` (computed)

## Idempotency

Receiving, equipment receive, custody issue, and adjustments accept `idempotency_key` where applicable to prevent double-posting.

## Forbidden

- Direct quantity edits in UI or API
- Soft “edit last transaction” — reverse with a compensating transaction instead
