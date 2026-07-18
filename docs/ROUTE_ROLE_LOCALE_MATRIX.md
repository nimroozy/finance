# Route / Role / Locale Matrix (Stage 10.3 brief)

## Locales

| Locale | Prefix | Dir |
|--------|--------|-----|
| English | `/en/...` | `ltr` |
| Persian | `/fa/...` | `rtl` |

Shell and launcher must render both; i18n key parity via `npm run i18n:check`.

## Primary launcher apps (role-gated)

Visible on `/apps` when the user has the matching permission(s). Submodules stay in context nav only.

| App | Typical roles | EN / FA |
|-----|---------------|---------|
| Customers | admin, manager, sales, collections | both |
| Payments | admin, manager, collections | both |
| Collections | admin, manager, collector | both |
| Tasks | admin, manager, field/office | both |
| Support (tickets) | admin, manager, support | both |
| NOC | admin, NOC/tech | both |
| CRM | admin, manager, sales | both |
| Installations | admin, manager, sales/field | both |
| Inventory | admin, manager, warehouse | both |
| Services | admin, manager, NOC | both |
| Reports | admin, manager | both |
| Administration | Super Admin / admin | both |

Unauthorized apps are hidden from the grid; deep links to forbidden routes surface `/403`.

## Smoke routes (deploy gate)

| Route | Expect |
|-------|--------|
| `/en/apps` | 200, launcher |
| `/fa/apps` | 200, `dir=rtl` |
| `/en/services` | 200 |
| `/en/services/cancellations` | 200 |

## Automated coverage

- Mocked: `e2e/stage91-launcher.spec.ts`, `e2e/stage91-matrix.spec.ts`, `e2e/stage10-services.spec.ts`
- Real (non-prod seed): `e2e-real/acceptance.spec.ts` — see [REAL_WORKFLOW_RESULTS.md](REAL_WORKFLOW_RESULTS.md)
