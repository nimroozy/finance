# Stage 10.4 — Stock reconciliation

## Acceptance DB (after inventory workflow receive × 6 projects)

| Field | Value |
|-------|-------|
| Opening | 0.000 |
| Closing (sum on_hand) | 30.000 |
| Computed closing | 30.000 |
| Result | **MATCH** |
| Receipts | 30 |
| Transaction count | 6 |

Formula: `opening + receipts + inbound_transfers + adj_in - fulfilled - outbound_transfers - write_offs - adj_out = closing`

## Production (pre/post final tip deploy)

| Metric | Pre | Post |
|--------|-----|------|
| inventory_stock_transactions | 14 | 14 |
| on_hand | 18.000 | 18.000 |

Production ledger unchanged by Stage 10.4 acceptance (isolated DB).
