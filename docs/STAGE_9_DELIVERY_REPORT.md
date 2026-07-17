# Stage 9 Delivery Report

**Date (UTC):** 2026-07-17  
**Branch:** `cursor/stage-9-inventory-assets`  
**SHA deployed:** `4ab751f3305bc44162968fedd3e0848fd7ff7424`  
**Draft PR:** https://github.com/nimroozy/finance/pull/14  
**VPS:** `root@209.38.194.184` (`/opt/collection-system`)  
**Deploy stamp:** `20260717T155006Z`  
**Do not merge.** **Stage 10 not started.**

---

## Summary

Stage 9 Inventory, Assets, Sites & Towers is live on production:

- Stage 9 inventory migration already present (batch **16**); tables empty pre-seed
- Categories (9) + default locations seeded per branch
- Inventory permissions via `RolePermissionSeeder`
- STAGE9 TEST walked through qty/serial receive → reservation/fulfill → custody → customer install → site/tower → transfer → repair → stock count → immutability
- Financial tables untouched; no live Zoho bills; no WhatsApp live send

---

## Explicit non-goals

- Does not merge PR #14 or prior stacked PRs
- Does not start Stage 10 Radius
- Does not modify payment, wallet, handover, cashbox, or custody reversal rows/calculations
- Does not send live WhatsApp/Meta messages
- Does not create live Zoho bills from inventory TX
- Does not delete SSH deploy keys (**KEY_KEPT**)

---

## Backups

| Phase | Path | Status |
|-------|------|--------|
| Pre-deploy (labeled) | `/opt/collection-backups/20260717T155006Z-stage9-predeploy/` | **Done** — `postgres.dump` (~1.9MB) + gz + storage |
| Post-deploy (labeled) | `/opt/collection-backups/20260717T155006Z-stage9-postdeploy/` | **Done** — `postgres.dump` (~2.1MB) + gz + storage |
| Post-deploy (script) | `/opt/collection-backups/20260717T155609Z` | **Done** — `./scripts/backup.sh` |

---

## Pre-deploy snapshot

| Item | Value |
|------|-------|
| BEFORE `.deployed-sha` | `6a6bc81a95775ae298bad85326104080d94701ae` |
| Compose | backend/frontend healthy; nginx/postgres/redis/queue/scheduler up |
| Ports | **80/443** public via nginx; app ports internal |
| Health `/up` | **200** |

### Financial / CRM counts (pre)

| Metric | Count |
|--------|-------|
| payments | 3 |
| cash_handover_requests | 1 |
| collector_wallets | 3 |
| branch_cashboxes | 2 |
| payment_reversals | 3 |
| customers | 6961 |
| tickets | 2 |
| tasks | 3 |
| installations | 3 |
| crm_leads | 1 |
| crm_lead_sources | 10 |
| inventory_* tables | present (batch 16), **0 rows** |

---

## Deploy steps

1. `git pull` on `cursor/stage-9-inventory-assets` → SHA `4ab751f3305bc44162968fedd3e0848fd7ff7424`
2. `./scripts/sync-to-vps.sh` → `root@209.38.194.184:/opt/collection-system/`
3. VPS `./scripts/deploy.sh` (build, up, migrate → **Nothing to migrate**, RolePermissionSeeder, skip admin reinstall)
4. Migration `2026_07_17_180000_create_stage9_inventory_tables` already Ran → batch **16**
5. Seeders re-run (all **SEED_OK**)
6. Wrote host `.deployed-sha` + `APP_COMMIT_SHA` in `.env` (secrets preserved)
7. Recreated backend/queue/scheduler so `APP_COMMIT_SHA` env matched; copied `.deployed-sha` into containers

### Seeders

| Seeder | Result |
|--------|--------|
| `RolePermissionSeeder` | **SEED_OK** |
| `Stage9ProductCategorySeeder` | **SEED_OK** (9 categories) |
| `Stage9DefaultLocationsSeeder` | **SEED_OK** (MAIN-WH / SALES / TECH / REPAIR / … per branch) |
| `Stage8LeadSourceSeeder` | **SEED_OK** |
| `Stage7OrgSeeder` | **SEED_OK** |
| `Stage7SlaPolicySeeder` | **SEED_OK** |
| `Stage7TicketTypeSeeder` | **SEED_OK** |
| `Stage7TaskTemplateSeeder` | **SEED_OK** |
| `Stage7EscalationRuleSeeder` | **SEED_OK** |

`inventory_product_categories` codes: CPE, RADIO, ROUTER, POWER, CABLE, TOWER, OFFICE, TOOL, SERVICE.

---

## Financial / ops counts (production)

| Metric | Pre | Post (after STAGE9 TEST) | Match |
|--------|-----|--------------------------|-------|
| payments | 3 | 3 | **MATCH** |
| cash_handover_requests | 1 | 1 | **MATCH** |
| collector_wallets | 3 | 3 | **MATCH** |
| branch_cashboxes | 2 | 2 | **MATCH** |
| payment_reversals | 3 | 3 | **MATCH** |
| customers | 6961 | 6961 | **MATCH** |
| tickets | 2 | 2 | **MATCH** |
| tasks | 3 | 3 | **MATCH** |
| installations | 3 | 4 | +1 STAGE9 TEST |
| crm_lead_sources | 10 | 10 | **MATCH** |
| inventory_product_categories | 0 | 9 | seeded |
| inventory_locations | 0 | 82 | seeded (+ tower loc) |
| inventory_products | 0 | 2 | STAGE9 TEST |
| inventory_equipment | 0 | 3 | STAGE9 TEST serials |
| inventory_stock_transactions | 0 | 14 | ledger movements |
| inventory_reservations | 0 | 1 | STAGE9 TEST |
| inventory_transfers | 0 | 1 | STAGE9 TEST |
| inventory_sites | 0 | 1 | STAGE9 TEST SITE |
| inventory_towers | 0 | 1 | STAGE9 TEST TOWER |
| inventory_custody_records | 0 | 1 | STAGE9 TEST |
| inventory_repairs | 0 | 1 | STAGE9 TEST |
| inventory_stock_counts | 0 | 1 | STAGE9 TEST |
| inventory_customer_equipment | 0 | 1 | STAGE9 TEST |

---

## STAGE9 TEST records

Label: **STAGE9 TEST … - DO NOT USE**

| Step | Result |
|------|--------|
| 1 Quantity product | id **1** `STAGE9-QTY-DONOTUSE` — *STAGE9 TEST PRODUCT - DO NOT USE* |
| 2 Serialized product | id **2** `STAGE9-SER-DONOTUSE` — *STAGE9 TEST SERIAL PRODUCT* |
| 3 Receive qty → MAIN-WH | receipt ledger; on_hand established |
| 4 Receive serial | `STAGE9-TEST-SERIAL-001` (eq **1**) |
| 4b Second serial | `STAGE9-TEST-SERIAL-002` (eq **2**) |
| 5 Installation + reserve/fulfill | installation **4** `STAGE9-INS-DONOTUSE`; reservation **1** fulfilled |
| 6 Custody issue | custody **1** issued to Super Admin |
| 7 Install at customer | `InstallationEquipmentService`; customer_equipment **1**; eq1 `installed` |
| 8 Site + tower | site **1** *STAGE9 TEST SITE*; tower **1** *STAGE9 TEST TOWER*; eq2 at tower |
| 9 Transfer WH→TECH | transfer **1** received |
| 10 Repair open/complete | repair **1** completed (`STAGE9-TEST-SERIAL-003`) |
| 11 Stock count + variance | count **1** posted |
| 12 Immutability | `StockTransaction::update` → **LogicException** (`update_blocked`) |
| 13 Zoho live bills | **none** (`zoho_bills=0`, Http fake, placeholder events only) |
| 14 WhatsApp live send | **none** (`whatsapp_messages=0`, Http::fake) |

---

## Production verification checklist

- [x] Pre-deploy backup + financial counts snapshot
- [x] Code sync via `scripts/sync-to-vps.sh` + `./scripts/deploy.sh`
- [x] Stage 9 migration batch 16; Stage 9/8/7 + RolePermission seeders SEED_OK
- [x] Financial counts unchanged (**MATCH**)
- [x] Docker compose healthy
- [x] Health: `GET https://finance.mns.af/api/v1/health` → **200** with `deployment.commit_sha=4ab751f3…`
- [x] `/up` → 200; `/en/login` → 200; Super Admin not reinstalled
- [x] `.deployed-sha` on host + in backend containers; `APP_COMMIT_SHA` set without clobbering secrets
- [x] Inventory tables exist; categories=9; locations seeded
- [x] STAGE9 TEST records created (DO NOT USE)
- [x] Stock transactions immutable
- [x] No Zoho live bills; no WhatsApp live send
- [x] Post-deploy backup
- [x] SSH key **KEY_KEPT** (never deleted)
- [x] Stage 10 **not** started; PR **#14 not merged**

### Health / DeploymentInfo

```json
{
  "status": "ok",
  "checks": {"database": "ok", "redis": "ok"},
  "deployment": {
    "app_name": "MNS Collection",
    "commit_sha": "4ab751f3305bc44162968fedd3e0848fd7ff7424",
    "backend_version": "7.1",
    "php_version": "8.4.23",
    "laravel_version": "12.64.0"
  }
}
```

Migration batch **16**; latest migration `2026_07_17_180000_create_stage9_inventory_tables`.

---

## Notes

- `backend_version` config still reports `7.1` (DeploymentInfo default/config); commit SHA is the deploy identity for Stage 9.
- Host `.env` `APP_COMMIT_SHA` required backend/queue/scheduler recreate to appear via `env()` (env takes precedence over `.deployed-sha` file).
- Stage 9 migration was already applied under prior host SHA `6a6bc81…`; this deploy refreshed code/images, seeded data, and verified the full STAGE9 TEST path.
- Zoho scheduler tick remained active through deploy (no interruption required).

---

## References

- `docs/STAGE_9_INVENTORY_ASSETS.md` — Stage 9 scope
- `docs/INVENTORY_LEDGER_MODEL.md`, `docs/SERIALIZED_EQUIPMENT_MODEL.md`, `docs/STOCK_TRANSFER_WORKFLOW.md`
- `docs/DOMAIN_BOUNDARIES.md`
- PR https://github.com/nimroozy/finance/pull/14
- Prior: `docs/STAGE_8_DELIVERY_REPORT.md`
