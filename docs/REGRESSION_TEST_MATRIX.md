# Regression Test Matrix — Stage 10.1 Integrated Stable

## Scope

Automated gates required before calling Stage **10.1** green. No live Zoho/WhatsApp/Radius in CI/Playwright mocks.

## Backend (PHPUnit)

```bash
cd backend
php artisan test --filter='Stage7|Stage71|Stage8|Stage9|Stage91|Stage10|Authentication|WhatsApp'
```

| Suite filter | Domain |
|--------------|--------|
| `Authentication` | Login, me, lockout, CORS |
| `WhatsApp` / Stage7 WhatsApp | Intake + Cloud API foundation |
| `Stage7*` / `Stage71*` | Tickets, tasks, SLA, pickers, filters, transitions |
| `Stage8*` | CRM leads, pipeline, quotes, surveys |
| `Stage9*` | Inventory ledger, equipment, sites |
| `Stage91*` | UI preferences API |
| `Stage10*` | Service lifecycle (Radius flag remains false) |

**Expected:** all matching tests pass (109 tests / 483 assertions observed on tip).

## Frontend quality gates

```bash
cd frontend
npm run lint
npm run build
npm run i18n:check
```

| Gate | Purpose |
|------|---------|
| `lint` | ESLint clean |
| `build` | Next.js production build |
| `i18n:check` | `en.json` / `fa.json` key parity |

## Playwright (desktop + mobile)

```bash
cd frontend
npm run build   # required for webServer `npm start`
npx playwright test \
  e2e/stage7.spec.ts \
  e2e/stage71-functional.spec.ts \
  e2e/stage8-crm.spec.ts \
  e2e/stage9-inventory.spec.ts \
  e2e/stage91-launcher.spec.ts \
  e2e/stage91-matrix.spec.ts \
  e2e/stage10-services.spec.ts
```

Projects: `desktop-chromium`, `mobile-chromium` (Pixel 5).

| Spec | Focus |
|------|-------|
| `stage7` / `stage71` | Tickets create/transition, mobile task accept, filters/pickers |
| `stage8-crm` | CRM pages + create lead + stage transition |
| `stage9-inventory` | Dashboard, receive, reserve, transfer |
| `stage91-launcher` / `matrix` | `/apps`, theme, RTL, bottom nav |
| `stage10-services` | Activate / suspend / packages / finance hold |

### Known mobile shell pitfalls (covered)

| Issue | Mitigation |
|-------|------------|
| Header **Quick create** vs form **Create** | Shell label `Quick create`; clicks scoped to `main` via `e2e/helpers.ts` |
| Confirm dialog under bottom nav | `ConfirmDialog` uses `z-[60]` + `pb-24` on mobile; `clickConfirmDialog()` |
| Form submit covered by overlays | `clickMainAction(..., { force: true })` |

## Manual / VPS

See [PRODUCTION_SMOKE_TEST.md](PRODUCTION_SMOKE_TEST.md).
