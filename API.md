# API

Base path: `/api/v1`

## Public

| Method | Path | Notes |
|--------|------|-------|
| GET | `/health` | DB + Redis |
| POST | `/auth/login` | `{ login, password }` throttled |
| GET | `/zoho/oauth/callback` | OAuth browser redirect (state validated) |

## Authenticated (Bearer token)

### Stage 1
| Method | Path | Permission |
|--------|------|------------|
| POST | `/auth/logout` | — |
| GET | `/auth/me` | — |
| POST | `/auth/change-password` | — |
| CRUD | `/users`, `/branches` | users.* / branches.* |
| GET | `/roles` | roles.view |
| GET | `/audit-logs` | audit.view |
| GET/PUT | `/settings` | settings.manage |

### Stage 2 — Zoho
| Method | Path | Permission |
|--------|------|------------|
| GET | `/zoho/status` | zoho.view |
| GET | `/zoho/data-centers` | zoho.configure |
| PUT | `/zoho/settings` | zoho.configure |
| GET | `/zoho/oauth/redirect` | zoho.configure |
| POST | `/zoho/disconnect` | zoho.configure |
| GET | `/zoho/organizations` | zoho.view |
| POST | `/zoho/organizations/select` | zoho.configure |
| POST | `/zoho/test` | zoho.view |
| POST | `/zoho/sync/{customers\|invoices\|full}` | zoho.sync |
| GET | `/zoho/sync-jobs` | zoho.view |
| POST | `/zoho/sync-jobs/{id}/retry` | zoho.sync |
| GET | `/zoho/api-logs` | zoho.view |
| CRUD | `/zoho/branch-mappings` | zoho.configure |
| GET/PUT | `/zoho/reporting-tag-mappings` | zoho.configure |
| GET | `/customers`, `/customers/{id}` | customers.view |
| GET | `/customers/unmapped` | zoho.configure \| customers.manage |
| POST | `/customers/{id}/map-branch` | customers.manage |
| GET | `/invoices`, `/invoices/{id}` | invoices.view |
| GET | `/debtors` | debtors.view |
| GET | `/debtors/export` | debtors.export |

### Stage 3 — Assignments

| Method | Path | Permission |
|--------|------|------------|
| GET | `/assignments` | assignments.view |
| GET | `/assignments/unassigned-debtors` | assignments.view |
| GET | `/assignments/export` | assignments.view |
| GET | `/collectors/workload` | assignments.view |
| GET | `/assignments/{id}` | assignments.view |
| GET | `/assignments/{id}/history` | assignments.view |
| POST | `/assignments` | assignments.manage |
| POST | `/assignments/bulk` | assignments.manage |
| POST | `/assignments/auto-preview` | assignments.manage |
| POST | `/assignments/auto-confirm` | assignments.manage |
| POST | `/assignments/{id}/reassign` | assignments.reassign |
| POST | `/assignments/{id}/cancel` | assignments.cancel |
| POST | `/assignments/{id}/accept` | auth (collector owns assignment) |
| POST | `/assignments/{id}/viewed` | auth (collector owns assignment) |

### Stage 3 — Visits

| Method | Path | Permission |
|--------|------|------------|
| GET | `/visits` | visits.view |
| GET | `/visits/outcomes` | visits.view |
| GET | `/visits/{id}` | visits.view |
| POST | `/visits` | visits.create \| visits.manage |
| POST | `/visits/{id}/correction-note` | visits.manage |

### Stage 3 — Routes

| Method | Path | Permission |
|--------|------|------------|
| GET | `/routes` | routes.view |
| GET | `/routes/{id}` | routes.view |
| POST | `/routes/{id}/start` | routes.view |
| POST | `/routes/{id}/complete` | routes.view |
| POST | `/routes` | routes.manage |
| PUT | `/routes/{id}` | routes.manage |
| DELETE | `/routes/{id}` | routes.manage |
| POST | `/routes/{id}/publish` | routes.manage |
| POST | `/routes/{id}/cancel` | routes.manage |
| PUT | `/routes/{id}/stops` | routes.manage |

### Stage 3 — Promises

| Method | Path | Permission |
|--------|------|------------|
| GET | `/promises` | promises.view |
| POST | `/promises` | promises.create \| promises.manage |
| POST | `/promises/{id}/cancel` | promises.create \| promises.manage |
| POST | `/promises/{id}/fulfill` | promises.manage |
| POST | `/promises/{id}/supersede` | promises.manage |

### Stage 3 — Notes

| Method | Path | Permission |
|--------|------|------------|
| GET | `/notes` | notes.view |
| POST | `/notes` | notes.create \| notes.manage |
| PATCH | `/notes/{id}` | notes.create \| notes.manage |

### Stage 3 — Evidence

| Method | Path | Permission |
|--------|------|------------|
| POST | `/visits/{id}/files` | evidence.upload |
| GET | `/files/{id}/download` | evidence.view |

### Stage 3 — Notifications

| Method | Path | Permission |
|--------|------|------------|
| GET | `/notifications` | notifications.view |
| POST | `/notifications/read-all` | notifications.view |
| POST | `/notifications/{id}/read` | notifications.view |

### Stage 3 — Collectors

| Method | Path | Permission |
|--------|------|------------|
| GET | `/collectors` | collectors.view |
| POST | `/collectors` | collectors.manage |
| PUT | `/collectors/{id}` | collectors.manage |
| GET | `/collector/dashboard` | auth (requires collector profile) |

### Stage 3 — Reports

| Method | Path | Permission |
|--------|------|------------|
| GET | `/reports/assignments-by-collector` | reports.assignments |
| GET | `/reports/unassigned-debtors` | reports.assignments |
| GET | `/reports/visits-by-outcome` | reports.visits |
| GET | `/reports/gps-mismatch` | reports.visits |
| GET | `/reports/overdue-promises` | reports.promises |

**Not in Stage 3:** payment, receipt, wallet, or cash-handover endpoints (Stage 4+).

Response envelope:

```json
{ "success": true, "data": {}, "meta": {} }
```

```json
{ "success": false, "message": "...", "errors": {} }
```
