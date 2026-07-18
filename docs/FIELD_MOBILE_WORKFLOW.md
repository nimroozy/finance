# Field Mobile Workflow

How technicians use Stage 7 APIs (and WhatsApp staff actions) on mobile for field tasks.

## Daily loop

1. Open **My tasks** — `GET /tasks/my` (or WhatsApp “My tasks”).
2. **Accept** offered work — `POST /tasks/{id}/accept`.
3. **Start travel** when leaving base — `POST /tasks/{id}/start-travel`.
4. **Arrive** on site — `POST /tasks/{id}/arrive` (optionally capture GPS on the task record via prior update / completion notes).
5. **Start** work — `POST /tasks/{id}/start`.
6. Capture **evidence** — `POST /attachments` with `attachable_type=task` (photos, PDFs).
7. Log time/notes — `POST /work-logs` with `task_id` (and `ticket_id` when linked).
8. **Complete** — `POST /tasks/{id}/complete`.
9. Supervisor **verifies** — `POST /tasks/{id}/verify` (office / lead tech).

Reject with reason if the offer is wrong: `POST /tasks/{id}/reject`.

Block when site conditions prevent progress: `POST /tasks/{id}/block` `{ reason }`.

## Status checklist for field tasks

| Step | Status |
|------|--------|
| Offered to tech | `offered` |
| Tech accepted | `accepted` |
| En route | `travelling` |
| On site | `arrived` |
| Working | `in_progress` |
| Done (pending QA) | `completed` → `verification_pending` |
| QA done | `approved` |

Office-only tasks skip travel/arrive; see [TASK_WORKFLOW.md](TASK_WORKFLOW.md).

## Linked ticket / installation

Field tasks often include `ticket_id` and/or `installation_id`:

- Open customer context via app deep link (WhatsApp “Open customer”) — not by dumping balances into chat.
- Installation queue status is updated by office/NOC via `POST /installations/{id}/transition`; techs primarily drive **tasks**.

## WhatsApp shortcuts

Staff bot actions for field use:

- My tasks / My tickets
- Start task / Complete task (via **signed one-time token** — see [WHATSAPP_TICKET_INTAKE.md](WHATSAPP_TICKET_INTAKE.md))

Do not treat unsigned chat text as authorization for complete/start.

## Offline / practical constraints

- Attachments max size and MIME list come from `ticketing.attachments` (video optional via `TICKETING_ALLOW_VIDEO`).
- Download links are signed and short-lived.
- Branch isolation still applies on mobile tokens — technicians only see their branches’ tasks.

## Permissions needed on tech roles

Minimum: `tasks.view`, `tasks.accept`, `tasks.complete`, `attachments.upload`, plus `tickets.view` when opening linked tickets.
