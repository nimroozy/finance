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
| **6** | **WhatsApp Cloud API + notification orchestration** | **In progress / foundation deployed** |

## Stage 6 — WhatsApp & notifications (current)

**Objective:** Official WhatsApp Business Cloud API foundation and notification orchestration.

**In scope**

- Connection, webhooks, templates, outbound messages, delivery status
- Internal staff notifications
- Notification rules (branch/event)
- Basic inbound-message storage
- Secure links to application resources
- Phone normalization, opt-out, safe retry
- Domain event → orchestrator → queue (no Meta calls inside payment/handover TX)

**Out of scope (defer)**

- Complete support inbox / contact-center UX → **Stage 7 Ticketing**
- Staff bot task commands → **Stage 7**
- WhatsApp-created tickets → **Stage 7**

**Exit criteria:** See Stage 6 completion conditions in the Stage 6 delivery brief (mocked tests, webhook, templates sync path, no financial calc changes). Live Meta verification when credentials are configured.

---

## Stage 7 — Ticketing and task management

**Do not begin until Stage 6 is accepted and architecture docs are reviewed.**

**Required**

- Customer tickets, internal tickets, WhatsApp-created tickets
- Categories, priorities, SLA, assignment, escalation
- Work logs, attachments, resolution, customer confirmation
- Multi-department tasks, task dependencies
- WhatsApp task notifications + staff bot commands
- Branch isolation

**Hard rule:** Separate **tickets** from **tasks**. One ticket may create multiple departmental tasks.

Model: [TICKETING_MODEL.md](TICKETING_MODEL.md).

---

## Stage 8 — CRM, sales pipeline, new installations

**Required**

- Leads, opportunities, follow-ups, site surveys, quotations
- Sales ownership, lost reasons, sales targets
- Installation requests → finance approval → equipment reservation → technical assignment
- Radius activation + Zoho customer/invoice creation (queued)
- Customer acceptance
- Branch sales reports

Model: [CRM_INSTALLATION_MODEL.md](CRM_INSTALLATION_MODEL.md).

---

## Stage 9 — Inventory, assets, sites, towers

**Required inventory:** products, serialized + quantity items, warehouses/offices/towers/sites/customer locations, stock transactions (transfer, reserve, sale, install, return, repair, damage, loss, adjustment, scrap).

**Hard rule:** Immutable inventory transactions only. No direct stock quantity editing.

**Required assets:** fixed assets, tower/office/vehicle/server/power/solar equipment, maintenance, custodian, warranty, condition, photos, documents.

Model: [INVENTORY_MODEL.md](INVENTORY_MODEL.md).

---

## Stage 10 — SAS Radius integration

Branch-aware Radius adapter architecture (independent SAS server per branch).

Lookup, create, activate, suspend, package change, expiration, online status, sessions, usage, disconnect, mappings, health checks.

**Hard rule:** Central app must not depend on live Radius DB for normal page loads — cached views + queued commands. Optional secure local agent later.

Model: [RADIUS_INTEGRATION_MODEL.md](RADIUS_INTEGRATION_MODEL.md).

---

## Stage 11 — Unified dashboards and operational reporting

Branch sales, sales by employee, new internet vs equipment sales, installation pipeline, ticket SLA, task backlog, inventory value, tower assets, receivables, collections, cash handovers, Radius activation status, department performance, branch comparison.

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
| 9 | SAS Radius branch integrations | 10 |
| 10 | Unified dashboards | 11 |
| 11 | Purchasing and suppliers | After 9 (Zoho-backed) |
| 12 | Advanced operational/financial reporting | 11 |

## Feature flags

Frontend flags live in `frontend/src/config/feature-flags.ts`.

Unfinished modules appear as **roadmap placeholders** only — not as functional pages.
