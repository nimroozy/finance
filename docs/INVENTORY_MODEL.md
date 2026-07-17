# Inventory, Assets, Sites & Towers Model (Stage 9)

## Hard rules

1. **Immutable inventory transactions** — every stock change is an append-only transaction.
2. **No direct quantity editing** — UIs and APIs must never `UPDATE ... SET quantity`.
3. Support **serialized** and **quantity-based** tracking.
4. Inventory must not call Zoho/Radius/WhatsApp inside the stock transaction; emit events.

## Location types

Stock and assets may reside at:

- Warehouses
- Offices
- Towers
- Sites (network)
- Customer locations (installed CPE)

## Product model

- **Product** — catalog item (may map to Zoho item ID)
- **Serialized item** — unique serial / MAC / IMEI
- **Quantity item** — bulk countable SKU

## Stock transaction types

| Type | Meaning |
|------|---------|
| `receipt` | Inbound from purchase/transfer in |
| `transfer` | Location A → B |
| `reservation` | Hold for sale/install |
| `sale` | Issued to customer sale |
| `installation` | Issued to install job |
| `return` | Returned to stock |
| `repair` | Move to repair state/location |
| `damage` | Write-down damaged |
| `loss` | Write-off lost |
| `adjustment` | Controlled correction (reason + approval) |
| `scrap` | Destroyed / end of life |

Each transaction stores: type, product, serial (if any), qty, from/to location, actor, branch, reason, related documents (PO, ticket, installation), timestamps.

**On-hand quantity** = projection/sum of transactions (materialized view or cached balance table rebuilt from ledger).

## Fixed assets

Separate from sellable stock when capitalized:

- Tower equipment, office equipment, vehicles
- Servers, power systems, generators, batteries, solar
- Custodian, warranty, condition, maintenance history
- Photos and documents

Assets may reference serial inventory items when dual-tracked.

## Sites & towers

- **Tower** — infrastructure site with geo, power, access notes
- **Site** — broader network/customer site registry
- Link assets and stock locations to towers/sites

## Events

- `StockTransactionPosted`
- `StockReservationReleased`
- `AssetAssigned`
- `MaintenanceLogged`

## Zoho boundary

Official purchase bills and inventory valuation for accounting remain Zoho-backed. Local ledger is operational movement; post accounting impact via Zoho Integration with idempotency (see [ZOHO_ACCOUNTING_BOUNDARY.md](ZOHO_ACCOUNTING_BOUNDARY.md)).
