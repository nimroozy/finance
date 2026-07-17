# Stage 9 — Inventory, Assets, Sites & Towers

## Status

**Delivered on branch** `cursor/stage-9-inventory-assets` (backend ledger + frontend ops workspace).

Do **not** start Stage 10 (Radius) from this workstream. Do **not** change payment calculation paths.

## Objective

Operational inventory for ISP warehouses and field stock: immutable stock ledger, serialized equipment, reservations, transfers, receiving, counts, sites/towers, fixed assets, custody/repairs/maintenance, and lightweight purchasing/receiving.

## Hard rules

1. **Immutable inventory transactions only** — never `UPDATE ... SET quantity`.
2. Stock UIs post ledger movements (receipt, transfer, reserve, adjust via count, etc.).
3. Inventory must not call Zoho/Radius/WhatsApp inside the stock transaction; emit events / queue jobs.
4. Zoho remains accounting SoT for valuation/bills; local ledger is operational movement.

## Backend (API)

Base path: `/api/v1/inventory/*`

| Area | Endpoints |
|------|-----------|
| Products / categories | `GET/POST/PUT /inventory/products`, categories |
| Locations | `GET/POST/PUT /inventory/locations` |
| Sites / towers | `GET/POST/PUT /inventory/sites`, towers |
| Suppliers | `GET/POST/PUT /inventory/suppliers` |
| Stock | `GET /inventory/stock/balances`, `transactions`, `POST .../adjust` |
| Reservations | `GET/POST /inventory/reservations`, `.../reserve|fulfill|release` |
| Transfers | `GET/POST /inventory/transfers`, `.../submit|approve|dispatch|receive|close` |
| Equipment | `GET /inventory/equipment`, `POST .../receive`, `.../move` |
| Purchasing / GRN | purchase-requests, purchase-orders, `POST /inventory/goods-receipts` |
| Custody / repairs / maintenance | custody, repairs, maintenance-plans |
| Counts | stock-counts + record/post |
| Ops | dashboard, search, reports CSV, import dry-run/apply, fixed-assets, customer-equipment |

Permissions: fine-grained `inventory.*` seeded in RolePermission seeder.

## Frontend

Feature flags: `inventory`, `assets`, `sites` enabled in `feature-flags.ts`.

API client: `frontend/src/lib/inventory.ts`.

Nav workspaces (flag + permission gated):

- **Inventory** — dashboard, products, stock, equipment, reservations, transfers, receiving, counts
- **Assets & Sites** — sites, fixed assets, customer equipment, custody, repairs, maintenance
- **Purchasing** — requests, orders, suppliers
- **Reports** — inventory reports

Mobile warehouse UX: large receive/count buttons, barcode input fallback, camera capture via `AttachmentGallery` (`capture="environment"`).

i18n: `inventory.*` + nav keys in `en.json` / `fa.json`.

E2E: `frontend/e2e/stage9-inventory.spec.ts`.

## Domain docs

- [INVENTORY_LEDGER_MODEL.md](INVENTORY_LEDGER_MODEL.md)
- [SERIALIZED_EQUIPMENT_MODEL.md](SERIALIZED_EQUIPMENT_MODEL.md)
- [STOCK_RESERVATION_WORKFLOW.md](STOCK_RESERVATION_WORKFLOW.md)
- [STOCK_TRANSFER_WORKFLOW.md](STOCK_TRANSFER_WORKFLOW.md)
- [TOWER_SITE_ASSET_MODEL.md](TOWER_SITE_ASSET_MODEL.md)
- [CUSTOMER_SERVICE_EQUIPMENT.md](CUSTOMER_SERVICE_EQUIPMENT.md)
- [PURCHASING_RECEIVING_WORKFLOW.md](PURCHASING_RECEIVING_WORKFLOW.md)
- [INVENTORY_IMPORT_GUIDE.md](INVENTORY_IMPORT_GUIDE.md)
- Overview model: [INVENTORY_MODEL.md](INVENTORY_MODEL.md)

## Boundaries

See [DOMAIN_BOUNDARIES.md](DOMAIN_BOUNDARIES.md).

**Must not:** mutate payment calculation paths; implement Stage 10 Radius; call Zoho/Radius inline inside inventory TX.

## Verification

```bash
cd frontend && npm run lint && npm run build && npx playwright test e2e/stage9-inventory.spec.ts
cd ../backend && php artisan test --filter=Stage9
```
