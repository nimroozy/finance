# Stage 7 Delivery Report

**Date (UTC):** 2026-07-17  
**Branch:** `cursor/stage-7-ticketing-tasks`  
**SHA deployed:** `aa5d1a5a48283e1e2d5d14c9b49228a1ce344c68`  
**VPS:** `root@209.38.194.184` (`/opt/collection-system`)  
**Deploy stamp:** `20260717T133627Z`  
**Base:** `cursor/stage-6-whatsapp` @ `af147c44870a07952fc8300d3eaf0a6ebca4c600`  
**Draft PR:** https://github.com/nimroozy/finance/pull/11  
**Do not merge:** prior stacked PRs until reviewed. **Stage 8 CRM not started.**

---

## Summary

Stage 7 multi-branch ticketing and task management foundation delivered and **deployed to production VPS**:

- Tickets separate from tasks (one ticket may spawn many departmental tasks)
- Departments/teams, SLA clocks and escalations, work logs, attachments, major incidents
- Operational installation queue only (no Stage 8 CRM/leads/quotations)
- WhatsApp ticket intake + staff/customer bot hooks via async notification orchestration
- Support/technical/NOC/finance/management dashboards, search, timeline, settings pages
- EN/FA UI, OpenAPI **v1.9.0**, Stage 7 docs

---

## Explicit non-goals (unchanged)

- Does not merge previous PRs
- Does not start Stage 8 CRM
- Does not modify payment, wallet, handover, cashbox, custody reversal, or Zoho reconciliation calculations
- Does not send live WhatsApp/Meta messages

---

## Backups

| Phase | Path | Status |
|-------|------|--------|
| Pre-deploy (labeled) | `/opt/collection-backups/20260717T133627Z-stage7-predeploy/` | **Done** — `db.sql` (~24MB), `config.tgz`, pre-counts |
| Pre-deploy (script) | `/opt/collection-backups/20260717T133947Z` | **Done** — `./scripts/backup.sh` |
| Post-deploy (labeled) | `/opt/collection-backups/20260717T133627Z-stage7-postdeploy/` | **Done** — `db.sql`, post-counts |
| Post-deploy (script) | `/opt/collection-backups/20260717T134003Z` | **Done** — `./scripts/backup.sh` |
| Snapshots | `/opt/collection-backups/snapshots/20260717T133627Z-stage7-*.txt` | pre/post counts + table list |

---

## Migrations & seeders

| Artifact | Notes |
|----------|--------|
| `2026_07_17_160000_create_stage7_org_tables.php` | **Ran** `[14]` |
| `2026_07_17_161000_create_stage7_ticketing_tables.php` | **Ran** `[14]` |
| Seeders | All **SEED_OK** |

### Seed results (production)

| Seeder | Result |
|--------|--------|
| `Stage7OrgSeeder` | OK — departments=72, teams=27 |
| `Stage7SlaPolicySeeder` | OK — sla_policies=5 |
| `Stage7TicketTypeSeeder` | OK — ticket_types=22 |
| `Stage7TaskTemplateSeeder` | OK — task_templates=2 |
| `Stage7EscalationRuleSeeder` | OK — escalation_rules=5 |
| `RolePermissionSeeder` | OK |

### Tables confirmed present

`departments`, `teams`, `tickets`, `tasks`, `ticket_types`, `sla_policies`, `escalation_rules`, `escalations`, `installations`, `task_templates`, plus related SLA/status/sequence/attachment tables.

---

## Financial counts (production)

| Metric | Pre | Post | Match |
|--------|-----|------|-------|
| payments | 3 | 3 | MATCH |
| cash_handover_requests | 1 | 1 | MATCH |
| cash_handovers | MISSING (N/A; table is `cash_handover_requests`) | MISSING | MATCH |
| collector_wallets | 3 | 3 | MATCH |
| branch_cashboxes | 2 | 2 | MATCH |
| payment_reversals | 3 | 3 | MATCH |
| whatsapp_connections | 1 | 1 | MATCH |

---

## Production verification checklist

- [x] Pre-deploy backup + financial counts snapshot
- [x] Code sync via `scripts/sync-to-vps.sh` + `./scripts/deploy.sh`
- [x] Migrate + seed Stage 7
- [x] Financial counts before/after unchanged
- [x] Docker compose healthy (backend/frontend/postgres/redis healthy; nginx/queue/scheduler up)
- [x] Health: `https://finance.mns.af/up` → **200**; local 443 → 200
- [x] Admin login unchanged (`/en/login` + `/fa/login` → 200; Super Admin not reinstalled)
- [x] WhatsApp foundation present (`whatsapp_connections=1`); no live Meta messages sent
- [x] Public ports **22/80/443** (ufw allow OpenSSH/80/443; ss confirms listeners)
- [x] `STAGE7 TEST` records created (ticket #2, task #1, installation #1) labeled **DO NOT USE**
- [x] Post-deploy backup
- [x] Stage 8 **not** started

### STAGE7 TEST records

| Entity | ID | Label |
|--------|----|-------|
| Ticket | 2 (`S7-TEST-1784295629`) | STAGE7 TEST ticket - DO NOT USE |
| Task | 1 | STAGE7 TEST task - DO NOT USE |
| Installation | 1 | STAGE7 TEST installation - DO NOT USE |

---

## Docker / ports / scheduler

| Check | Status |
|-------|--------|
| `docker compose ps` | backend, frontend, postgres, redis **healthy**; nginx, queue-worker, scheduler up |
| Public listeners | 22, 80, 443 |
| ufw | active — OpenSSH, 80/tcp, 443/tcp |
| Zoho / scheduler | `collection-system-scheduler-1` running (Laravel scheduler container); host cron includes `collection-backup`, certbot |

---

## Local / CI verification

| Suite | Result |
|-------|--------|
| Backend `php artisan test --filter=Stage7` | **17 passed** (60 assertions) |
| Frontend lint/build | **Passed** |
| Playwright `e2e/stage7.spec.ts` | **12/12 passed** |
| OpenAPI | Updated to **v1.9.0** |

---

## Delivered surfaces

- Backend: org, tickets, tasks, SLA/escalations, installation queue, WhatsApp intake, dashboards/search/timeline APIs
- Frontend: tickets/tasks/installations pages, role dashboards, settings, EN/FA, feature flags enabled for Stage 7 modules
- Docs: `STAGE_7_TICKETING_TASKS.md`, workflow/SLA/intake/queue/mobile docs, roadmap/architecture/boundary updates

---

## Known issues / deferred

- Initial remote heredoc lost trailing commands because `docker compose exec` consumed stdin; fixed by re-running migrate/seed/verify with stdin redirected to `/dev/null`. Migrations had already been applied by backend entrypoint/deploy; seeders were re-run successfully.
- True pre-sync Stage 7 table absence confirmed via `\dt` (119 relations, no tickets/tasks); financial pre-counts file was written during fixup after migrate but before seed — financial totals unchanged throughout.
- WhatsAppConnection tinker one-liner hit a PHP parse/escaping issue; SQL count confirmed `whatsapp_connections=1`.
- Stage 8 CRM not started
- Live Meta WhatsApp end-to-end only when credentials approved
