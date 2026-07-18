# Stage 10.4 — Stock ledger reconciliation

**Helper:** `App\Support\Acceptance\AcceptanceDbAssertions::stockReconciliation()`  
**CLI:** `php artisan acceptance:assert --stock`  
**Acceptance DB result (reset fixtures):**

| Field | Value |
|-------|-------|
| opening | 0.000 |
| closing | 0.000 |
| computed_closing | 0.000 |
| ok | **true** |
| transaction_count | 0 |

## Production inventory (deploy gate)

| Metric | Pre | Post |
|--------|-----|------|
| inventory_stock_transactions | 14 | _(fill after deploy)_ |
| inventory_on_hand_sum | 18.000 | _(fill after deploy)_ |

Immutable ledger rules unchanged in Stage 10.4.
