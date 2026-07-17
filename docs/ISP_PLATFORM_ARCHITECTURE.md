# ISP Platform Architecture

## Purpose

This repository is evolving from a multi-branch **debt collection** system into a unified **multi-branch ISP operations platform**.

Most branch employees must complete daily work **without** logging into:

- Zoho Books (accounting UI)
- Individual SAS Radius servers (subscriber auth / service)

## Sources of truth

| System | Owns | Role in the platform |
|--------|------|----------------------|
| **Zoho Books** | Accounting | Source of truth for customers/contacts (where configured), items, invoices, payments, credit notes, accounts, bills, purchase transactions, official financial reporting |
| **SAS Radius** | Subscriber auth & service | Source of truth for online auth, packages, sessions, usage, suspend/activate until safely exposed through adapters |
| **This application** | Operations | Branch-restricted UI, workflows, collections, inventory movement, ticketing, CRM, Radius orchestration, notifications, operational reporting |

Do **not** create a second independent general ledger.

## Design principles

1. **Modular domains** — not one monolithic service class.
2. **Clear boundaries** — permissions, events, API resources, and audit records per domain.
3. **Async integration** — WhatsApp, Zoho, Radius, inventory, and tickets must not call each other inside the same DB transaction.
4. **Domain events + queues** — cross-domain work uses events and queued jobs.
5. **Branch isolation** — every operational record is branch-scoped unless explicitly global (HQ / central finance).
6. **Idempotent accounting impact** — every local financial action that hits Zoho carries Zoho status, Zoho ID, idempotency key, retry state, reconciliation state, and audit.
7. **Staged delivery** — do not implement all modules in one stage. See [STAGE_ROADMAP.md](STAGE_ROADMAP.md).

## Recommended domains

| Domain | Responsibility |
|--------|----------------|
| Identity | Users, roles, permissions, departments, auth |
| Branches | Branch master data, HQ flags, isolation rules |
| Customers | Local customer operational profile, mapping, ownership |
| CRM | Leads, opportunities, follow-ups |
| Sales | Quotes, ownership, targets, lost reasons |
| Installations | New install workflow, surveys, activation handoff |
| Tickets | Customer/internal tickets, SLA, escalation |
| Tasks | Multi-department work items (separate from tickets) |
| Inventory | Products, serials, warehouses, immutable stock txns |
| Assets | Fixed assets, custodians, maintenance |
| Sites | Customer and network site records |
| Towers | Tower/site infrastructure |
| Purchasing | Suppliers, POs, receipts (Zoho-backed accounting) |
| Finance | Local finance workflows that sync to Zoho |
| Collections | Assignments, visits, payments, wallets, handovers |
| Zoho Integration | OAuth, sync, mapping, payment push, reconciliation |
| Radius Integration | Branch-aware adapters, cached views, queued commands |
| WhatsApp | Cloud API connection, templates, outbound, webhooks |
| Notifications | Channel orchestration (in-app, WhatsApp, future email/SMS) |
| Reporting | Operational and management dashboards |
| Audit | Immutable action history across domains |

Detailed contracts: [DOMAIN_BOUNDARIES.md](DOMAIN_BOUNDARIES.md).

## Runtime topology (current + target)

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────┐
│  Next.js UI │────▶│  Laravel API     │────▶│ PostgreSQL  │
└─────────────┘     │  + Sanctum/RBAC  │     └─────────────┘
                    │  + Queues/Redis  │────────────┐
                    └────────┬─────────┘            │
                             │                      ▼
              ┌──────────────┼──────────────┐   Domain events
              ▼              ▼              ▼
         Zoho Books    WhatsApp Cloud   Radius adapters
         (accounting)  (notifications)  (per-branch SAS)
```

## Department model

Each branch may organize users into departments:

- Sales
- Finance
- Technical
- NOC
- Inventory
- Management

Users may belong to one or more departments.

Tasks (Stage 7+) may be assigned to **branch**, **department**, **team**, or **individual user**.

## WhatsApp staff bot (planned)

Commands / interactive actions (Stage 7+ with Ticketing/Tasks):

- My tasks / My tickets / Pending installations / Today visits / My handovers
- Start task / Complete task / Escalate / Open customer

Sensitive actions require secure application links, signed interactive actions, or additional authentication.

Never send passwords. Avoid unnecessary customer financial detail on WhatsApp.

## Integration coupling rules

| Forbidden | Required instead |
|-----------|------------------|
| Calling Meta Graph API inside payment confirmation DB transaction | Emit `BusinessNotificationRequested` → orchestrator → queue |
| Calling Zoho inside inventory stock mutation transaction | Emit inventory event → Zoho posting job |
| Calling Radius inside ticket resolve transaction | Emit activation/suspend command → Radius queue |
| Embedding ticket creation inside WhatsApp webhook TX | Store inbound → emit event → Ticketing listener (Stage 7) |

## Related documents

- [STAGE_ROADMAP.md](STAGE_ROADMAP.md)
- [DOMAIN_BOUNDARIES.md](DOMAIN_BOUNDARIES.md)
- [ZOHO_ACCOUNTING_BOUNDARY.md](ZOHO_ACCOUNTING_BOUNDARY.md)
- [TICKETING_MODEL.md](TICKETING_MODEL.md)
- [CRM_INSTALLATION_MODEL.md](CRM_INSTALLATION_MODEL.md)
- [INVENTORY_MODEL.md](INVENTORY_MODEL.md)
- [RADIUS_INTEGRATION_MODEL.md](RADIUS_INTEGRATION_MODEL.md)
- [../WHATSAPP_SETUP.md](../WHATSAPP_SETUP.md)
