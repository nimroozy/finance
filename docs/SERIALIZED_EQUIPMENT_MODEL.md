# Serialized Equipment Model

## Product tracking modes

- `serialized` — one equipment row per serial / MAC
- `quantity` — bulk countable SKU
- `batch` — batch-tracked (foundation)
- `non_stock_service` — catalog service item, no stock

## Equipment record

Each unit has: `equipment_number`, `serial_number`, optional `mac_address` / `barcode`, `status`, `location_id`, ownership, warranty, links to customer / installation / site / tower.

## Status lifecycle (typical)

`in_stock` → `reserved` → `in_transit` → `installed` / `custody` / `sold` / `repair` → `scrapped` / `lost`

## Receiving

`POST /inventory/equipment/receive` creates equipment + posts receipt ledger movement.

## Moves

`POST /inventory/equipment/{id}/move` relocates unit and posts transfer-style ledger entries.
