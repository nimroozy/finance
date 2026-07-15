# API (Stage 1)

Base path: `/api/v1`

## Public

| Method | Path | Notes |
|--------|------|-------|
| GET | `/health` | DB + Redis |
| POST | `/auth/login` | `{ login, password }` throttled |

## Authenticated (Bearer token)

| Method | Path | Permission |
|--------|------|------------|
| POST | `/auth/logout` | — |
| GET | `/auth/me` | — |
| POST | `/auth/change-password` | — |
| GET/POST | `/users` | users.view / users.manage |
| GET/PUT | `/users/{id}` | users.view / users.manage |
| POST | `/users/{id}/disable` | users.manage |
| GET | `/roles` | roles.view |
| GET/POST | `/branches` | branches.view / manage |
| GET/PUT | `/branches/{id}` | branches.view / manage |
| GET | `/audit-logs` | audit.view |
| GET/PUT | `/settings` | settings.manage |

Response envelope:

```json
{ "success": true, "data": {}, "meta": {} }
```

```json
{ "success": false, "message": "...", "errors": {} }
```

OpenAPI export is planned for a later stage.
