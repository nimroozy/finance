# Real E2E Testing

## Layout

| Suite | Path | Mode |
|-------|------|------|
| Mocked UI | `frontend/e2e/` | Intercepts APIs; default CI |
| Real smoke | `frontend/e2e-real/` | Hits live API when enabled |

## Enabling real smoke

```bash
cd frontend
E2E_REAL=1 \
BASE_URL=https://your-frontend \
E2E_USER=manager@example.com \
E2E_PASSWORD='…' \
npx playwright test -c playwright.real.config.ts
```

Specs **skip** when `E2E_REAL` / `BASE_URL` are absent so mocked pipelines stay green.

## Minimum smoke

- Login
- Apps launcher visible
- `GET /api/v1/apps/counts` succeeds
- Services area reachable; create UI when available

## Backend contracts

Prefer Feature tests for field/permission/branch guarantees:

- `Stage102ContractTest`
- `Stage102BranchIntegrityTest`
- `Stage102OperationalSearchTest`

Expand real E2E later with role matrix and multi-branch isolation once a shared staging tenant exists.
