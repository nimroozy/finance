# Stage 10.4 — Stock ledger reconciliation

**Helper:** `App\Support\Acceptance\AcceptanceDbAssertions::stockReconciliation()`  
**Endpoint:** `GET /api/v1/acceptance/stock-reconciliation` (acceptance-only)  
**CLI:** `php artisan acceptance:assert --stock`

## Formula (acceptance reset opening = 0)

```
opening
+ receipts (+ returns)
+ inbound transfers (transfer_receive)
+ adjustments_in
- fulfilled issues (sale|installation|issue|custody)
- outbound transfers (transfer_dispatch)
- write-offs (loss|scrap|damage)
- adjustments_out
= sum(inventory_stock_balances.on_hand)
```

Immediate location transfers / repair moves are excluded (net-zero across locations).

## Rules unchanged

Immutable `inventory_stock_transactions` (no update/delete). No financial or ledger rule changes in Stage 10.4.

## Latest acceptance DB result

| Field | Value |
|-------|-------|
| opening | 0.000 |
| closing | _(fill)_ |
| computed_closing | _(fill)_ |
| ok | _(fill)_ |

## Production inventory (deploy verify)

Recorded in delivery report pre/post counts (`inventory_on_hand_sum`, `inventory_stock_transactions`). Must **MATCH** across Stage 10.4 deploy.
