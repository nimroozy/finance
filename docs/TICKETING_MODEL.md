# Ticketing Model (Stage 7)

## Separation of concerns

| Concept | Purpose |
|---------|---------|
| **Ticket** | Customer or internal issue/request with SLA, status, resolution |
| **Task** | Departmental work unit that executes parts of a ticket, installation, or standalone work |
| **Installation (queue)** | Operational new-install pipeline status (Stage 7); CRM/leads/quotes remain Stage 8 |

One ticket **may** create multiple tasks (Sales, Technical, NOC, Finance, Inventory, Management, Support).

Do **not** treat tickets and tasks as the same entity.

## Ticket types

Configured in `ticket_types` (seeded by `Stage7TicketTypeSeeder`). Common patterns:

- Customer support / outage / billing inquiry
- Internal operational ticket
- WhatsApp-created ticket (`source=whatsapp`)

## Ticket attributes

- Branch (required), customer (optional for internal)
- `type_code`, category, subject, description
- **Priority:** `low`, `normal`, `medium`, `high`, `urgent`, `critical`
- **Severity:** `individual_customer`, `multiple_customers`, `neighborhood`, `tower`, `branch`, `network_wide`
- **Source:** `manual`, `whatsapp`, `phone_call`, `customer_portal`, `sales`, `finance`, `noc`, `technical`, `monitoring`, `installation`, `radius`, `email_future`, `api`, `internal`
- Assignment: department, team, primary assignee, watchers
- SLA: policy, `response_due_at`, `resolution_due_at`, `first_response_at`, sla state
- Related refs: tower/site, radius account, invoice, payment, installation, WhatsApp conversation
- Resolution notes, customer confirmation, tags, attachments, work logs

### Ticket statuses

`new`, `triaged`, `assigned`, `in_progress`, `waiting_customer`, `waiting_finance`, `waiting_noc`, `waiting_technical`, `waiting_equipment`, `scheduled`, `escalated`, `resolved`, `verification_pending`, `closed`, `cancelled`, `reopened`

See [TICKET_WORKFLOW.md](TICKET_WORKFLOW.md).

## Task attributes

- **Type:** `field` | `office`
- **Priority:** `critical`, `high`, `medium`, `normal`, `low`
- Assignees: branch, department, team, user
- Links: `ticket_id`, `installation_id`, `customer_id`, `parent_task_id`
- Checklist / required evidence JSON, GPS, schedule windows
- Dependencies via `depends_on` on create

### Task statuses

`pending`, `offered`, `accepted`, `rejected`, `scheduled`, `travelling`, `arrived`, `in_progress`, `blocked`, `waiting`, `completed`, `verification_pending`, `approved`, `cancelled`, `failed`

See [TASK_WORKFLOW.md](TASK_WORKFLOW.md) and [FIELD_MOBILE_WORKFLOW.md](FIELD_MOBILE_WORKFLOW.md).

## Installation queue statuses (Stage 7)

`request_received`, `customer_verification`, `coverage_check`, `site_survey_pending`, `finance_review`, `equipment_waiting`, `scheduled`, `assigned`, `travelling`, `installing`, `noc_activation_pending`, `customer_confirmation`, `completed`, `delayed`, `cancelled`

See [INSTALLATION_QUEUE.md](INSTALLATION_QUEUE.md). Full CRM remains Stage 8.

## SLA & escalation

- Dual clocks: first response + resolution
- Pause on configured waiting statuses (default: `waiting_customer`, `waiting_equipment`, `scheduled`)
- Escalation rules: time-based / priority / department; acknowledge API
- Escalation emits notifications via Notifications domain (not direct WhatsApp API)

See [SLA_ESCALATION.md](SLA_ESCALATION.md).

## WhatsApp interaction (Stage 7)

Stage 6 stores inbound messages. Stage 7 adds intake suggestions, optional auto-create, and staff bot actions with signed one-time tokens.

See [WHATSAPP_TICKET_INTAKE.md](WHATSAPP_TICKET_INTAKE.md).

## Branch isolation

Staff see tickets/tasks/installations for accessible branches only. Central finance / super admin may see cross-branch with permission.

## Events

- Tickets: `TicketOpened`, `TicketAssigned`, `TicketStatusChanged`, `TicketEscalated`, `TicketResolved`, `TicketClosed`, `TicketReopened`
- Tasks: `TaskCreated`, `TaskOffered`, `TaskAccepted`, `TaskRejected`, `TaskStarted`, `TaskCompleted`, `TaskBlocked`, `TaskVerified`, `TaskReassigned`
- Installations: `InstallationStatusChanged`

## Numbering

Configurable sequences: `TKT`, `TSK`, `INS` (see `config/ticketing.php`).
