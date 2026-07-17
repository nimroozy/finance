# Ticket Workflow

Statuses, transitions, assignment, resolve/close, and reopen for Stage 7 tickets.

## Statuses

| Status | Meaning |
|--------|---------|
| `new` | Just created; not yet triaged |
| `triaged` | Classified (type/priority/department) |
| `assigned` | Has a primary assignee (and/or department/team) |
| `in_progress` | Active work |
| `waiting_customer` | Blocked on customer response (SLA pause candidate) |
| `waiting_finance` | Waiting on finance |
| `waiting_noc` | Waiting on NOC |
| `waiting_technical` | Waiting on technical |
| `waiting_equipment` | Waiting on equipment (SLA pause candidate) |
| `scheduled` | Work scheduled for later (SLA pause candidate by default) |
| `escalated` | Escalation raised |
| `resolved` | Resolution recorded; may still need verification |
| `verification_pending` | Awaiting close verification / customer confirmation path |
| `closed` | Terminal success |
| `cancelled` | Terminal cancel |
| `reopened` | Reopened from resolved/closed/verification |

Waiting group: `waiting_customer`, `waiting_finance`, `waiting_noc`, `waiting_technical`, `waiting_equipment`.

## Allowed transitions

```
new → triaged | cancelled
triaged → assigned | in_progress | escalated | cancelled
assigned → in_progress | scheduled | escalated | cancelled | waiting_*
in_progress → waiting_* | scheduled | escalated | resolved | cancelled
scheduled → in_progress | assigned | escalated | cancelled | waiting_*
escalated → in_progress | assigned | resolved | cancelled | waiting_*
waiting_* → in_progress | assigned | escalated | resolved | cancelled
resolved → verification_pending | closed | reopened
verification_pending → closed | in_progress | reopened
closed → reopened
reopened → triaged | assigned | in_progress
cancelled → (none)
```

Enforced by `Ticket::allowedTransitions()` and `TicketStatusTransitionService`.

## Create

`POST /tickets` requires `branch_id`, `type_code`, `subject`. Optional: source, priority, severity, customer fields, department/team/assignee, WhatsApp conversation link, tags.

Initial status is always `new`. SLA clocks start on create (`SlaClockService::applyOnCreate`).

## Assignment

`POST /tickets/{id}/assign` with `{ user_id, reason? }`.

- From `new`: moves through triage then to `assigned`
- From `triaged`: transitions to `assigned`
- Sets `primary_assignee_id`
- Emits `TicketAssigned`

Watchers: `POST /tickets/{id}/watchers` with `{ user_id, action: add|remove }`.

## Generic transition

`POST /tickets/{id}/transition` with `{ status, reason?, comment? }` for any allowed status change (permission: `tickets.update`).

Prefer dedicated endpoints for resolve / close / reopen when those semantics apply.

## Resolve

`POST /tickets/{id}/resolve` with `{ resolution_summary?, customer_confirmation? }`.

- Transitions to `resolved`
- Stores resolution summary and optional customer confirmation flag
- Emits `TicketResolved`

## Close

`POST /tickets/{id}/close` with `{ reason? }`.

- From `resolved` (or via `verification_pending` when required) → `closed`
- Emits `TicketClosed`

## Reopen

`POST /tickets/{id}/reopen` with `{ reason? }`.

- Allowed from `resolved`, `verification_pending`, or `closed`
- Status becomes `reopened`; `reopened_count` increments
- Emits `TicketReopened`
- SLA resume/re-evaluation follows status-change rules (see [SLA_ESCALATION.md](SLA_ESCALATION.md))

## Branch & permissions

| Permission | Actions |
|------------|---------|
| `tickets.view` / `tickets.view_all_branch` / `tickets.view_all` | List/show |
| `tickets.create` | Create |
| `tickets.update` | Update, transition, watchers |
| `tickets.assign` | Assign |
| `tickets.resolve` | Resolve |
| `tickets.close` | Close |
| `tickets.reopen` | Reopen |

Staff without `tickets.view_all` only see tickets for their branch IDs.

## Events

`TicketOpened`, `TicketAssigned`, `TicketStatusChanged`, `TicketEscalated`, `TicketResolved`, `TicketClosed`, `TicketReopened` → notification listener (orchestrator), never Meta Graph calls inside the ticket DB transaction.
