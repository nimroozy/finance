# Stage 7 Delivery Report

**Date (UTC):** 2026-07-17  
**Branch:** `cursor/stage-7-ticketing-tasks`  
**SHA:** `bb9e408f20a9e730e0b09731b08a007f6e933489`  
**Base:** `cursor/stage-6-whatsapp` @ `af147c44870a07952fc8300d3eaf0a6ebca4c600`  
**Draft PR:** https://github.com/nimroozy/finance/pull/11  
**Do not merge:** prior stacked PRs until reviewed. **Stage 8 CRM not started.**

---

## Summary

Stage 7 multi-branch ticketing and task management foundation delivered on branch:

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

---

## Backups

| Phase | Path | Status |
|-------|------|--------|
| Pre-deploy | `/opt/collection-backups/<UTC>-stage7-predeploy/` | **Pending** — `/opt/collection-system` not available in this agent environment |
| Post-deploy | `/opt/collection-backups/<UTC>-stage7-postdeploy/` | **Pending** — requires production host |

---

## Migrations & seeders

| Artifact | Notes |
|----------|--------|
| `2026_07_17_160000_create_stage7_org_tables.php` | Departments, teams, membership pivots |
| `2026_07_17_161000_create_stage7_ticketing_tables.php` | Tickets, tasks, SLA, escalations, installations, attachments, work logs |
| Seeders | `Stage7OrgSeeder`, `Stage7TicketTypeSeeder`, `Stage7SlaPolicySeeder`, `Stage7EscalationRuleSeeder`, `Stage7TaskTemplateSeeder` |

### Tables created

| Migration | Tables |
|-----------|--------|
| Org | `departments`, `teams`, `department_user`, `team_user` |
| Ticketing | `ticket_types`, `sla_policies`, `ticket_sequences`, `task_sequences`, `installation_sequences`, `tickets`, `ticket_watchers`, `ticket_status_transitions`, `ticket_sla_states`, `sla_breach_events`, `major_incidents`, `major_incident_ticket`, `escalation_rules`, `escalations`, `task_templates`, `tasks`, `task_dependencies`, `task_status_transitions`, `work_logs`, `work_log_amendments`, `operational_attachments`, `installations`, `ticket_intake_suggestions`, `staff_action_tokens` |

### Permissions summary

Stage 7 permissions are defined in `RolePermissionSeeder` (`tickets.*`, `tasks.*`, `sla.manage`, `installations.*`, `attachments.*`, `reports.support|technical|management`, `whatsapp.ticket_intake`, `whatsapp.staff_actions`, `departments.manage`, `ticket_types.manage`, `task_templates.manage`). Super Admin receives all; Central Finance / Branch Manager / Collector / Auditor get scoped subsets (view-heavy for auditor; field task accept/complete for collector).

### Ticket types / SLA seeded

`Stage7SlaPolicySeeder` seeds default Critical/High/Normal/Medium/Low SLA policies (Asia/Kabul business hours). `Stage7TicketTypeSeeder` seeds 22 ticket types (e.g. connectivity_outage, slow_speed, installation_request, billing_inquiry, major_incident, whatsapp_general) linked to matching default SLA by priority.

**Production migrate/seed:** **Pending** — run on production host under `/opt/collection-system` after backup.

---

## Local / CI verification

| Suite | Result |
|-------|--------|
| Backend `php artisan test --filter=Stage7` | **17 passed** (60 assertions) |
| Frontend lint/build | **Passed** |
| Playwright `e2e/stage7.spec.ts` | **12/12 passed** |
| OpenAPI | Updated to **v1.9.0** |

---

## Production verification

**Status: PENDING** — `/opt/collection-system` is not available in this cloud agent environment. Complete on the production host:

- [ ] Pre-deploy backup + financial counts snapshot
- [ ] Migrate + seed Stage 7
- [ ] `STAGE7 TEST` labeled walkthrough (tickets, tasks, installation queue, WhatsApp intake intents only — no live Meta unless approved)
- [ ] Financial counts before/after unchanged (payments, handovers, wallets, cashboxes, custody)
- [ ] WhatsApp foundation health (Stage 6 connection/webhook)
- [ ] Public ports **22/80/443** only; containers healthy
- [ ] Post-deploy backup
- [ ] Update this report with backup paths, count tables, and walkthrough evidence

---

## Financial counts (production)

| Metric | Pre | Post | Notes |
|--------|-----|------|-------|
| Payments | _pending_ | _pending_ | Must remain unchanged |
| Handovers | _pending_ | _pending_ | Must remain unchanged |
| Collector wallets | _pending_ | _pending_ | Must remain unchanged |
| Cashboxes | _pending_ | _pending_ | Must remain unchanged |

---

## WhatsApp / ports (production)

| Check | Status |
|-------|--------|
| Stage 6 WhatsApp foundation health | **Pending** (production host) |
| Ticket intake via async orchestration (no Meta inside TX) | Implemented in code; live Meta optional |
| Public listeners 22/80/443 | **Pending** (production host) |

---

## Delivered surfaces

- Backend: org, tickets, tasks, SLA/escalations, installation queue, WhatsApp intake, dashboards/search/timeline APIs
- Frontend: tickets/tasks/installations pages, role dashboards, settings, EN/FA, feature flags enabled for Stage 7 modules
- Docs: `STAGE_7_TICKETING_TASKS.md`, workflow/SLA/intake/queue/mobile docs, roadmap/architecture/boundary updates

---

## Known issues / deferred

- Production deploy, migrate/seed, `STAGE7 TEST` walkthrough, and financial/port checks remain for the production host
- Stage 8 CRM (leads, opportunities, quotations, commercial installation workflow) not started
- Live Meta WhatsApp end-to-end only when credentials approved on production
