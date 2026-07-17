# Inventory Import Guide

## Endpoints

- `POST /inventory/import/dry-run` — validate rows, no writes
- `POST /inventory/import/apply` — apply valid rows (permission `inventory.import`)

## Payload

```json
{
  "type": "products",
  "rows": [{ "name_en": "...", "tracking_mode": "quantity", "sku": "..." }]
}
```

Supported types are defined by `InventoryImportService` (products, opening balances, equipment serials as implemented).

## Practice

1. Always dry-run first
2. Fix reported errors
3. Apply with a dedicated import role
4. Prefer idempotent keys for serials to avoid duplicates
