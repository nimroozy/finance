# Task Workflow

Field vs office flows, dependencies, evidence, and verification for Stage 7 tasks.

## Ticket vs task

| | Ticket | Task |
|--|--------|------|
| Purpose | Customer/internal issue with SLA | Executable work unit |
| Cardinality | One issue | Many tasks per ticket (or standalone / installation-linked) |
| Assignees | Primary assignee + department/team | User / department / team |

Never collapse tickets into tasks in UI or API design.

## Task types

| Type | Typical flow |
|------|----------------|
| `field` | Offer → accept → travel → arrive → start → complete → verify |
| `office` | Offer → accept → start → complete → verify (travel/arrive optional) |

## Statuses

`pending`, `offered`, `accepted`, `rejected`, `scheduled`, `travelling`, `arrived`, `in_progress`, `blocked`, `waiting`, `completed`, `verification_pending`, `approved`, `cancelled`, `failed`

Terminal: `approved`, `cancelled`, `failed`.

Statuses that satisfy dependency predecessors: `completed`, `verification_pending`, `approved`.

## Field flow (technician)

```
pending → offered → accepted
                 ↘ rejected → (re-offer / pending)
accepted → scheduled? → travelling → arrived → in_progress → completed
                                              ↘ blocked / waiting
completed → verification_pending → approved
```

API steps:

| Action | Endpoint |
|--------|----------|
| Offer to user | `POST /tasks/{id}/offer` `{ user_id, reason? }` |
| Accept | `POST /tasks/{id}/accept` |
| Reject | `POST /tasks/{id}/reject` `{ reason }` |
| Start travel | `POST /tasks/{id}/start-travel` |
| Arrive on site | `POST /tasks/{id}/arrive` |
| Start work | `POST /tasks/{id}/start` |
| Complete | `POST /tasks/{id}/complete` |
| Block | `POST /tasks/{id}/block` `{ reason }` |
| Verify / approve | `POST /tasks/{id}/verify` |
| Reassign | `POST /tasks/{id}/reassign` `{ user_id, reason }` |
| Cancel | `POST /tasks/{id}/cancel` `{ reason }` |

Personal queue: `GET /tasks/my`.

## Office flow

Same offer/accept model. Travel and arrive may be skipped: from `accepted` / `scheduled` / `pending` the workflow allows moving to `in_progress` via `start` when transitions permit.

Use `blocked` / `waiting` for cross-department holds (e.g. waiting on finance) instead of inventing parallel ticket statuses on the task.

## Dependencies

On create, pass `depends_on: [task_id, …]`.

- Task B cannot complete (and may be blocked from starting work) until predecessors are in a complete-for-dependency status.
- `TaskDependencyService` owns graph checks; cycles are rejected.

## Evidence & work logs

- Tasks may declare `required_evidence` / `checklist` JSON.
- Attach files with `POST /attachments` (`attachable_type=task`).
- Record time/notes with `POST /work-logs` (`task_id` and/or `ticket_id`).
- Completion notes stored on the task at complete.

## Templates

`GET/POST/PUT /task-templates` for reusable checklists and department defaults. Installations may expand a template into child tasks (`expand_template` on installation create).

## Permissions

| Permission | Actions |
|------------|---------|
| `tasks.view` | List / show / my |
| `tasks.create` | Create |
| `tasks.assign` | Offer |
| `tasks.accept` | Accept / reject |
| `tasks.complete` | Travel, arrive, start, complete, block |
| `tasks.verify` | Verify |
| `tasks.reassign` | Reassign |
| `tasks.cancel` | Cancel |

## Events

`TaskCreated`, `TaskOffered`, `TaskAccepted`, `TaskRejected`, `TaskStarted`, `TaskCompleted`, `TaskBlocked`, `TaskVerified`, `TaskReassigned` → notifications via orchestrator.
