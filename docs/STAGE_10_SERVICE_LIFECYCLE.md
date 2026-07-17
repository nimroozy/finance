# Stage 10 — ISP Service Lifecycle

Commercial/operational service lifecycle after installation: packages, locations, activation, suspension, reactivation, cancellation, change requests, relocation, renewals, contracts, finance holds, and Zoho-synced read-only billing views.

**Branch:** `cursor/stage-10-service-lifecycle`

## Hard constraints

- **No SAS Radius / Radius DB connectivity** — Radius is deferred to Stage 12
- **No automated network provisioning**
- Zoho remains SoT for customers/invoices/payments (billing views read-only)
- Radius feature flag remains `false`
- Do **not** mutate payment calculation paths
- Do **not** start Stage 11 dashboards from this workstream

## Clarification

| Topic | Stage |
|-------|-------|
| **Service Lifecycle** (packages, activate/suspend/cancel, contracts, finance holds, billing views) | **Stage 10** |
| **SAS Radius** integration (adapters, sessions, provisioning commands) | **Stage 12** (was historically labeled Stage 10 in older docs) |

## Tables

`service_types`, `service_access_technologies`, `service_sla_templates`, `service_locations`, `service_packages`, `service_package_versions`, `service_sequences`, `service_contracts`, `services`, `service_status_transitions`, `service_activations`, `service_suspensions`, `service_cancellations`, `service_change_requests`, `service_relocations`, `service_renewals`, `service_finance_holds`

Also: nullable `tickets.service_id`, nullable `inventory_customer_equipment.service_id`.

## Backend API (prefix `/api/v1`)

| Method | Path |
|--------|------|
| GET/POST | `/services` |
| GET/PUT | `/services/{id}` |
| POST | `/services/{id}/activate\|suspend\|reactivate\|cancel\|transition` |
| POST | `/services/{id}/change-requests\|relocations\|renewals\|finance-holds` |
| GET | `/services/{id}/billing\|timeline` |
| GET/POST | `/service-packages`, `/service-packages/{id}/versions` |
| GET/POST/PUT | `/service-locations` |
| GET | `/service-types`, `/service-access-technologies`, `/service-sla-templates` |
| GET/POST | `/service-contracts` |
| POST | `/installations/{id}/convert-to-service` |
| GET | `/services/dashboard`, `/services/noc-workspace`, `/services/reports/status` |
| POST | `/services/migration/dry-run\|apply` |

Permissions: fine-grained `services.*` seeded in RolePermission seeder.

## Frontend

Feature flag: `services` enabled in `frontend/src/config/feature-flags.ts` (stage 10). `radius` remains disabled (stage 12 placeholder).

API client: `frontend/src/lib/services.ts`.

Nav workspaces (flag + permission gated):

- **Services** — dashboard, all services, pending installation/activation, suspended, finance hold, change requests, relocations, packages, contracts, SLA, reports
- **NOC** — `/noc/services` attention queue (lifecycle only; no Radius automation)

### Pages

| Route | Purpose |
|-------|---------|
| `/services/dashboard` | Lifecycle metrics (MRR, pending, suspended, expiring) |
| `/services`, `/services/new`, `/services/[id]` | List, create, **command center** detail |
| `/services/[id]/timeline\|change\|relocate\|suspend\|cancel` | Deep links → detail tabs |
| `/services/pending-installation`, `pending-activation`, `suspended`, `finance-hold` | Status queues |
| `/services/change-requests`, `relocations` | Change/relocate workspaces |
| `/services/packages`, `/services/packages/[id]` | Package catalog + versions |
| `/services/contracts`, `/services/sla`, `/services/reports` | Contracts, SLA templates, status report |
| `/noc/services` | NOC operational queue |

Service detail = command center with `StatusActionMenu` for valid actions only (activate / suspend / reactivate / cancel), read-only Zoho billing panel, equipment list, timeline, change/relocate/finance-hold forms.

Customer detail includes an optional **Services** tab when the module flag and `services.view` are present.

i18n: `services.*` + nav keys in `en.json` / `fa.json`.

E2E: `frontend/e2e/stage10-services.spec.ts` (dashboard, create, activate, suspend, package, finance hold, NOC, RTL, permission denial) — mocked.

## Boundaries

See [DOMAIN_BOUNDARIES.md](DOMAIN_BOUNDARIES.md).

**Must not:** connect to SAS Radius; invent local balances; mutate payment/wallet paths; block page loads on live Radius.

**Billing:** read-only views from Zoho-synced Customer / Invoice / Payment tables.

## Backend tests

`tests/Feature/Stage10ServiceLifecycleTest.php` — lifecycle paths, Radius flag off, payment count stability, mocked WhatsApp notifications.

## Verification

```bash
cd frontend && npm run lint && npm run build && npx playwright test e2e/stage10-services.spec.ts
cd ../backend && php artisan test --filter=Stage10
```
