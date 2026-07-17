# Stock Reservation Workflow

## Purpose

Hold available stock for installation, sale, or CRM/lead handoff without issuing yet.

## Flow

1. **Create** draft reservation with location + line items (`product_id`, `qty`, optional `equipment_id`)
2. **Reserve** — posts reservation ledger; increases `reserved`
3. **Fulfill** — issues stock (installation/sale path)
4. **Release** — returns reserved qty to available

Statuses: `draft` → `approved` / `partially_fulfilled` → `fulfilled` | `released` | `expired` | `cancelled`

## Integrations

Optional links: `installation_id`, `lead_id`, `customer_id`, `task_id`.
Installation helpers under `/inventory/installations/*` reserve/fulfill/install without mutating CRM inline.
