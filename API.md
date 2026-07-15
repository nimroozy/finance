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

Response envelope:

```json
{ "success": true, "data": {}, "meta": {} }
```

```json
{ "success": false, "message": "...", "errors": {} }
```
