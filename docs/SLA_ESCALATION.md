# SLA & Escalation

How Stage 7 first-response and resolution clocks work, when they pause, and how escalations fire.

## Clocks

On ticket create, `SlaClockService::applyOnCreate`:

1. Resolves an active `SlaPolicy` matching branch / ticket type / priority (most specific first; null wildcards allowed).
2. Sets `response_due_at` and `resolution_due_at` from policy minutes, or config defaults:
   - `ticketing.sla.default_first_response_minutes` (60)
   - `ticketing.sla.default_resolution_minutes` (1440)
3. Creates `TicketSlaState` with `response_state` / `resolution_state` = `running` and remaining seconds snapshots.

### States on `ticket_sla_states`

| Field | Values (typical) |
|-------|------------------|
| `response_state` | `running`, `paused`, `met`, `breached` |
| `resolution_state` | `running`, `paused`, `breached` |
| `paused_at` | Set while clocks are paused |
| `pause_total_seconds` | Accumulated pause |
| `breached_at` | First breach timestamp |

First response is **met** when `tickets.first_response_at` is set (on meaningful staff activity / transition rules).

## Pause rules

On every ticket status change, `SlaClockService::onStatusChange`:

- Entering a pause status → `pause()` (both clocks pause; `paused_at` set)
- Leaving a pause status → `resume()` (add elapsed pause to due timestamps; clear `paused_at`)

### Default pause statuses

From `config/ticketing.php` (overridable per `SlaPolicy.pause_statuses`):

- `waiting_customer`
- `waiting_equipment`
- `scheduled`

Policies may also pause on other waiting statuses (`waiting_finance`, `waiting_noc`, `waiting_technical`) when configured on the policy row.

While paused, breach evaluation **skips** the ticket.

## Breach evaluation

`EvaluateSlaBreachesJob` → `SlaClockService::evaluateBreaches`:

- Considers open tickets (not `resolved` / `closed` / `cancelled` / `verification_pending`)
- **Response breach:** no `first_response_at` and `response_due_at` < now
- **Resolution breach:** `resolution_due_at` < now
- Writes `SlaBreachEvent`, audits `ticket.sla_breached`, updates sla state

Remaining times helper: `remainingTimes()` accounts for current pause elongation.

## Escalations

### Rules

Managed via `GET/POST/PUT /escalation-rules` (`escalations.manage` or `sla.manage`).

Rules define when to open an escalation (minutes after due, level, notify role/department, ticket filters).

Default config seeds illustrate:

- `first_response_overdue` (L1)
- `resolution_overdue` (L2, e.g. 60 minutes after due)

### Runtime

`EvaluateEscalationsJob` → `EscalationService::evaluate` creates `Escalation` rows and may transition the ticket to `escalated` (emits `TicketEscalated`).

API:

| Endpoint | Purpose |
|----------|---------|
| `GET /escalations` | List (`escalations.view`) |
| `POST /escalations` | Manual escalate (`escalations.manage`) |
| `POST /escalations/{id}/acknowledge` | Acknowledge |

### Notifications

Escalation and SLA events go through the **Notifications** orchestrator (in-app / WhatsApp templates). Ticketing must not call Meta Graph inside the escalation transaction.

## SLA policy API

| Endpoint | Permission |
|----------|------------|
| `GET /sla-policies` | `sla.manage` or `tickets.view` |
| `POST /sla-policies` | `sla.manage` |
| `PUT /sla-policies/{id}` | `sla.manage` |

Policy fields typically include: branch (nullable = global), ticket type code, priority, response/resolution minutes, pause statuses, active flag.

## Operational dashboards

- Support dashboard: open tickets, SLA at risk / breached
- NOC dashboard: escalations and network-severity tickets
- Manager dashboard: cross-branch operational rollup (permission gated)

See `GET /operations/dashboard/*`.
