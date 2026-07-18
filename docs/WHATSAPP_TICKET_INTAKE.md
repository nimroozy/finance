# WhatsApp Ticket Intake

Stage 7 rules for turning inbound WhatsApp messages into tickets, plus staff-bot security.

## Boundary with Stage 6

| Stage 6 (WhatsApp domain) | Stage 7 (Ticketing) |
|---------------------------|---------------------|
| Webhook verify/ingress, signature check | — |
| Persist inbound message + conversation | Listen after commit |
| Emit inbound event | Create intake suggestion / ticket |
| Templates & outbound delivery | Notify via orchestrator only |

**Forbidden:** creating tickets inside the Meta webhook DB transaction. Flow is:

```
Webhook → store inbound → commit
  → HandleInboundMessageForTicketing (queued after commit)
    → TicketIntakeService
```

## Intake suggestions

Inbound messages that match intake rules produce `TicketIntakeSuggestion` rows:

| Status | Meaning |
|--------|---------|
| `pending` | Awaiting staff action |
| `ticket_created` | Ticket opened from suggestion |
| `dismissed` | Staff dismissed |
| `appended` | Linked/appended to an existing open ticket |

### Auto-create

`TICKETING_AUTO_CREATE` / `ticketing.intake.auto_create` (default `false`):

- `false` — staff reviews `GET /ticket-intake` then `POST /ticket-intake/{id}/create-ticket`
- `true` — service may create the ticket immediately from the suggestion

Defaults for new tickets: priority/category from `ticketing.intake.*`.

If an open ticket already exists for the same customer/conversation, the intake service prefers append over duplicate create.

### API

| Endpoint | Permission |
|----------|------------|
| `GET /ticket-intake` | `whatsapp.ticket_intake` or `tickets.view` |
| `POST /ticket-intake/{id}/create-ticket` | `whatsapp.ticket_intake` or `tickets.create` |
| `POST /ticket-intake/{id}/dismiss` | `whatsapp.ticket_intake` |

Created tickets use `source=whatsapp` and may link `whatsapp_conversation_id`.

## Staff bot

`StaffBotService` interactive actions:

- My tasks
- My tickets
- Start task
- Complete task
- Escalate
- Open customer

List helpers use assignee filters (`GET`-style data for bot replies). Mutating actions must not run from unsigned free-text alone.

## Security of staff actions

Sensitive WhatsApp actions use **one-time staff action tokens**:

1. Issue `StaffActionToken` (hash stored; plain token returned once). TTL: `ticketing.staff_action_token_ttl_minutes` (default 60).
2. Send a **temporary signed URL** (`URL::temporarySignedRoute`) or signed interactive payload referencing that token.
3. On redeem: validate hash, expiry, unused, and that `token.user_id === actor.id`.
4. Execute allowed action (`start_task`, `complete_task`, `accept_task`, `assign_ticket`, …), mark `used_at`, audit.

Rules:

- Never send passwords or long-lived API tokens on WhatsApp.
- Minimize financial detail in bot replies (receipt amounts only when a dedicated receipt template/rule already allows it).
- Prefer deep links into the app for open-customer / complex workflows.
- All mutations remain branch-scoped and permission-checked in the service layer.

## Notifications

Ticket/task events emit business notification intents. WhatsApp delivery stays in the Notifications → WhatsApp job path from Stage 6.
