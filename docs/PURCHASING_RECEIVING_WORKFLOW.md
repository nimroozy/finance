# Purchasing & Receiving Workflow

## Scope (Stage 9)

Operational PO tracking and goods receipt into the local ledger. Accounting bills remain Zoho-backed (later/Stage 11 depth).

## Flow

1. **Purchase request** → approve
2. **Purchase order** (supplier + lines) → approve
3. **Goods receipt** `POST /inventory/goods-receipts` posts receipt ledger (qty and optional serial lines)

## Receiving UI

Warehouse receiving page supports:

- Quantity GRN
- Serial equipment receive
- Barcode text input fallback
