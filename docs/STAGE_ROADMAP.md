# Stage Roadmap — ISP Operations Platform

Do **not** implement all modules in one stage. Do **not** auto-start the next stage when the current stage completes.

## Platform goal

Unified multi-branch ISP operations so branch staff can work without daily login to Zoho Books or per-branch SAS Radius UIs, while Zoho remains accounting SoT and Radius remains subscriber auth SoT until adapters are safe.

## Completed stages (summary)

| Stage | Focus | Status |
|-------|--------|--------|
| 1 | Foundation (auth, branches, audit, Docker) | Complete |
| 2 | Zoho Books sync (customers/invoices) | Complete |
| 3 | Assignments, routes, visits, promises | Complete |
| 4 | Payments, receipts, wallets, Zoho payment sync | Complete |
| 5 | Cash handovers / cashboxes | Complete |
| 5.1–5.3 | Sync hardening, ownership, prefix mapping, cleanup | Complete |
| **6** | **WhatsApp Cloud API + notification orchestration** | **Complete / foundation** |
| **7** | **Ticketing, tasks, installation queue** | **Complete / delivered on branch** |
| **8** | **CRM, sales pipeline, installations handoff** | **In progress / delivered on branch** |
| **9** | **Inventory, assets, sites, towers** | **In progress / delivered on branch** |
| **9.1** | **Unified app launcher, bilingual UI, UX shell** | **Complete / in 10.1 tip** |
| **10** | **ISP service lifecycle** | **Complete / in 10.1 tip** |
| **10.1** | **Integrated stable baseline (7→10 + 9.1)** | **Complete / in 10.2 tip** |
| **10.2** | **Production recovery (service API, search, cleanup dry-run)** | **Complete / in 10.3 tip** |
| **10.3** | **Functional acceptance (launcher, ActivationPanel, queues, reviewed cleanup)** | **In progress / tip branch** |

## Stage 6 — WhatsApp & notifications (complete / foundation)

**Objective:** Official WhatsApp Business Cloud API foundation and notification orchestration.

**Delivered**

- Connection, webhooks, templates, outbound messages, delivery status
- Internal staff notifications
- Notification rules (branch/event)
- Basic inbound-message storage
- Secure links to application resources
- Phone normalization, opt-out, safe retry
- Domain event → orchestrator → queue (no Meta calls inside payment/handover TX)

**Consumed by Stage 7:** inbound message events, staff notifications, WhatsApp delivery for ticket/task intents.

**Exit criteria:** Stage 6 completion conditions met (mocked tests, webhook, templates sync path, no financial calc changes). Live Meta verification when credentials are configured.

---

## Stage 7 — Ticketing and task management (in progress / delivered on branch)

**Overview:** [STAGE_7_TICKETING_TASKS.md](STAGE_7_TICKETING_TASKS.md).

**Delivered / in scope on this branch**

- Customer tickets, internal tickets, WhatsApp intake → tickets
- Categories, priorities, severities, sources, SLA, assignment, escalation
- Work logs, attachments, resolution, customer confirmation, reopen
- Multi-department tasks (field/office), dependencies, templates, verify
- WhatsApp task notifications + staff bot commands (signed tokens)
- **Installation queue** (operational status pipeline only)
- Operations dashboards, search, ticket/task reports, customer timeline
- Branch isolation

**Hard rule:** Separate **tickets** from **tasks**. One ticket may create multiple departmental tasks.

**Clarification — installation queue vs CRM:** Stage 7 owns the installation **queue** (request → field → confirmation statuses). Full CRM (leads, opportunities, quotations, sales targets) and commercial install orchestration are **Stage 8** (see [STAGE_8_CRM_SALES.md](STAGE_8_CRM_SALES.md)).

Model: [TICKETING_MODEL.md](TICKETING_MODEL.md). Queue notes: [INSTALLATION_QUEUE.md](INSTALLATION_QUEUE.md).

---

## Stage 8 — CRM, sales pipeline, new installations

**In progress / delivered on branch.** Overview: [STAGE_8_CRM_SALES.md](STAGE_8_CRM_SALES.md).

**Delivered / in scope on this branch**

- Leads, opportunities, follow-ups, site surveys, quotations
- Sales ownership, lost reasons, sales targets
- CRM dashboard + lead CSV reports
- Frontend CRM workspace (pipeline, leads, follow-ups, quotations, surveys, targets, reports)
- Conversion → customer + installation queue handoff (event/queue; no inline Zoho/Radius)

**Still later / partial**

- Full CRM-grade install orchestration through finance/equipment/Radius/Zoho acceptance
- Deep sales attribution dashboards (Stage 11)

Model: [CRM_INSTALLATION_MODEL.md](CRM_INSTALLATION_MODEL.md).

---

## Stage 9 — Inventory, assets, sites, towers

**In progress / delivered on branch.** Overview: [STAGE_9_INVENTORY_ASSETS.md](STAGE_9_INVENTORY_ASSETS.md).

**Delivered / in scope on this branch**

- Immutable stock ledger, products, locations, reservations, transfers, receiving, counts
- Serialized equipment lifecycle, custody, repairs, maintenance
- Sites, towers, fixed assets, customer equipment
- Purchasing requests/orders + goods receipts (operational)
- Frontend inventory / assets / purchasing workspaces + reports
- Import dry-run/apply + CSV reports

**Hard rule:** Immutable inventory transactions only. No direct stock quantity editing.

Models: [INVENTORY_MODEL.md](INVENTORY_MODEL.md), [INVENTORY_LEDGER_MODEL.md](INVENTORY_LEDGER_MODEL.md).

---

## Stage 9.1 — Unified app launcher & UX stabilization

**Delivered on branch** `cursor/stage-9-1-unified-app-ui` (PR stacks on stage-9). Overview: [STAGE_9_1_UI_UX.md](STAGE_9_1_UI_UX.md).

**Delivered / in scope**

- `/apps` permission-aware launcher (favorites, recent, search, groups)
- Server-backed UI preferences (theme, favorites, recent, default app)
- Shared app shell: header, context sidebar, mobile drawer, bottom nav
- EN/FA + RTL shell polish, dark/light/system theme, design tokens
- i18n key parity check (`npm run i18n:check`)
- Playwright launcher + matrix smoke; backend Stage91 preference tests

**Hard rule:** UI/navigation/localization only. No accounting, inventory ledger, CRM, or Zoho behavior changes.

Docs: [APP_LAUNCHER_ARCHITECTURE.md](APP_LAUNCHER_ARCHITECTURE.md), [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md), [EN_FA_LOCALIZATION.md](EN_FA_LOCALIZATION.md), [UI_TEST_MATRIX.md](UI_TEST_MATRIX.md).

---

## Stage 10 — ISP Service Lifecycle

**Delivered on branch** `cursor/stage-10-service-lifecycle` (PR #15) and included in integrated tip `cursor/stage-10-1-integrated-stable` (PR #17).

Commercial/operational service lifecycle after installation: packages, locations, activation, suspension, reactivation, cancellation, change requests, relocation, renewals, contracts, finance holds, and Zoho-synced read-only billing views.

Docs: [STAGE_10_SERVICE_LIFECYCLE.md](STAGE_10_SERVICE_LIFECYCLE.md).

**Hard rules**

- Do **not** connect to SAS Radius or any Radius DB in this stage.
- Do **not** begin automated network provisioning.
- Zoho remains source of truth for customers / invoices / payments — billing views are read-only from synced data.
- Radius feature flag stays **false** (deferred to Stage 12).
- Stage 10 = **Service Lifecycle**; Radius is **not** Stage 10.

---

## Stage 10.1 — Integrated stable baseline

**Tip branch:** `cursor/stage-10-1-integrated-stable` (draft PR #17). Version label: `10.1-integrated-stable`.

Unifies Stages **7 / 7.1 / 8 / 9 / 9.1 / 10** for production deploy. Docs: [STAGE_10_1_INTEGRATED_STABLE.md](STAGE_10_1_INTEGRATED_STABLE.md), [BRANCH_INTEGRATION_HISTORY.md](BRANCH_INTEGRATION_HISTORY.md), [REGRESSION_TEST_MATRIX.md](REGRESSION_TEST_MATRIX.md), [PRODUCTION_SMOKE_TEST.md](PRODUCTION_SMOKE_TEST.md).

**Hard rules:** No Stage 11. No Radius. Preserve superseded PRs #15/#16 for audit (close later as superseded). Do not mutate financial or ledger calculations.

---

## Stage 10.2 — Production recovery

**Tip branch:** `cursor/stage-10-2-production-recovery`. Version label: `10.2-production-recovery`.

Service API completeness, global search, launcher counts, branch integrity, demo-cleanup CLI (dry-run). Docs: [STAGE_10_2_PRODUCTION_RECOVERY.md](STAGE_10_2_PRODUCTION_RECOVERY.md), [STAGE_10_2_DELIVERY_REPORT.md](STAGE_10_2_DELIVERY_REPORT.md).

---

## Stage 10.3 — Functional acceptance

**Tip branch:** `cursor/stage-10-3-functional-acceptance`. Version label: `10.3-functional-acceptance`.

Functional acceptance of launcher, ActivationPanel evidence UI, service queues, AcceptanceSeeder (non-prod), reviewed demo cleanup apply. Docs: [STAGE_10_3_FUNCTIONAL_ACCEPTANCE.md](STAGE_10_3_FUNCTIONAL_ACCEPTANCE.md), [STAGE_10_3_DELIVERY_REPORT.md](STAGE_10_3_DELIVERY_REPORT.md), [PRODUCTION_SHA_VERIFICATION.md](PRODUCTION_SHA_VERIFICATION.md).

**Hard rules:** No Stage 11. No Radius. No live WhatsApp. No uncontrolled Zoho writes. Do not seed AcceptanceSeeder on production. Preserve SSH deploy keys (**KEY_KEPT**).

---

## Stage 10.4 — Production acceptance closure

**Tip branch:** `cursor/stage-10-4-production-acceptance-closure`. Version label: `10.4-production-acceptance-closure`.

Isolated acceptance stack, `./scripts/run-acceptance.sh`, real Playwright matrix (desktop/mobile/small-mobile × en/fa), DB assertions, route crawler, console/network audit, SHA closure. Docs: [STAGE_10_4_PRODUCTION_ACCEPTANCE_CLOSURE.md](STAGE_10_4_PRODUCTION_ACCEPTANCE_CLOSURE.md), [STAGE_10_4_DELIVERY_REPORT.md](STAGE_10_4_DELIVERY_REPORT.md), [STAGE_10_4_STARTING_SHA.md](STAGE_10_4_STARTING_SHA.md), [STAGE_10_4_SHA_VERIFICATION.md](STAGE_10_4_SHA_VERIFICATION.md).

**Hard rules:** No Stage 11. No Radius. No live WhatsApp. No uncontrolled Zoho writes. Do not seed AcceptanceSeeder on production. Required real E2E skipped count = 0. Preserve SSH deploy keys (**KEY_KEPT**).

---

## Stage 11 — Unified dashboards and operational reporting

Branch sales, sales by employee, new internet vs equipment sales, installation pipeline, ticket SLA, task backlog, inventory value, tower assets, receivables, collections, cash handovers, service lifecycle status, department performance, branch comparison.

---

## Stage 12 — SAS Radius integration (deferred)

Branch-aware Radius adapter architecture (independent SAS server per branch).

Lookup, create, activate, suspend, package change, expiration, online status, sessions, usage, disconnect, mappings, health checks.

**Hard rule:** Central app must not depend on live Radius DB for normal page loads — cached views + queued commands. Optional secure local agent later.

Model: [RADIUS_INTEGRATION_MODEL.md](RADIUS_INTEGRATION_MODEL.md).

---

## Future modules (mapped to stages)

| # | Module | Primary stage |
|---|--------|---------------|
| 1 | WhatsApp and notifications | 6 |
| 2 | Ticketing and task management | 7 |
| 3 | CRM and lead management | 8 |
| 4 | New installation workflow | 8 |
| 5 | Inventory and stock | 9 |
| 6 | Fixed asset and tower/site | 9 |
| 7 | Customer-installed equipment | 8–9 |
| 8 | Sales management and reporting | 8 + 11 |
| 9 | ISP service lifecycle | 10 |
| 10 | Unified dashboards | 11 |
| 11 | SAS Radius branch integrations | 12 |
| 12 | Purchasing and suppliers | After 9 (Zoho-backed) |
| 13 | Advanced operational/financial reporting | 11 |

## Feature flags

Frontend flags live in `frontend/src/config/feature-flags.ts`.

Unfinished modules appear as **roadmap placeholders** only — not as functional pages.
