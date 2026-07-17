# Stage 8 Delivery Report

**Date (UTC):** 2026-07-17  
**Branch:** `cursor/stage-8-crm-sales`  
**SHA deployed:** `fa02b63f43434418611493442a2b78c1bc27807e`  
**Draft PR:** https://github.com/nimroozy/finance/pull/13  
**VPS:** `root@209.38.194.184` (`/opt/collection-system`)  
**Deploy stamp:** `20260717T150750Z`  
**Do not merge.** **Stage 9 not started.**

---

## Summary

Stage 8 CRM & Sales Pipeline is live on production:

- Stage 8 CRM migrations applied (batch **15**)
- Lead sources seeded (10 codes)
- CRM permissions via `RolePermissionSeeder`
- STAGE8 TEST lead walked through contacted → qualified → coverage → site survey (task) → quotation accept → customer/installation conversion
- Financial tables untouched; no live WhatsApp sends

---

## Explicit non-goals

- Does not merge PR #13 or prior stacked PRs
- Does not start Stage 9 inventory/assets
- Does not modify payment, wallet, handover, cashbox, or custody reversal rows/calculations
- Does not send live WhatsApp/Meta messages
- Does not delete SSH deploy keys (**KEY_KEPT**)

---

## Backups

| Phase | Path | Status |
|-------|------|--------|
| Pre-deploy (labeled) | `/opt/collection-backups/20260717T150750Z-stage8-predeploy/` | **Done** — `postgres.dump` (~1.9MB) + gz + storage |
| Pre-deploy (script) | `/opt/collection-backups/20260717T150858Z` | **Done** — `./scripts/backup.sh` |
| Post-deploy (labeled) | `/opt/collection-backups/20260717T150750Z-stage8-postdeploy/` | **Done** — `postgres.dump` (~1.9MB) + gz + storage |
| Post-deploy (script) | `/opt/collection-backups/20260717T151339Z` | **Done** — `./scripts/backup.sh` |

---

## Pre-deploy snapshot

| Item | Value |
|------|-------|
| BEFORE `.deployed-sha` | `3c08df520e5025f222876dbd6ce9329da3fafdeb` |
| Compose | backend/frontend healthy; nginx/postgres/redis/queue/scheduler up |
| Ports | **80/443** public via nginx; app ports internal |
| Zoho scheduler | `zoho:scheduler-tick` every minute (active); plus promise/reconciliation/SLA/escalation schedules |

### Financial / CRM counts (pre)

| Metric | Count |
|--------|-------|
| payments | 3 |
| cash_handover_requests | 1 |
| collector_wallets | 3 |
| branch_cashboxes | 2 |
| payment_reversals | 3 |
| crm_* tables | **0** (not yet migrated) |

---

## Deploy steps

1. `git pull` on `cursor/stage-8-crm-sales` → SHA `fa02b63f…`
2. `./scripts/sync-to-vps.sh` → `root@209.38.194.184:/opt/collection-system/`
3. VPS `./scripts/deploy.sh` (build, up, migrate, RolePermissionSeeder, skip admin reinstall)
4. Migration `2026_07_17_170000_create_stage8_crm_tables` → batch **15**
5. Seeders re-run (all **SEED_OK**)
6. Wrote host `.deployed-sha` + `APP_COMMIT_SHA` in `.env` (secrets preserved)
7. Copied `.deployed-sha` into running backend/queue/scheduler containers for DeploymentInfo

### Seeders

| Seeder | Result |
|--------|--------|
| `RolePermissionSeeder` | **SEED_OK** |
| `Stage8LeadSourceSeeder` | **SEED_OK** (10 sources) |
| `Stage7OrgSeeder` | **SEED_OK** |
| `Stage7SlaPolicySeeder` | **SEED_OK** |
| `Stage7TicketTypeSeeder` | **SEED_OK** |
| `Stage7TaskTemplateSeeder` | **SEED_OK** |
| `Stage7EscalationRuleSeeder` | **SEED_OK** |

`crm_lead_sources` codes: campaign, field_sales, other, partner_dealer, phone_inbound, referral, social_media, walk_in, website, whatsapp.

---

## Financial / ops counts (production)

| Metric | Pre | Post (after STAGE8 TEST) | Match |
|--------|-----|--------------------------|-------|
| payments | 3 | 3 | **MATCH** |
| cash_handover_requests | 1 | 1 | **MATCH** |
| collector_wallets | 3 | 3 | **MATCH** |
| branch_cashboxes | 2 | 2 | **MATCH** |
| payment_reversals | 3 | 3 | **MATCH** |
| crm_lead_sources | 0 | 10 | seeded |
| crm_leads | 0 | 1 | +1 STAGE8 TEST |
| crm_activities | 0 | 1 | +1 STAGE8 TEST |
| crm_follow_ups | 0 | 1 | +1 STAGE8 TEST |
| crm_coverage_checks | 0 | 1 | +1 STAGE8 TEST |
| crm_site_surveys | 0 | 1 | +1 STAGE8 TEST |
| crm_quotations | 0 | 1 | +1 STAGE8 TEST |
| tasks | (prior 2) | 3 | +1 survey task |
| installations | (prior 2) | 3 | +1 from conversion |

---

## STAGE8 TEST records

Label: **STAGE8 TEST LEAD - DO NOT USE** / **STAGE8 TEST**

| Entity | ID | Number / note |
|--------|----|----------------|
| Lead | 1 | `01-LEAD-2026-000001` — pipeline ended `installation_request` |
| Activity | 1 | type `call` (no WhatsApp) |
| Follow-up | 1 | pending |
| Coverage check | 1 | `survey_required` |
| Site survey | 1 | task_id **3** |
| Survey task | 3 | confirmed exists |
| Quotation | 1 | draft → **accepted** |
| Customer | 7000 | `CRM-IUSPSJ35` — STAGE8 TEST |
| Installation | 3 | `01-INS-2026-000001` — linked on lead |

Flow verified: lead → contacted → qualified → coverage_check → site_survey (+task) → quotation → approved → convert → installation_request.  
**whatsapp_sent=no** · **payments_untouched=yes**

---

## Production verification checklist

- [x] Pre-deploy backup + financial counts snapshot
- [x] Code sync via `scripts/sync-to-vps.sh` + `./scripts/deploy.sh`
- [x] Stage 8 migration batch 15; Stage 8 + Stage 7 + RolePermission seeders SEED_OK
- [x] Financial counts unchanged
- [x] Docker compose healthy
- [x] Health: `GET https://finance.mns.af/api/v1/health` → **200** with `deployment.commit_sha=fa02b63f…`
- [x] `/up` → 200; `/en/login` → 200; Super Admin not reinstalled
- [x] `.deployed-sha` on host + in backend containers; `APP_COMMIT_SHA` set without clobbering secrets
- [x] `crm_lead_sources` = 10
- [x] STAGE8 TEST records created (DO NOT USE)
- [x] Post-deploy backup
- [x] SSH key **KEY_KEPT** (never deleted)
- [x] Stage 9 **not** started; PR **#13 not merged**

### Health / DeploymentInfo

```json
{
  "status": "ok",
  "checks": {"database": "ok", "redis": "ok"},
  "deployment": {
    "app_name": "MNS Collection",
    "commit_sha": "fa02b63f43434418611493442a2b78c1bc27807e",
    "backend_version": "7.1",
    "php_version": "8.4.23",
    "laravel_version": "12.64.0"
  }
}
```

Migration batch **15**; latest migration `2026_07_17_170000_create_stage8_crm_tables`.

---

## Notes

- `backend_version` config still reports `7.1` (DeploymentInfo default/config); commit SHA is the deploy identity for Stage 8.
- Host `.env` `APP_COMMIT_SHA` may require container recreate to appear via `env()`; file-based `.deployed-sha` inside containers is sufficient for health probes.
- Zoho scheduler tick remained active through deploy (no interruption required).

---

## References

- `docs/STAGE_8_CRM_SALES.md` — Stage 8 scope
- `docs/CRM_INSTALLATION_MODEL.md`, `docs/DOMAIN_BOUNDARIES.md`
- PR https://github.com/nimroozy/finance/pull/13
- Prior: `docs/STAGE_7_1_DELIVERY_REPORT.md`
