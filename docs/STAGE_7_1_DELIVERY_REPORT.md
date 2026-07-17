# Stage 7.1 Delivery Report

**Date (UTC):** 2026-07-17  
**Branch:** `cursor/stage-7-1-ui-functional-repair`  
**SHA deployed:** `bfed7b9202d83efb56620ce36d0c4b49897fe428`  
**Draft PR:** https://github.com/nimroozy/finance/pull/12  
**VPS:** `root@209.38.194.184` (`/opt/collection-system`)  
**Deploy stamp:** `20260717T143722Z`  
**Do not merge.** **Stage 8 CRM not started.**

---

## Summary

Stage 7.1 closes the highest-impact UI ↔ API gaps from `docs/STAGE_7_1_FUNCTIONAL_AUDIT.md` and ships an operations workspace redesign:

- Server-side ticket/task/installation filters (priority, SLA state, assignee, search, unassigned, …)
- Lightweight `/pickers/*` endpoints + searchable Branch/Customer/User/Department pickers
- `allowed_transitions` on show resources; UI status actions constrained to allowed paths
- Attachment galleries with list + download on ticket/task detail
- WhatsApp conversation customer column fix (`contact_name`)
- Ops design system: WorkspaceHeader, ResponsiveTabs, DataTable mobile cards, SavedViewSelector
- System version / DeploymentInfo endpoint for ops visibility

---

## Explicit non-goals

- Does not merge PR #12 or prior stacked PRs
- Does not start Stage 8 CRM
- Does not modify payment, wallet, handover, cashbox, custody reversal, or Zoho reconciliation calculations
- Does not send live WhatsApp/Meta messages
- Does not delete SSH deploy keys (**KEY_KEPT**)

---

## Automated test results

| Suite | Command | Result |
|-------|---------|--------|
| Playwright Stage 7 + 7.1 | `npx playwright test e2e/stage7.spec.ts e2e/stage71-functional.spec.ts` | **22 passed** |
| PHPUnit Stage 7 + 7.1 | `php artisan test --filter='Stage7|Stage71'` | **28 passed** (154 assertions) |

### Playwright fix note

Finalization repaired brittle selectors (DataTable dual table/card DOM, ResponsiveTabs visibility, Customer vs Branch picker, Create vs Quick create). No product bug required for green mocked E2E.

---

## Backups

| Phase | Path | Status |
|-------|------|--------|
| Pre-deploy (labeled) | `/opt/collection-backups/20260717T143722Z-stage71-predeploy/` | **Done** — `db.sql` (~24.9MB) |
| Pre-deploy (script) | `/opt/collection-backups/20260717T144005Z` | **Done** — `./scripts/backup.sh` |
| Post-deploy (labeled) | `/opt/collection-backups/20260717T143722Z-stage71-postdeploy/` | **Done** — `db.sql` (~24.9MB) |
| Post-deploy (script) | `/opt/collection-backups/20260717T144253Z` | **Done** — `./scripts/backup.sh` |
| Snapshots | `/opt/collection-backups/snapshots/20260717T143722Z-stage71-*-counts.txt` | pre/post financial+ops counts |

---

## Migrations & seeders

Stage 7 migrations already at batch **14** (`2026_07_17_161000_create_stage7_ticketing_tables`). Deploy re-ran migrate --force (no new migrations).

| Seeder | Result |
|--------|--------|
| `Stage7OrgSeeder` | **SEED_OK** |
| `Stage7SlaPolicySeeder` | **SEED_OK** |
| `Stage7TicketTypeSeeder` | **SEED_OK** |
| `Stage7TaskTemplateSeeder` | **SEED_OK** |
| `Stage7EscalationRuleSeeder` | **SEED_OK** |
| `RolePermissionSeeder` | **SEED_OK** (also via `deploy.sh`) |

---

## Financial / ops counts (production)

| Metric | Pre | Post (after STAGE71 TEST) | Match |
|--------|-----|---------------------------|-------|
| payments | 3 | 3 | **MATCH** |
| cash_handover_requests | 1 | 1 | **MATCH** |
| collector_wallets | 3 | 3 | **MATCH** |
| branch_cashboxes | 2 | 2 | **MATCH** |
| payment_reversals | 3 | 3 | **MATCH** |
| whatsapp_connections | 1 | 1 | **MATCH** |
| tickets | 1 | 2 | +1 STAGE71 TEST |
| tasks | 1 | 2 | +1 STAGE71 TEST |
| installations | 1 | 2 | +1 STAGE71 TEST |

---

## STAGE71 TEST records

| Entity | ID | Number | Label |
|--------|----|--------|-------|
| Ticket | 3 | `S71-TEST-1784299369` | STAGE71 TEST ticket - DO NOT USE |
| Task | 2 | `S71-TASK-1784299404` | STAGE71 TEST task - DO NOT USE |
| Installation | 2 | `S71-INST-1784299404` | STAGE71 TEST installation - DO NOT USE |

Prior STAGE7 TEST rows retained (ticket 2, task 1, installation 1).

---

## Production verification checklist

- [x] Pre-deploy backup + financial counts snapshot
- [x] Code sync via `scripts/sync-to-vps.sh` + `./scripts/deploy.sh`
- [x] Stage 7 seeders re-run (all SEED_OK)
- [x] Financial counts unchanged; ops counts +1 each for STAGE71 TEST
- [x] Docker compose healthy (backend/frontend/postgres/redis healthy; nginx/queue/scheduler up)
- [x] Health: `GET https://finance.mns.af/api/v1/health` → **200** with `deployment.commit_sha=bfed7b9…`
- [x] Login pages: `/en/login` → **200**; Super Admin not reinstalled
- [x] `.deployed-sha` written (repo root + baked into backend image at `/var/www/html/.deployed-sha`)
- [x] DeploymentInfo via tinker matches SHA / backend_version **7.1** / migration_batch **14**
- [x] STAGE71 TEST records created (DO NOT USE)
- [x] Post-deploy backup
- [x] SSH key **KEY_KEPT** (never deleted)
- [x] Stage 8 **not** started; PR **#12 not merged**

### DeploymentInfo (tinker)

```json
{
  "app_name": "MNS Collection",
  "commit_sha": "bfed7b9202d83efb56620ce36d0c4b49897fe428",
  "build_timestamp": "2026-07-17T14:40:09+00:00",
  "backend_version": "7.1",
  "migration_batch": 14,
  "latest_migration": "2026_07_17_161000_create_stage7_ticketing_tables",
  "php_version": "8.4.23",
  "laravel_version": "12.64.0"
}
```

---

## Notes

- Public `https://finance.mns.af/up` currently serves the Next frontend HTML (200); use `/api/v1/health` for backend+DB+redis+DeploymentInfo.
- `GET /api/v1/system/version` remains auth-gated (401 unauthenticated); health endpoint embeds deployment summary for ops probes.
- Authenticated live role-matrix UI walkthrough still pending (admin password not available to agent).

---

## References

- `docs/STAGE_7_1_FUNCTIONAL_AUDIT.md` — defect inventory + fix status
- `docs/STAGE_7_DELIVERY_REPORT.md` — Stage 7 baseline deploy
- `docs/STAGE_7_TICKETING_TASKS.md`, workflow docs, OpenAPI
