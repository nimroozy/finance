# Ticketing Model (Stage 7)

## Separation of concerns

| Concept | Purpose |
|---------|---------|
| **Ticket** | Customer or internal issue/request with SLA, status, resolution |
| **Task** | Departmental work unit that executes parts of a ticket or other workflows |

One ticket **may** create multiple tasks (Sales, Technical, NOC, Finance, Inventory, Management).

Do **not** treat tickets and tasks as the same entity.

## Ticket types

- Customer ticket
- Internal ticket
- WhatsApp-created ticket (from inbound message + classification rules)

## Ticket attributes

- Branch (required)
- Customer (optional for internal)
- Category, subcategory
- Priority
- Channel (`whatsapp`, `phone`, `walk_in`, `internal`, `system`)
- Reporter / assignee
- SLA policy, due_at, breached_at
- Status: `open`, `in_progress`, `waiting_customer`, `escalated`, `resolved`, `closed`, `cancelled`
- Resolution notes, customer confirmation
- Attachments, work logs

## SLA & escalation

- SLA clocks pause on configured waiting statuses.
- Escalation rules: time-based, priority-based, department-based.
- Escalation emits notifications via Notifications domain (not direct WhatsApp API).

## Tasks

Assignable to:

- Branch
- Department (Sales, Finance, Technical, NOC, Inventory, Management)
- Team
- Individual user

Support:

- Dependencies (task B blocked until A completes)
- Start / complete / reassign
- WhatsApp staff-bot notifications (Stage 7)
- Secure deep links for sensitive actions

## WhatsApp interaction (Stage 7)

Stage 6 only stores inbound messages.

Stage 7 adds:

- Rule: inbound → create/link ticket
- Staff bot: `My tickets`, `My tasks`, `Start task`, `Complete task`, `Escalate`, `Open customer`
- Sensitive actions: signed interactive payloads or app login links
- No passwords; minimize financial detail

## Branch isolation

Staff see tickets/tasks for accessible branches only. Central finance / super admin may see cross-branch with permission.

## Events

- `TicketOpened`, `TicketAssigned`, `TicketEscalated`, `TicketResolved`, `TicketClosed`
- `TaskCreated`, `TaskAssigned`, `TaskStarted`, `TaskCompleted`, `TaskBlocked`

## Out of Stage 6

Full support inbox, bot commands, and ticket CRUD are **not** Stage 6 deliverables.
