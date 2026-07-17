# Stage 10 Delivery Report

**Date (UTC):** 2026-07-17  
**Branch:** `cursor/stage-10-service-lifecycle`  
**SHA deployed:** `3f202774b710cfb7e8d2b1ac3c1373c13ad14812`  
**Draft PR:** https://github.com/nimroozy/finance/pull/15  
**VPS:** `root@209.38.194.184` (`/opt/collection-system`)  
**Deploy stamp:** `20260717T170018Z`  
**Do not merge.** **Stage 11 not started.** **No Radius.**

---

## Summary

Stage 10 ISP Service Lifecycle is live on production:

- Stage 10 service migration batch **17** (`2026_07_17_190000_create_stage10_service_tables`)
- Service types / access technologies / SLA templates seeded
- Service permissions via `RolePermissionSeeder`
- STAGE10 TEST walked through package → location → service → approve/activate → suspend/reactivate → package change → finance hold/release → billing view → cancel (`equipment_return_required`)
- Financial tables untouched; inventory `on_hand` sum unchanged; no Radius; no live WhatsApp

---

## Explicit non-goals

- Does not merge PR #15 or prior stacked PRs
- Does not start Stage 11 dashboards
- Does not enable or connect SAS Radius (deferred to Stage 12)
- Does not modify payment, wallet, handover, cashbox, or reversal rows/calculations
- Does not send live WhatsApp/Meta messages
- Does not invent local balances or mutate Zoho SoT billing data
- Does not delete SSH deploy keys (**KEY_KEPT**)

---

## Backups

| Phase | Path | Status |
|-------|------|--------|
| Pre-deploy (labeled) | `/opt/collection-backups/20260717T170018Z-stage10-predeploy/` | **Done** — `postgres.dump` (~2.1MB) + gz + storage |
| Post-deploy (labeled) | `/opt/collection-backups/20260717T170018Z-stage10-postdeploy/` | **Done** — `postgres.dump` (~2.2MB) + gz + storage |
| Post-deploy (script) | `/opt/collection-backups/20260717T170609Z` | **Done** — `./scripts/backup.sh` |

---

## Pre-deploy snapshot

| Item | Value |
|------|-------|
| BEFORE `.deployed-sha` | `b2cec3006f67a7ac7e84447eb320ca86385d68d3` |
| Compose | backend/frontend healthy; nginx/postgres/redis/queue/scheduler up |
| Ports | **80/443** public via nginx; app ports internal |
| Health `/api/v1/health` | **200** |
| Radius in `.env` | **none** (grep -i radius empty) |

### Financial / CRM counts (pre)

| Metric | Count |
|--------|-------|
| payments | 3 |
| cash_handover_requests | 1 |
| collector_wallets | 3 |
| branch_cashboxes | 2 |
| payment_reversals | 3 |
| customers | 6964 |
| tickets | 2 |
| tasks | 3 |
| installations | 4 |
| crm_leads | 1 |
| services | table present (batch 17), **0 rows** pre-test |
| inventory_on_hand_sum | **18.000** |

---

## Deploy steps

1. `git pull` on `cursor/stage-10-service-lifecycle` → SHA `3f202774b710cfb7e8d2b1ac3c1373c13ad14812`
2. `./scripts/sync-to-vps.sh` → `root@209.38.194.184:/opt/collection-system/`
3. VPS `./scripts/deploy.sh` (build, up, migrate → **Nothing to migrate** / Stage 10 already batch **17**, RolePermissionSeeder, skip admin reinstall)
4. Seeders re-run (all **SEED_OK**)
5. Wrote host `.deployed-sha` + `APP_COMMIT_SHA` in `.env` (secrets preserved)
6. Recreated backend/queue/scheduler so `APP_COMMIT_SHA` env matched; copied `.deployed-sha` into containers

### Seeders

| Seeder | Result |
|--------|--------|
| `RolePermissionSeeder` | **SEED_OK** |
| `Stage10ServiceTypeSeeder` | **SEED_OK** |
| `Stage10AccessTechnologySeeder` | **SEED_OK** |
| `Stage10SlaTemplateSeeder` | **SEED_OK** |
| `Stage9ProductCategorySeeder` | **SEED_OK** |
| `Stage9DefaultLocationsSeeder` | **SEED_OK** |
| `Stage8LeadSourceSeeder` | **SEED_OK** |
| `Stage7OrgSeeder` | **SEED_OK** |
| `Stage7SlaPolicySeeder` | **SEED_OK** |
| `Stage7TicketTypeSeeder` | **SEED_OK** |
| `Stage7TaskTemplateSeeder` | **SEED_OK** |
| `Stage7EscalationRuleSeeder` | **SEED_OK** |

---

## Financial / ops counts (production)

| Metric | Pre | Post (after STAGE10 TEST) | Match |
|--------|-----|---------------------------|-------|
| payments | 3 | 3 | **MATCH** |
| cash_handover_requests | 1 | 1 | **MATCH** |
| collector_wallets | 3 | 3 | **MATCH** |
| branch_cashboxes | 2 | 2 | **MATCH** |
| payment_reversals | 3 | 3 | **MATCH** |
| inventory_on_hand_sum | 18.000 | 18.000 | **MATCH** |
| customers | 6964 | 6964 | **MATCH** |
| tickets | 2 | 2 | **MATCH** |
| tasks | 3 | 4 | +1 post-activation verification task |
| installations | 4 | 4 | **MATCH** |
| crm_leads | 1 | 1 | **MATCH** |
| services | 0 | 1 | +1 STAGE10 TEST |
| service_packages | 0 | 2 | STAGE10 TEST + upgrade |
| service_locations | 0 | 1 | STAGE10 TEST |

---

## STAGE10 TEST records

Labels: **STAGE10 TEST …** (no DO NOT USE suffix)

| Step | Result |
|------|--------|
| 1 Package + version | package **1** `STAGE10-TEST-PKG` — *STAGE10 TEST PACKAGE*; version **1** |
| 1b Upgrade package | package **2** `STAGE10-TEST-PKG-UP` — *STAGE10 TEST PACKAGE UPGRADE* |
| 2 Location | location **1** *STAGE10 TEST LOCATION* on customer **7000** |
| 3 Service | service **1** `01-SVC-2026-000001` — *STAGE10 TEST SERVICE* |
| 4 Link installation | installation **3** (STAGE8 TEST) |
| 5 Approve → activate | draft → `pending_activation` → `active`/`online`; activation **1**; task **4** |
| 6 Suspend → reactivate | suspension recorded; transition suspended→active |
| 7 Package change | change request **1** `applied`; package→**2**; MRR **2500.00** |
| 8 Finance hold → release | hold **1** placed then `released`; billing current |
| 9 Timeline | **15** entries (status_transition, activation, suspension, change_request, finance_hold, cancellation) |
| 10 Billing view | `read_only=true`, `source_of_truth=zoho` |
| 11 Cancel | cancellation **1** with `equipment_return_required=true`; commercial `cancelled`; operational `decommissioned` |
| 12 No Radius | `service_lifecycle.radius.enabled=false`; `platform.modules.radius.enabled=false`; `.env` grep empty |
| 13 No live WhatsApp | `whatsapp_messages=0`; Http faked during test |
| 14 Financial + inventory | all MATCH |

Customer used: **7000** `CRM-IUSPSJ35` — existing STAGE8 TEST (no new Zoho customer).

---

## Production verification checklist

- [x] Pre-deploy backup + financial/inventory counts snapshot
- [x] Code sync via `scripts/sync-to-vps.sh` + `./scripts/deploy.sh`
- [x] Stage 10 migration batch 17; Stage 10/9/8/7 + RolePermission seeders SEED_OK
- [x] Financial counts unchanged (**MATCH**); inventory on_hand sum unchanged (**MATCH**)
- [x] Docker compose healthy
- [x] Health: `GET https://finance.mns.af/api/v1/health` → **200** with `deployment.commit_sha=3f202774…`
- [x] `/up` → 200; Super Admin not reinstalled
- [x] `.deployed-sha` on host + in backend containers; `APP_COMMIT_SHA` set without clobbering secrets
- [x] STAGE10 TEST lifecycle completed end-to-end
- [x] No Radius config/endpoints; Radius feature flags false
- [x] No live WhatsApp send
- [x] Post-deploy backup
- [x] SSH key **KEY_KEPT** (never deleted)
- [x] Stage 11 **not** started; PR **#15 not merged**

### Health / DeploymentInfo

```json
{
  "status": "ok",
  "checks": {"database": "ok", "redis": "ok"},
  "deployment": {
    "app_name": "MNS Collection",
    "commit_sha": "3f202774b710cfb7e8d2b1ac3c1373c13ad14812",
    "backend_version": "7.1",
    "php_version": "8.4.23",
    "laravel_version": "12.64.0"
  }
}
```

Migration batch **17**; latest migration `2026_07_17_190000_create_stage10_service_tables`.

---

## Notes

- `backend_version` config still reports `7.1` (DeploymentInfo default/config); commit SHA is the deploy identity for Stage 10.
- Stage 10 migration was already applied as batch 17 under prior host SHA `b2cec30…`; this deploy refreshed code/images, seeded catalog data, wrote SHA, and verified the full STAGE10 TEST path.
- Activation creates a post-activation verification task (+1 tasks) — expected; no inventory stock movements.
- Radius remains disabled (Stage 12). No Stage 11 work.

---

## References

- `docs/STAGE_10_SERVICE_LIFECYCLE.md` — Stage 10 scope
- `docs/DOMAIN_BOUNDARIES.md`, `docs/RADIUS_INTEGRATION_MODEL.md` (deferred)
- PR https://github.com/nimroozy/finance/pull/15
- Prior: `docs/STAGE_9_DELIVERY_REPORT.md`
