# Real E2E tests (against a live backend)

These Playwright specs exercise the **real** API and UI together. They are separate from `frontend/e2e/`, which uses **mocked** network responses.

## When to use

- Smoke / contract confidence after deploy
- Verifying login → apps launcher → create service against a real Stage 10.2 backend
- Branch-scoped API checks that mocks cannot prove

## Environment

| Variable | Required | Purpose |
|----------|----------|---------|
| `E2E_REAL=1` | Yes | Enables real specs (otherwise they `test.skip`) |
| `BASE_URL` or `PLAYWRIGHT_BASE_URL` | Yes | Frontend origin (e.g. `https://app.example.com` or `http://127.0.0.1:3000`) |
| `E2E_USER` / `E2E_PASSWORD` | Recommended | Login credentials with services + apps permissions |
| `E2E_BRANCH_ID` | Optional | Branch used when creating a service |

Without `E2E_REAL=1` and a base URL, specs skip cleanly so CI mocked suites stay green.

## Run

```bash
cd frontend
npx playwright test -c playwright.real.config.ts
# or
E2E_REAL=1 BASE_URL=http://127.0.0.1:3000 E2E_USER=manager@example.com E2E_PASSWORD=secret \
  npx playwright test -c playwright.real.config.ts
```

## Coverage (smoke)

`smoke.spec.ts` (when env present):

1. Login
2. Open `/apps` launcher
3. Navigate toward services and attempt create-service happy path (or assert create form reaches API)

## Related

- Mocked launcher / stage suites: `frontend/e2e/` (see README note there)
- Backend contract: `Stage102ContractTest`, `Stage102BranchIntegrityTest`, `Stage102OperationalSearchTest`
- Doc: `docs/REAL_E2E_TESTING.md`
