# Stock Transfer Workflow

## Purpose

Move quantity or serialized units between inventory locations (warehouse ↔ office ↔ tower stock location).

## Flow

1. **Create** draft transfer (from/to location + items)
2. **Submit** → **Approve** (permission `inventory.transfers.approve`)
3. **Dispatch** — stock leaves source / enters in-transit
4. **Receive** — destination on-hand increases (`received_qtys` optional for partial)
5. **Close** — finalize; discrepancy flag if qty mismatch

Statuses: `draft` → `submitted` → `approved` → `dispatched` / `in_transit` → `received` → `closed`

## Mobile UX

Receive action uses large primary buttons for warehouse scanners / tablets.
