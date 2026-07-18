# Stage 10.3 — Functional Acceptance

## Status

**Tip branch:** `cursor/stage-10-3-functional-acceptance`  
**Version label:** `10.3-functional-acceptance`

Functional acceptance of the integrated Stages 7–10.2 baseline: launcher primary-app grid, ActivationPanel evidence UI, service queue surfaces, AcceptanceSeeder (non-production), and reviewed demo cleanup.

## Objective

Prove production-ready functional acceptance without starting Stage 11:

| Area | Acceptance focus |
|------|------------------|
| Launcher | Primary apps only; EN/FA; role-aware visibility |
| Services | Activation checklist from API evidence; queues (pending activation, change requests, relocations, cancellations, finance hold) |
| CRM / install | Link Zoho-mirrored customer path (acceptance fixtures) |
| Cleanup | Human-reviewed manifest apply (`stage103:cleanup-demo`) — payments/stock untouched |
| Deploy | Four-way SHA match; stage label on health/version |

## Hard rules

1. **No Stage 11** — purchasing / unified dashboards stay disabled.
2. **No Radius** — `platform.modules.radius.enabled` remains `false`.
3. **No live WhatsApp** sends required for acceptance.
4. **No uncontrolled Zoho writes** — optional write probe only behind `E2E_ZOHO_WRITE=1`.
5. **Do not seed `AcceptanceSeeder` on production** — dedicated acceptance tenant / non-prod only.
6. Preserve SSH deploy key (**KEY_KEPT** — `~/.ssh/id_ed25519` never deleted).
7. Do not mutate payment/wallet/handover/cashbox math or inventory ledger rules.

## Related docs

- [STAGE_10_3_DELIVERY_REPORT.md](STAGE_10_3_DELIVERY_REPORT.md)
- [PRODUCTION_SHA_VERIFICATION.md](PRODUCTION_SHA_VERIFICATION.md)
- [ROUTE_ROLE_LOCALE_MATRIX.md](ROUTE_ROLE_LOCALE_MATRIX.md)
- [UI_ACCEPTANCE_RESULTS.md](UI_ACCEPTANCE_RESULTS.md)
- [MOBILE_ACCEPTANCE_RESULTS.md](MOBILE_ACCEPTANCE_RESULTS.md)
- [REAL_WORKFLOW_RESULTS.md](REAL_WORKFLOW_RESULTS.md)
- [STAGE_10_3_DEMO_DATA_REVIEW.md](STAGE_10_3_DEMO_DATA_REVIEW.md)
- [STAGE_10_3_CLEANUP_RESULT.md](STAGE_10_3_CLEANUP_RESULT.md)
- [APP_LAUNCHER_ARCHITECTURE.md](APP_LAUNCHER_ARCHITECTURE.md)

## Local verification

```bash
cd backend && php artisan test --filter='Stage10|Stage102|Stage103|Stage8|Stage91|Acceptance'
cd ../frontend && npm run lint && npm run build && npm run i18n:check
npm run e2e:mocked -- e2e/stage91-launcher.spec.ts e2e/stage91-matrix.spec.ts e2e/stage10-services.spec.ts
```

## Version / stage

`GET /api/v1/health` → `data.deployment.stage` = `10.3-functional-acceptance`  
`GET /api/v1/system/version` → `data.stage` = `10.3-functional-acceptance`
