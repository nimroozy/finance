# Stage 8 — CRM & Sales Pipeline

## Status

**Delivered on branch** `cursor/stage-8-crm-sales` (backend foundation + frontend CRM workspace).

Do **not** start Stage 9 (inventory/assets) from this workstream.

## Objective

Branch-scoped CRM for ISP sales: leads → pipeline stages → follow-ups / surveys / quotations → conversion into customers and installation queue handoff.

## Backend (API)

Base path: `/api/v1/crm/*`

| Area | Endpoints |
|------|-----------|
| Lead sources | `GET /crm/lead-sources` |
| Leads | `GET/POST /crm/leads`, `GET/PUT /crm/leads/{id}`, `POST .../transition`, `.../assign`, `.../link-zoho-customer`, `.../convert`, `.../timeline` |
| Zoho mirror | `GET /crm/customers/search-zoho-mirror` |
| Activities | `GET/POST /crm/activities` |
| Follow-ups | `GET/POST /crm/follow-ups`, `POST .../complete`, `.../cancel` |
| Coverage | `GET/POST /crm/coverage-checks` |
| Site surveys | `GET/POST /crm/site-surveys`, `POST .../complete` |
| Quotations | `GET/POST /crm/quotations`, `POST .../send|accept|reject` |
| Opportunities | `GET/POST /crm/opportunities`, `PUT /crm/opportunities/{id}` |
| Targets | `GET/POST /crm/sales-targets` |
| Dashboard / reports | `GET /crm/dashboard`, `GET /crm/reports/leads` (CSV) |

Config: `backend/config/crm.php` (`CRM_ENABLED`).

Permissions are fine-grained (`crm.leads.*`, `crm.quotations.*`, `crm.reports.view`, …) and seeded via RolePermission seeder.

## Frontend

Feature flag: `frontend/src/config/feature-flags.ts` → `crm.enabled = true`.

API client: `frontend/src/lib/crm.ts`.

Nav group **CRM & Sales** in `app-shell` (behind flag + permissions):

- `/crm/dashboard`
- `/crm/pipeline`
- `/crm/leads`, `/crm/leads/new`, `/crm/leads/[id]`
- `/crm/follow-ups`
- `/crm/quotations`
- `/crm/surveys`
- `/crm/targets`
- `/crm/reports`

UI reuses Stage 7 ops components (`WorkspaceHeader`, `FilterBar`, `DataTable`, `StatusActionMenu`, pickers, etc.).

i18n: `crm` + nav keys in `en.json` / `fa.json`.

E2E: `frontend/e2e/stage8-crm.spec.ts`.

## Pipeline stages

`lead` → `contacted` → `qualified` → `coverage_check` → `site_survey` → `quotation` → `negotiation` → `approved` → `finance_review` → `installation_request` → … → `active_customer` / `after_sales`, plus `lost` / `cancelled`.

Conversion requires an existing Zoho-linked local customer (`zoho_contact_id`); search/link first. No Zoho contact create or Radius activation from CRM (Stage 10.2).

## Boundaries

See [DOMAIN_BOUNDARIES.md](DOMAIN_BOUNDARIES.md) and [CRM_INSTALLATION_MODEL.md](CRM_INSTALLATION_MODEL.md).

**Must not:** mutate payment calculation paths; implement Stage 9 inventory ledger; call Zoho/Radius inline inside CRM transactions.

## Verification

```bash
cd frontend && npm run lint && npm run build && npx playwright test e2e/stage8-crm.spec.ts
cd ../backend && php artisan test --filter=Stage8
```
