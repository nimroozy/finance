# Stage 7 — Ticketing, Tasks & Installation Queue

Practical overview of the Stage 7 operations domains delivered on this branch.

## Objective

Give branch staff a unified place to:

- Open and work **customer / internal tickets** with SLA and escalation
- Execute **departmental tasks** (field and office) separately from tickets
- Run a lightweight **installation queue** (operational pipeline only)
- Accept **WhatsApp inbound → ticket intake** without embedding support desk logic in the webhook transaction

Stage 6 remains the WhatsApp Cloud API + notification foundation. Stage 7 consumes inbound events and staff-bot actions on top of that foundation.

## Domains delivered

| Domain | Owns |
|--------|------|
| **Tickets** | Ticket CRUD, types, watchers, status transitions, resolve/close/reopen, SLA clocks, escalations, work logs, attachments |
| **Tasks** | Task CRUD, offer/accept/reject, field travel flow, complete/verify, dependencies, templates, reassign/cancel |
| **Installations (queue)** | Installation request numbers, status pipeline, optional task-template expansion |
| **Operations UX APIs** | Support/technical/NOC/finance/manager dashboards, cross-entity search, ticket/task reports, customer timeline |
| **WhatsApp intake** | Intake suggestions from inbound messages; create or dismiss ticket suggestions |

Hard rule: **tickets ≠ tasks**. One ticket may spawn many departmental tasks.

## Related docs

| Doc | Topic |
|-----|--------|
| [TICKETING_MODEL.md](TICKETING_MODEL.md) | Entity model & enums |
| [TICKET_WORKFLOW.md](TICKET_WORKFLOW.md) | Ticket statuses & transitions |
| [TASK_WORKFLOW.md](TASK_WORKFLOW.md) | Field vs office tasks |
| [SLA_ESCALATION.md](SLA_ESCALATION.md) | Clocks, pause, escalations |
| [WHATSAPP_TICKET_INTAKE.md](WHATSAPP_TICKET_INTAKE.md) | Intake + staff bot security |
| [INSTALLATION_QUEUE.md](INSTALLATION_QUEUE.md) | Stage 7 queue vs Stage 8 CRM |
| [FIELD_MOBILE_WORKFLOW.md](FIELD_MOBILE_WORKFLOW.md) | Technician mobile actions |
| [openapi.yaml](openapi.yaml) | HTTP contract |

## Deployment notes

1. **Feature flag / config** — `TICKETING_ENABLED` (default `true`) in `config/ticketing.php`.
2. **Migrations & seeders** — run Stage 7 migrations; seed ticket types via `Stage7TicketTypeSeeder` (included from `DatabaseSeeder`).
3. **Permissions** — grant fine-grained `tickets.*`, `tasks.*`, `installations.*`, `sla.manage`, `escalations.*`, `attachments.upload`, `whatsapp.ticket_intake`, `task_templates.manage`, `ticket_types.manage` as needed.
4. **Queues** — SLA breach evaluation (`EvaluateSlaBreachesJob`) and escalation evaluation (`EvaluateEscalationsJob`) must run on a worker schedule.
5. **WhatsApp** — Stage 6 connection + webhook must be healthy; intake listens after inbound persistence (`HandleInboundMessageForTicketing`). Auto-create tickets only when `TICKETING_AUTO_CREATE=true`.
6. **Attachments** — stored on the configured disk (`ticketing.attachments.disk`); download uses signed URLs (TTL from config).
7. **Branch isolation** — tickets, tasks, and installations use `BelongsToBranchScope`. Client-supplied `branch_id` only narrows within the user’s allowed branches.

## What is included

- Full ticket lifecycle with richer statuses than early drafts (`new` → … → `reopened`)
- Multi-department tasks with dependencies and evidence hooks
- SLA first-response + resolution clocks with pause on waiting statuses
- Escalation rules and acknowledge flow
- WhatsApp intake suggestions + staff bot interactive actions with one-time signed tokens
- Installation **queue** statuses and transitions
- Operational dashboards, search, and reports for tickets/tasks
- Work logs and polymorphic attachments (ticket / task / installation)

## What is not included (deferred)

| Deferred item | Target stage |
|---------------|--------------|
| CRM leads, opportunities, follow-ups, lost reasons | **Stage 8** |
| Quotations, sales targets, sales ownership attribution | **Stage 8** |
| Full installation CRM (surveys as commercial objects, finance approval gates tied to Zoho, CPE ledger) | **Stage 8** |
| Inventory reservation / stock ledger | **Stage 9** |
| Radius activation commands | **Stage 10** |
| Unified executive reporting warehouse | **Stage 11** |
| Full contact-center inbox UX beyond Stage 6 inbox + Stage 7 intake | Later polish |

Do **not** treat Stage 7 installation queue as Stage 8 CRM delivery.

## API surface

All Stage 7 routes live under `/api/v1` inside the authenticated Sanctum group. See the Stage 7 section of `backend/routes/api.php` and [openapi.yaml](openapi.yaml).
