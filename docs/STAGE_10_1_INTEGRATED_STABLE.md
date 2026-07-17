# Stage 10.1 — Integrated Stable Baseline

## Status

**Integrated stable** on branch `cursor/stage-10-1-integrated-stable`.

`APP_STAGE` / version label: **`10.1-integrated-stable`**.

This branch is the audit-preserving merge of Stages **7 → 7.1 → 8 → 9 → 9.1 → 10** into one deployable baseline. It does **not** start Stage 11.

## Objective

Ship one VPS-ready tip that includes:

| Stage | Capability |
|-------|------------|
| 7 / 7.1 | Ticketing, tasks, installation queue, ops filters/pickers |
| 8 | CRM sales pipeline + install handoff |
| 9 | Inventory ledger, equipment, sites/towers |
| 9.1 | Unified `/apps` launcher, shell, theme, EN/FA RTL |
| 10 | ISP service lifecycle (packages → activate/suspend → holds) |

## Hard rules

1. **No Stage 11** dashboards / purchasing enablement beyond what Stage 9 already shipped.
2. **No Radius** — `platform.modules.radius.enabled` stays `false` (Stage 12).
3. Do **not** mutate Zoho SoT billing math, payment/wallet/handover/cashbox calculations, or inventory ledger rules.
4. Do **not** close superseded draft PRs **#15** / **#16** — keep them for audit; recommend close later as superseded by **#17**.
5. Preserve SSH deploy keys (**KEY_KEPT**).

## Related PRs (do not auto-close)

| PR | Branch | Role |
|----|--------|------|
| [#15](https://github.com/nimroozy/finance/pull/15) | `cursor/stage-10-service-lifecycle` | Stage 10 only — **superseded by #17**; preserve |
| [#16](https://github.com/nimroozy/finance/pull/16) | `cursor/stage-9-1-unified-app-ui` | Stage 9.1 only — **superseded by #17**; preserve |
| [#17](https://github.com/nimroozy/finance/pull/17) | `cursor/stage-10-1-integrated-stable` | **Integrated stable** tip |

## Docs in this pack

- [STAGE_10_1_DELIVERY_REPORT.md](STAGE_10_1_DELIVERY_REPORT.md)
- [BRANCH_INTEGRATION_HISTORY.md](BRANCH_INTEGRATION_HISTORY.md)
- [REGRESSION_TEST_MATRIX.md](REGRESSION_TEST_MATRIX.md)
- [PRODUCTION_SMOKE_TEST.md](PRODUCTION_SMOKE_TEST.md)
- Service domain: [STAGE_10_SERVICE_LIFECYCLE.md](STAGE_10_SERVICE_LIFECYCLE.md)
- Shell: [STAGE_9_1_UI_UX.md](STAGE_9_1_UI_UX.md), [APP_LAUNCHER_ARCHITECTURE.md](APP_LAUNCHER_ARCHITECTURE.md)

## Verification (local)

```bash
cd backend && php artisan test --filter='Stage7|Stage71|Stage8|Stage9|Stage91|Stage10|Authentication|WhatsApp'
cd ../frontend && npm run lint && npm run build && npm run i18n:check
npx playwright test e2e/stage7.spec.ts e2e/stage71-functional.spec.ts \
  e2e/stage8-crm.spec.ts e2e/stage9-inventory.spec.ts \
  e2e/stage91-launcher.spec.ts e2e/stage91-matrix.spec.ts \
  e2e/stage10-services.spec.ts
```

## Version endpoint

`GET /api/v1/system/version` must report `stage: "10.1-integrated-stable"` after VPS deploy.
