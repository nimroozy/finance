# Frontend E2E (mocked)

These Playwright specs under `frontend/e2e/` are **mocked API** tests. They intercept `/api/v1/*` (and auth) so the UI can be exercised without a live backend.

For **real backend** smoke tests, see `frontend/e2e-real/` and `docs/REAL_E2E_TESTING.md`.

## Run mocked

```bash
cd frontend
npm run build && npm run start &
npx playwright test
# filter examples:
npx playwright test stage91
npx playwright test stage10
```
