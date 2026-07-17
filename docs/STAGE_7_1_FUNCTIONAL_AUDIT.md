# Stage 7.1 — Functional Audit (UI ↔ API gaps)

**Date (UTC):** 2026-07-17  
**Branch:** `cursor/stage-7-1-ui-functional-repair`  
**Scope:** Code-level review of Stage 7 ticketing / tasks / installations / ops dashboards / WhatsApp intake UI against backend APIs.  
**Method:** Static review of frontend pages + `lib/*` clients vs controllers/resources/routes; live public probes on production VPS.  
**Role-based authenticated live tests:** **pending** (no login credentials available to this agent).

> No separate Stage 7.1 brief file exists in-repo. Expected functional bar is inferred from Stage 7 docs (`docs/STAGE_7_TICKETING_TASKS.md`, workflow docs, OpenAPI), the delivered UI surface, and this audit checklist.

---

## Live checks (production)

| Check | Result |
|-------|--------|
| Host | `root@209.38.194.184` (`/opt/collection-system`) |
| `https://finance.mns.af/up` | **200** — “Application up” |
| `https://finance.mns.af/en/login`, `/fa/login` | **200** |
| `GET /api/v1/tickets` (unauthenticated) | **401** (expected) |
| Docker compose | backend/frontend/postgres/redis **healthy**; nginx/queue/scheduler up |
| Stage 7 row counts | tickets=1, tasks=1, installations=1 (STAGE7 TEST records) |
| WhatsApp conversations | **0** rows (inbox empty; customer-column bug not exercised live) |

### How to login (from seeders / ops docs)

| Item | Value |
|------|-------|
| Login URL | `https://finance.mns.af/en/login` (also `/fa/login`) |
| Super Admin email | `admin@finance.mns.af` |
| Username | `admin` |
| Password | **Not in repo / not printable.** Deployments never reset an existing Super Admin. Bootstrap uses `.env` `ADMIN_PASSWORD` only on first install. Emergency rotate: `php artisan admin:reset-password` per `OPERATIONS.md` (optional `.secrets/admin-pass`). |
| Prod note | `.secrets/admin-pass` **missing** on VPS at audit time; `force_password_change=false` for admin id=1. Operators with vault/access must supply password for authenticated Stage 7.1 verification. |

**Verification status:** public health **done**; authenticated role matrix **pending**.

---

## Defect summary by area

Severity: **critical** (broken core path / wrong data) · **high** (major functional gap) · **medium** (incomplete UX / consistency).

---

### 1. Tickets — list / new / detail

| Severity | Defect | Paths |
|----------|--------|-------|
| **High** | List priority filter is sent to API but **backend ignores `priority`**; UI then client-filters the current page only → wrong counts / incomplete results across pages. | `frontend/src/app/[locale]/(app)/tickets/page.tsx`; `frontend/src/lib/tickets.ts`; `backend/app/Http/Controllers/Api/V1/TicketController.php` `index()` |
| **High** | New ticket: customer is raw numeric `customer_id` — no search picker despite `GET /customers?search=` existing. | `tickets/new/page.tsx`; `lib/customers.ts` |
| **High** | Detail assign: raw numeric `user_id` — no user picker. `GET /users` requires `users.view` / `users.manage` (Branch Manager has it; many ticket operators may not). | `tickets/[id]/page.tsx`; `UserPolicy`; `lib/auth.ts` `listUsers` |
| **High** | Status change dropdown lists **all** `TICKET_STATUSES`, not allowed transitions → frequent **422** from `TicketStatusTransitionService`. No allowed-transitions API. | `tickets/[id]/page.tsx`; `Ticket::allowedTransitions()` |
| **High** | Attachments: upload only; no list of persisted attachments; no download UI. Local `uploads` state shows filenames only for current session. | `tickets/[id]/page.tsx`; `AttachmentController` |
| **Medium** | List missing filters that backend already supports: `type_code`, `customer_id`. Also missing: assignee, department/team, source, SLA breached, free-text search. | `tickets/page.tsx`; `TicketController@index` |
| **Medium** | No watcher UI despite `POST /tickets/{id}/watchers`. | `tickets/[id]/page.tsx` |
| **Medium** | Cannot create linked task from ticket detail (`tasks.create` unused). | `tickets/[id]/page.tsx` |
| **Medium** | `TicketResource` does not include `tasks` even though `show` eager-loads them (UI compensates via `listTasks`). | `TicketResource.php`; `TicketController@show` |

---

### 2. Tasks — list / my / detail

| Severity | Defect | Paths |
|----------|--------|-------|
| **High** | No create-task UI (`POST /tasks` unused by pages). Cannot spawn departmental tasks from tickets/installations in the product UI. | `tasks/page.tsx`; `lib/tasks.ts` `createTask` |
| **High** | Detail missing **reject**, **verify**, **reassign**, **cancel**, **offer** — field loop incomplete vs `FIELD_MOBILE_WORKFLOW.md`. | `tasks/[id]/page.tsx`; `TaskController` |
| **Medium** | List filters: status only. Missing branch, assignee, priority, department (API supports status/ticket_id/type). | `tasks/page.tsx`; `TaskController@index` |
| **Medium** | My tasks: no status filter; API excludes terminal statuses only. | `tasks/my/page.tsx`; `TaskController@my` |
| **Medium** | Attachments upload only; no list/download. Work logs not listed on detail (create-only). | `tasks/[id]/page.tsx` |

Field names (`task_number`, `assignee`, `depends_on_tasks`, etc.) largely **align** with `TaskResource`.

---

### 3. Installations

| Severity | Defect | Paths |
|----------|--------|-------|
| **High** | No create-installation UI despite `POST /installations` and `installations.create` on Branch Manager. Queue is view/transition only. | `installations/page.tsx`; `installations/[id]/page.tsx`; `lib/installations.ts` |
| **High** | Transition dropdown shows **all** statuses, not `Installation::allowedTransitions()` → 422s. | `installations/[id]/page.tsx` |
| **Medium** | Index filters: status only. Missing `branch_id`, customer/prospect search. | `InstallationController@index` |
| **Medium** | No attachment / work-log surfaces on installation detail. | `installations/[id]/page.tsx` |

`Installation` / `InstallationResource` field names align with `lib/installations.ts`.

---

### 4. Dashboards (support / technical / NOC / finance / management)

| Severity | Defect | Paths |
|----------|--------|-------|
| **Medium** | KPI-only pages: no drill-down links into filtered ticket/task/installation lists. | `support/dashboard`, `technical/dashboard`, `noc/dashboard`, `finance/tasks`, `management/service-operations` |
| **Medium** | Support dashboard ignores `by_priority` returned by API. | `support/dashboard/page.tsx`; `OperationsDashboardService::supportSummary` |
| **Medium** | No branch selector on any ops dashboard (API accepts `branch_id`). | `lib/operations.ts` + dashboard pages |
| **Medium** | Nav for management ops requires `reports.management` only; API allows any `dashboard.view` — permission mismatch. | `app-shell.tsx`; `OperationsDashboardController@manager` |
| **Medium** | Finance “tasks” route is finance **installation queue KPIs**, not a finance task list — naming confusion. | `finance/tasks/page.tsx` |

Response field names match `lib/operations.ts`.

---

### 5. WhatsApp inbox

| Severity | Defect | Paths |
|----------|--------|-------|
| **Critical** | Conversation detail eager-loads `customer:id,name,mobile,phone` but `customers` table has **`contact_name`**, not `name`. Opening a conversation with `customer_id` set will **SQL error**. (Prod currently has 0 conversations — latent.) | `WhatsAppController::showConversation` |
| **High** | “Link ticket” does **not** link an existing ticket id; when no intake suggestion exists it **creates a new ticket** with subject referencing the typed id. Misleading UX. | `whatsapp/inbox/page.tsx` `onLinkTicket` |
| **Medium** | Inbox uses relation key `inboundMessages` (camelCase); UI prefers `inbound_messages` with camelCase fallback — fragile. | `WhatsAppController`; `whatsapp/inbox/page.tsx` |
| **Medium** | No dismiss-intake UI; `dismissTicketIntake` unused. | inbox page |
| **Medium** | Create-from-inbox requires `branch_id` on conversation; missing branch fails requiredFields. | inbox page |

---

### 6. Settings pages

| Severity | Defect | Paths |
|----------|--------|-------|
| **Medium** | Ticket types / SLA / escalation rules / task templates are minimal CRUD — missing most backend fields (department defaults, SLA binding, escalate targets, template steps). | `settings/ticket-types`, `sla-policies`, `escalation-rules`, `task-templates` |
| **Medium** | Settings hub (`/settings`) is company/currency/timezone only — no in-page links to Stage 7 settings. | `settings/page.tsx` |
| **Medium** | Escalation **rules** settings exist; live Stage 7 **escalations** queue UI does **not**. Legacy `/escalations` is Stage 3 visit outcomes. | `escalations/page.tsx` vs `EscalationController` |

---

### 7. App-shell navigation

| Severity | Defect | Paths |
|----------|--------|-------|
| **High** | No nav entry for Stage 7 **ticket escalations** API (`GET /escalations`). Existing `/escalations` is collection-visit escalations — domain collision. | `app-shell.tsx`; `escalations/page.tsx` |
| **Medium** | Operations group mixes `reports.*` vs resource `.view` permissions inconsistently vs API middleware. | `app-shell.tsx` |
| **Medium** | Collector nav has no My Tasks / tickets even though Collector is seeded with `tasks.view` / `tasks.accept` / `tickets.view`. | `COLLECTOR_NAV`; `RolePermissionSeeder` |
| **Medium** | Feature flags for ticketing/tasks/installations are **enabled** (OK); CRM remains roadmap. | `feature-flags.ts` |

---

### 8. Client field names vs backend resources

| Client | Backend | Verdict |
|--------|---------|---------|
| `lib/tickets.ts` `Ticket` | `TicketResource` | **Aligned** for listed fields. `tasks` on type but not in resource (fetched separately). |
| `lib/tasks.ts` `Task` | `TaskResource` | **Aligned**. |
| `lib/installations.ts` | `InstallationResource` | **Aligned**. |
| `lib/operations.ts` dashboards | `OperationsDashboardService` | **Aligned**. |
| WhatsApp inbox customer | `Customer` model | **Misaligned** — API selects nonexistent `name` column. |
| Work logs | `WorkLog` (raw paginate) | Frontend accepts `internal_note` / `body` / `customer_visible_note` — OK. |

---

### 9. TicketController index filters vs Stage 7.1 expectations

**Implemented today:** `branch_id`, `status`, `type_code`, `customer_id` (+ pagination).

**Missing vs UI + ops expectations:**

| Filter | UI | Backend | Notes |
|--------|----|---------|-------|
| `priority` | Yes | **No** | Client-side page filter only — **high** |
| `type_code` | No | Yes | Wire UI |
| `customer_id` | No | Yes | Wire UI + picker |
| `primary_assignee_id` | No | **No** | Needed for queue triage |
| `assigned_department_id` / `team_id` | No | **No** | Org model exists |
| `source` | No | **No** | WhatsApp vs manual queues |
| `q` / search (number, subject) | No | **No** | Ops search API exists separately |
| `sla_breached` | No | **No** | Dashboard KPI has no drill-down |

OpenAPI `listTickets` also omits `priority` — docs lag UI.

---

### 10. TaskController / InstallationController

**TaskController**

- Index: `status`, `ticket_id`, `type` only. Missing: `branch_id`, `assignee_id`, `priority`, `department_id`, `installation_id`.
- My: assignee-scoped, non-terminal — OK for mobile.
- Actions: full workflow endpoints present; UI covers accept/travel/arrive/start/complete/block/upload only.

**InstallationController**

- Index: `status` only. Missing: `branch_id`, search/contact, customer.
- Store + transition present; **no create UI**.
- No assign/technician endpoints (technician_id on model unused by API).

---

### 11. Allowed transitions API

| Question | Answer |
|----------|--------|
| Exists? | **No** dedicated endpoint |
| Enforcement | Model `allowedTransitions()` / `canTransitionTo()` + 422 on bad `transition` |
| UI | Shows full enum lists for tickets and installations |
| Recommendation | `GET /tickets/{id}/allowed-transitions` (and tasks/installations) or embed `allowed_transitions` on show resources |

---

### 12. Attachments — list/download vs UI

| Capability | Backend | UI |
|------------|---------|-----|
| Upload `POST /attachments` | Yes (`attachments.upload`) | Ticket + task detail |
| Download `GET /attachments/{uuid}/download` (signed) | Yes | **Not used** (UI discards `download_url`) |
| List by attachable | **No endpoint** | Cannot reload historical files |
| Include on Ticket/Task show | Relations on models; **not** in resources | Session-local names only |

---

### 13. Customer search API for picker

| Item | Status |
|------|--------|
| API | `GET /customers?search=&branch_id=&page=` — **exists** (`CustomerController@index`) |
| Client helper | `listCustomers({ search })` in `lib/customers.ts` |
| Wired into ticket/installation create | **No** — raw id inputs |
| Permission | `customers.view` |

---

### 14. User / department / team APIs for pickers

| API | Status | Usable as picker? |
|-----|--------|-------------------|
| `GET /users?search=` | Exists | Yes, but gated by `users.view` \| `users.manage` — **too strict** for staff with only `tickets.assign` |
| `GET /departments` (+ nested `teams`) | Exists | Yes; **unused** by frontend |
| `GET /teams` standalone | **No** | Use departments payload |
| Frontend wiring | None on ticket/task/installation forms | — |

---

## Prioritized fix list

### P0 — Critical / unblock core paths

1. **Fix WhatsApp conversation customer eager-load** — use `contact_name` (or resource transform), not `name` (`WhatsAppController::showConversation`).
2. **Server-side ticket `priority` filter** (and remove broken client-only page filter) — `TicketController@index` + OpenAPI.
3. **Allowed transitions on ticket/installation show (or dedicated GET)** and constrain UI dropdowns — stop systemic 422s.
4. **Attachment list** — embed on show resources or `GET /attachments?attachable_type&attachable_id`; wire download links in ticket/task UI.

### P1 — High functional gaps

5. **Customer search picker** on ticket create (and installation create).
6. **User picker for assign** — lightweight assignee search (or relax `users.view` for assign contexts) + UI select.
7. **Create installation** page + nav CTA (`installations.create`).
8. **Create task** from ticket detail (and optional standalone) using `POST /tasks`.
9. **Task detail**: reject / verify / reassign / cancel actions per workflow docs.
10. **WhatsApp “link ticket”** — real link-to-existing behavior (or rename); stop creating duplicate tickets.
11. **Collector (field) nav**: My Tasks (+ linked ticket) so seeded field permissions are reachable.
12. **Stage 7 escalations queue page** (not visit escalations) + nav under operations/NOC.

### P2 — Medium polish

13. Ticket list filters: type, assignee, department, source, SLA breached, search; wire existing `type_code` / `customer_id`.
14. Task/installation index: branch + assignee/priority filters on backend + UI.
15. Dashboard KPI drill-downs + branch selector; surface `by_priority`.
16. Settings forms: expose core backend fields; hub links to Stage 7 settings.
17. Watchers UI; dismiss intake on inbox; use signed `download_url` after upload.
18. Align management dashboard nav permission with API (`reports.management` **or** `dashboard.view`).
19. Rename `/finance/tasks` or clarify copy (finance queue KPIs ≠ task list).
20. Embed `tasks` (and optionally `attachments`) on `TicketResource` for fewer round-trips.

---

## Verification matrix (pending live)

| Role | Tickets | Tasks | Installations | Dashboards | WhatsApp inbox | Settings |
|------|---------|-------|---------------|------------|----------------|----------|
| Super Administrator | pending | pending | pending | pending | pending | pending |
| Branch Manager | pending | pending | pending | pending | pending | pending |
| Collector (field) | pending | pending | n/a | n/a | pending | pending |
| Auditor (view) | pending | pending | pending | pending | pending | pending |

Re-run after P0/P1 with authenticated Playwright or manual checklist against `https://finance.mns.af`.

---

## Stage 7.1 fix status (2026-07-17 finalization)

**Branch:** `cursor/stage-7-1-ui-functional-repair` · **PR:** [#12](https://github.com/nimroozy/finance/pull/12) · **Do not merge.**

### Automated verification

| Suite | Result |
|-------|--------|
| Playwright `e2e/stage7.spec.ts` + `e2e/stage71-functional.spec.ts` | **22/22 passed** (desktop + mobile Chromium) |
| PHPUnit `--filter='Stage7|Stage71'` | **28/28 passed** (154 assertions) |

### P0/P1 repairs delivered in this branch

| Priority | Item | Status |
|----------|------|--------|
| P0 | WhatsApp conversation customer eager-load (`contact_name`) | **Fixed** (backend) |
| P0 | Server-side ticket `priority` (+ related list filters) | **Fixed** — `TicketController` + Stage71 filters tests |
| P0 | `allowed_transitions` on ticket/installation show; UI constrained | **Fixed** — resource + StatusActionMenu / confirm buttons |
| P0 | Attachment list + download wiring on ticket/task detail | **Fixed** — gallery + API list endpoints |
| P1 | Customer / user / branch / department pickers | **Fixed** — `PickerController` + SearchablePicker UI |
| P1 | Create installation / create task from ticket | **Fixed** (UI surfaces present) |
| P1 | Task reject / verify / reassign / cancel | **Partial** — workflow endpoints remain; UI covers primary field loop + confirm transitions |
| P1 | WhatsApp “link ticket” semantics | **Partial / deferred** — not re-verified live (0 conversations on prod at audit) |
| P1 | Collector nav My Tasks | **Fixed** (nav includes My Tasks under Service Operations) |
| P1 | Stage 7 escalations queue page | **Deferred** (domain collision with visit escalations remains) |

### Playwright finalization notes

Eight mocked E2E failures were **selector brittleness** after WorkspaceHeader / DataTable (table+mobile card) / ResponsiveTabs / Quick-create button — not product regressions. Tests updated to:

- Prefer `getByRole('link'|'heading'|'combobox'|'button', { exact })` over ambiguous `getByText`
- Use `#responsive-tabs-select` only when **visible** (mobile); else tablist
- Target Customer combobox (not Branch / command-menu Search)

Authenticated live role-matrix on VPS remains **pending** (admin password not in agent environment).

---

## References

- `docs/STAGE_7_TICKETING_TASKS.md`, `TICKET_WORKFLOW.md`, `TASK_WORKFLOW.md`, `FIELD_MOBILE_WORKFLOW.md`, `INSTALLATION_QUEUE.md`
- `docs/STAGE_7_DELIVERY_REPORT.md` (prod deploy SHA / STAGE7 TEST ids)
- `OPERATIONS.md` (admin credential lifecycle)
- `backend/routes/api.php` Stage 7 block (~495–593)
- `frontend/e2e/stage7.spec.ts` (mocked shells — not a substitute for live auth)
