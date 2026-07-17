# Functional Route Audit — Stage 10.2 Production Recovery

**Branch:** `cursor/stage-10-2-production-recovery`  
**Generated:** 2026-07-17  
**Scope:** Frontend `(app)` routes, API routes, app catalog, permissions, feature flags.  
**Non-goals:** Stage 11, financial calculation changes, live Radius.

## Architecture notes (CRM / Zoho / Radius)

1. **CRM conversion no longer creates local-only customers** or dispatches placeholder Zoho/Radius events.
2. Conversion **requires** an existing local customer with `zoho_contact_id` (Zoho mirror).
3. Staff must **search** `GET /api/v1/crm/customers/search-zoho-mirror` and **link** via `POST /api/v1/crm/leads/{id}/link-zoho-customer` before convert (or pass `customer_id`).
4. **Radius** remains Stage 12: `FEATURE_FLAGS.radius.enabled = false`, no Radius app card, placeholder events deleted.
5. Zoho Books remains SoT for customer contacts; local CRM links mirrors only — see `docs/ZOHO_ACCOUNTING_BOUNDARY.md` and `docs/RADIUS_INTEGRATION_MODEL.md`.

## Decision legend

| Decision | Meaning |
|----------|---------|
| **keep** | Production-ready enough to remain exposed |
| **repair** | Exposed but known gaps; fix before trusting ops |
| **hide** | Incomplete / deferred — remove from launcher or gate behind false flag |

## Known broken focus

| Area | Decision | Notes |
|------|----------|-------|
| Purchasing | **hide** | Workflow incomplete; `purchasing` flag false; catalog gated |
| Service queues | **repair** | Lifecycle APIs real; some queue UX historically stubby — verify |
| Radius placeholders | **hide** | Events + CRM dispatches removed; banners removed from staff dashboards |
| Zoho customer create placeholder | **hide** | `PlaceholderZohoCustomerRequested` deleted; link-only flow |

## Feature flags

| Module | Enabled | Stage | Notes |
|--------|---------|-------|-------|
| whatsapp | true | 6 | keep |
| ticketing | true | 7 | keep |
| tasks | true | 7 | keep |
| crm | true | 8 | keep — conversion now requires Zoho link |
| installations | true | 7 | keep |
| inventory | true | 9 | keep |
| assets | true | 9 | keep |
| sites | true | 9 | keep |
| services | true | 10 | keep/repair queues |
| radius | **false** | 12 | hide — no app card; placeholders removed |
| purchasing | **false** | 11 | hide — incomplete; catalog now gated |
| unifiedDashboards | false | 11 | hide |

## App catalog cards

| App id | href | Permissions (any) | Feature flag | Decision |
|--------|------|-------------------|--------------|----------|
| `dashboard` | `/dashboard` | `dashboard.view` | `None` | **keep** |
| `payments` | `/payments` | `payments.view` | `None` | **keep** |
| `collections` | `/assignments` | `assignments.view|visits.view` | `None` | **keep** |
| `tasks` | `/tasks` | `tasks.view` | `tasks` | **keep** |
| `support` | `/tickets` | `tickets.view` | `ticketing` | **keep** |
| `noc` | `/noc/services` | `escalations.view|services.noc.view` | `None` | **keep** |
| `installations` | `/installations` | `installations.view` | `installations` | **keep** |
| `services` | `/services/dashboard` | `services.view|services.dashboard.view` | `services` | **repair** |
| `customers` | `/customers` | `customers.view` | `None` | **keep** |
| `crm` | `/crm/dashboard` | `crm.leads.view|crm.reports.view` | `crm` | **keep** |
| `leads` | `/crm/leads` | `crm.leads.view` | `crm` | **keep** |
| `quotations` | `/crm/quotations` | `crm.quotations.view` | `crm` | **keep** |
| `followups` | `/crm/follow-ups` | `crm.follow_ups.manage` | `crm` | **keep** |
| `inventory` | `/inventory/dashboard` | `inventory.*` | `inventory` | **keep** |
| `equipment` | `/inventory/equipment` | `inventory.equipment.view` | `inventory` | **keep** |
| `warehouses` | `/inventory/stock` | `inventory.stock.view` | `inventory` | **keep** |
| `transfers` | `/inventory/transfers` | `inventory.transfers.view` | `inventory` | **keep** |
| `purchasing` | `/purchasing/requests` | `inventory.purchasing.view` | `purchasing=false` | **hide** |
| `sites` | `/sites` | `inventory.sites.view` | `sites` | **keep** |
| `repairs` | `/repairs` | `inventory.repairs.manage` | `assets` | **keep** |
| `whatsapp` | `/whatsapp/inbox` | `whatsapp.view` | `whatsapp` | **keep** |
| `notifications` | `/notifications` | `notifications.view` | `None` | **keep** |
| `reports` | `/reports/collection` | `reports.*` | `None` | **keep** |
| `branches` | `/branches` | `branches.view` | `None` | **keep** |
| `users` | `/users` | `users.view` | `None` | **keep** |
| `audit` | `/audit-logs` | `audit.view` | `None` | **keep** |
| `settings` | `/settings` | `settings.manage` | `None` | **keep** |

## Major route matrix

| Frontend route | Permission | Primary endpoint(s) | Decision | Notes |
|----------------|------------|---------------------|----------|-------|
| `/dashboard` | `dashboard.view` | `GET /api/v1/dashboard*` | **keep** | Core ops home |
| `/payments` | `payments.view` | `GET/POST /api/v1/payments*` | **keep** | Collections payments |
| `/assignments` | `assignments.view` | `GET/POST /api/v1/assignments*` | **keep** | Field assignments |
| `/tickets` | `tickets.view` | `GET/POST /api/v1/tickets*` | **keep** | Support ticketing |
| `/tasks` | `tasks.view` | `GET/POST /api/v1/tasks*` | **keep** | Task queue |
| `/installations` | `installations.view` | `GET/POST /api/v1/installations*` | **keep** | Install queue |
| `/crm/leads` | `crm.leads.view` | `GET/POST /api/v1/crm/leads*` | **keep** | CRM leads; convert requires Zoho link |
| `/crm/dashboard` | `crm.reports.view` | `GET /api/v1/crm/dashboard` | **keep** | CRM metrics |
| `/services/dashboard` | `services.dashboard.view` | `GET /api/v1/services/dashboard` | **repair** | Lifecycle OK; queue UX needs production verification |
| `/services/pending-activation` | `services.view` | `GET /api/v1/services*` | **repair** | Service queue surfaces historically stubby |
| `/noc/services` | `services.noc.view` | `GET /api/v1/services/noc*` | **keep** | Radius banner removed; operational only |
| `/purchasing/requests` | `inventory.purchasing.view` | `GET /api/v1/inventory/purchase-requests*` | **hide** | Purchasing incomplete; feature flag false |
| `/purchasing/orders` | `inventory.purchasing.view` | `GET /api/v1/inventory/purchase-orders*` | **hide** | Incomplete purchasing workflow |
| `/inventory/dashboard` | `inventory.dashboard.view` | `GET /api/v1/inventory/*` | **keep** | Stock ledger |
| `/customers` | `customers.view` | `GET /api/v1/customers*` | **keep** | Zoho-synced customers |
| `/zoho` | `zoho.view` | `GET /api/v1/zoho*` | **keep** | Zoho admin; no CRM placeholder create |

## Frontend routes under `frontend/src/app/[locale]/(app)` (164 pages)

- `/403`
- `/alerts`
- `/apps`
- `/assets`
- `/assets/[id]`
- `/assignments`
- `/assignments/bulk`
- `/assignments/[id]`
- `/assignments/new`
- `/assignments/unassigned`
- `/assignments/workload`
- `/audit-logs`
- `/bank-deposits`
- `/bank-deposits/new`
- `/branches`
- `/cashboxes`
- `/change-password`
- `/collector`
- `/collector/assignments`
- `/collector/assignments/[id]`
- `/collector/debtors`
- `/collector/handovers/new`
- `/collector/notifications`
- `/collector/payments`
- `/collector/payments/new`
- `/collector/payments/[uuid]`
- `/collector/payments/[uuid]/receipt`
- `/collector/permanent-customers`
- `/collector/promises/new`
- `/collector/routes`
- `/collector/routes/[id]`
- `/collectors`
- `/collector/visits`
- `/collector/visits/new`
- `/collector/wallet`
- `/crm/dashboard`
- `/crm/follow-ups`
- `/crm/leads`
- `/crm/leads/[id]`
- `/crm/leads/new`
- `/crm/pipeline`
- `/crm/quotations`
- `/crm/reports`
- `/crm/surveys`
- `/crm/targets`
- `/custody`
- `/custody-reversals`
- `/customer-equipment`
- `/customer-ownership`
- `/customer-ownership/bulk`
- `/customer-ownership/by-collector`
- `/customer-ownership/transfers`
- `/customer-ownership/unassigned`
- `/customers`
- `/customers/[id]`
- `/dashboard`
- `/debtors`
- `/escalations`
- `/finance/tasks`
- `/handovers`
- `/installations`
- `/installations/[id]`
- `/inventory/counts`
- `/inventory/counts/[id]`
- `/inventory/dashboard`
- `/inventory/equipment`
- `/inventory/equipment/[id]`
- `/inventory/products`
- `/inventory/products/[id]`
- `/inventory/products/new`
- `/inventory/receiving`
- `/inventory/reservations`
- `/inventory/reservations/[id]`
- `/inventory/stock`
- `/inventory/transfers`
- `/inventory/transfers/[id]`
- `/invoices`
- `/maintenance`
- `/management/service-operations`
- `/noc/dashboard`
- `/noc/services`
- `/notifications`
- `/ownership-conflicts`
- `/payments`
- `/payments/reversals`
- `/payments/sync-failures`
- `/payments/[uuid]`
- `/promises`
- `/purchasing/orders`
- `/purchasing/requests`
- `/receipts`
- `/reconciliation`
- `/repairs`
- `/reports/branch-receivables`
- `/reports/collection`
- `/reports/inventory`
- `/reports/payments`
- `/roles`
- `/routes`
- `/routes/[id]`
- `/routes/new`
- `/services`
- `/services/change-requests`
- `/services/contracts`
- `/services/dashboard`
- `/services/finance-hold`
- `/services/[id]`
- `/services/[id]/cancel`
- `/services/[id]/change`
- `/services/[id]/relocate`
- `/services/[id]/suspend`
- `/services/[id]/timeline`
- `/services/new`
- `/services/packages`
- `/services/packages/[id]`
- `/services/pending-activation`
- `/services/pending-installation`
- `/services/relocations`
- `/services/reports`
- `/services/sla`
- `/services/suspended`
- `/settings`
- `/settings/branch-payment-mappings`
- `/settings/customer-prefix-mappings`
- `/settings/escalation-rules`
- `/settings/mapping-cleanup`
- `/settings/sla-policies`
- `/settings/system-version`
- `/settings/task-templates`
- `/settings/ticket-types`
- `/settings/whatsapp`
- `/settings/whatsapp-rules`
- `/sites`
- `/sites/[id]`
- `/suppliers`
- `/support/dashboard`
- `/tasks`
- `/tasks/[id]`
- `/tasks/my`
- `/technical/dashboard`
- `/temporary-assignments`
- `/tickets`
- `/tickets/[id]`
- `/tickets/new`
- `/towers/[id]`
- `/transfers`
- `/transfers/[id]`
- `/transfers/new`
- `/users`
- `/visits`
- `/visits/[id]`
- `/wallets`
- `/whatsapp/failures`
- `/whatsapp/inbox`
- `/whatsapp/messages`
- `/whatsapp/templates`
- `/zoho`
- `/zoho/api-logs`
- `/zoho/branch-mappings`
- `/zoho/location-mapping`
- `/zoho/mapping-conflicts`
- `/zoho/sync-health`
- `/zoho/sync-jobs`
- `/zoho/unmapped`

## Backend API

- Source: `backend/routes/api.php` (+ `php artisan route:list --path=api/v1` → **439** routes at audit time).
- New Stage 10.2 CRM endpoints:
  - `POST /api/v1/crm/leads/{id}/link-zoho-customer` (`crm.leads.update`)
  - `GET /api/v1/crm/customers/search-zoho-mirror` (`crm.leads.view|customers.view`)

See also: `docs/ACTION_ENDPOINT_MATRIX.md`, `docs/PERMISSION_MATRIX.md`.

## Permissions source

`backend/database/seeders/RolePermissionSeeder.php` → `PERMISSIONS` constant (Stages 1–10). Super Admin gets all; Branch Manager gets broad CRM/inventory/services; Collector remains collections-focused.

## Demo cleanup

```bash
php artisan stage102:cleanup-demo --dry-run
php artisan stage102:cleanup-demo --apply
```

Never deletes `payments` or `stock_transactions` / `inventory_stock_transactions`.
