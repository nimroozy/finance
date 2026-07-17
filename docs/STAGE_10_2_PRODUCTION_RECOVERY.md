# Stage 10.2 — Production Recovery

Branch: `cursor/stage-10-2-production-recovery`

## Goals

Stabilize the integrated Stage 10 tip for production use:

1. **Service API completeness** — show/index serialize tower, site, network, IPs, circuit, owners, Zoho id, dates, package version, SLA, installation, equipment summary.
2. **Global search** — permission- and branch-scoped search across customers, services, leads, tickets, tasks, installations, payments, inventory (products/serials/MAC), transfers, sites, towers, users.
3. **Launcher** — real app badge counts via `GET /api/v1/apps/counts`; purchasing stays hidden when feature flag is false; prefer main apps on the launcher with submodules in context nav.
4. **Branch integrity** — service create/update rejects customer/location/package/installation from another branch.
5. **Real E2E foundation** — `frontend/e2e-real/` (opt-in) plus mocked `frontend/e2e/`.
6. **i18n** — shell/launcher hard-coded English moved to keys.
7. **Demo cleanup** — `stage102:cleanup-demo` (see existing command + tests).

## Key endpoints

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/v1/services`, `/api/v1/services/{id}` | Expanded fields via `ServiceResource` |
| GET | `/api/v1/operations/search?q=` | Expanded entity groups |
| GET | `/api/v1/apps/counts` | Launcher badge counts |

## Tests

```bash
cd backend
php artisan test --filter=Stage102
php artisan test --filter='Stage8|Stage10|Stage91'
```

## Docs

- `DEMO_PLACEHOLDER_CLEANUP.md`
- `ZOHO_CUSTOMER_LINKING.md`
- `REAL_E2E_TESTING.md`
- `APP_LAUNCHER_ARCHITECTURE.md` (counts + main vs submodule grouping)
