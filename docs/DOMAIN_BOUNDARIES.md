# Domain Boundaries

Each domain owns its tables, services, permissions, API resources, domain events, and audit actions. Cross-domain work uses **events + queued jobs**, never nested foreign API calls inside another domain’s database transaction.

## Shared cross-cutting rules

- Branch scope is mandatory for operational entities unless marked HQ/global.
- Permissions are fine-grained (`domain.action`) and role-assigned.
- Every mutating admin/collector action writes an audit record.
- External IDs (Zoho, Radius, Meta) are stored with sync/retry metadata on the owning domain’s integration records.

---

## Identity

**Owns:** users, roles, permissions, sessions, password policy, department memberships (future).

**Exposes:** auth APIs, `/users`, `/roles`.

**Events:** `UserCreated`, `UserDeactivated`, `RoleChanged`.

**Must not:** call Zoho/Radius/WhatsApp directly for account creation side effects — emit notification intents.

---

## Branches

**Owns:** branches, HQ flags, field-collection exclusion, branch settings.

**Exposes:** `/branches`, branch membership.

**Events:** `BranchCreated`, `BranchMarkedHeadquarter`.

---

## Customers

**Owns:** local customer operational records, prefix mapping, ownership, classification (HQ/test/archived).

**Exposes:** `/customers`, mapping cleanup APIs.

**Events:** `CustomerMapped`, `OwnershipAssigned`, `CustomerClassified`.

**Zoho:** contact sync via Zoho Integration domain; local UI does not replace Zoho GL.

---

## Collections (current production core)

**Owns:** assignments, routes, visits, promises, payments, receipts, wallets, handovers, cashboxes.

**Events:** `PaymentConfirmed`, `HandoverSubmitted`, `PromiseDue`, etc.

**Integrations:** Zoho payment push; WhatsApp via Notifications orchestrator only.

---

## CRM (Stage 8 — delivered foundation)

**Owns:** leads, opportunities, follow-ups, lost reasons, sales ownership, activities, coverage checks, site surveys.

**Exposes:** `/crm/leads`, `/crm/activities`, `/crm/follow-ups`, `/crm/coverage-checks`, `/crm/site-surveys`, `/crm/opportunities`, `/crm/dashboard`.

**Events:** `LeadCreated`, `OpportunityWon`, `OpportunityLost`, conversion/install handoff events.

**Must not:** create Zoho invoices inline — emit finance/install intents; must not mutate payment calc paths.

---

## Sales (Stage 8 — delivered foundation)

**Owns:** quotations, targets, employee sales attribution, equipment sales reporting keys.

**Exposes:** `/crm/quotations`, `/crm/sales-targets`, `/crm/reports/leads`.

**Events:** `QuoteAccepted`, `SalesTargetUpdated`.

---

## Installations

### Queue (Stage 7 — delivered)

**Owns:** installation queue records, status pipeline transitions, optional task-template expansion for field work.

**Exposes:** `/installations`, `/installations/{id}/transition`.

**Events:** `InstallationStatusChanged`.

**Must not:** implement CRM leads/quotes, Zoho invoice creation, inventory reservation, or live Radius activation — those remain later stages. See [INSTALLATION_QUEUE.md](INSTALLATION_QUEUE.md).

### CRM-grade install (Stage 8 — foundation on branch)

**Owns:** installation requests tied to won/converted leads, surveys, finance approval gates, technical assignment orchestration with accounting/Radius handoff.

**Events:** `InstallationRequested`, `InstallationApproved`, `InstallationCompleted` (plus CRM conversion handoff events).

**Downstream:** Inventory reservation job (Stage 9); Radius activation command (Stage 10); Zoho customer/invoice job.

See [STAGE_8_CRM_SALES.md](STAGE_8_CRM_SALES.md).

---

## Tickets (Stage 7 — delivered)

**Owns:** customer/internal tickets, ticket types, categories, priorities/severities/sources, SLA clocks, escalations & rules, work logs, attachments, resolution, customer confirmation, reopen, WhatsApp intake suggestions.

**Exposes:** `/tickets`, `/ticket-types`, `/ticket-intake`, `/sla-policies`, `/escalations`, `/escalation-rules`, `/work-logs`, `/attachments`, operations dashboards/search/reports for tickets.

**Events:** `TicketOpened`, `TicketAssigned`, `TicketStatusChanged`, `TicketEscalated`, `TicketResolved`, `TicketClosed`, `TicketReopened`.

**Inbound WhatsApp:** Notifications/WhatsApp stores message → emits inbound event → Ticketing creates intake suggestion / ticket when rules match.

---

## Tasks (Stage 7 — delivered)

**Owns:** departmental tasks (field/office), offer/accept workflow, travel/arrive, dependencies, templates, verification, reassign/cancel.

**Exposes:** `/tasks`, `/tasks/my`, task action endpoints, `/task-templates`.

**Rule:** Separate from tickets. One ticket may spawn many tasks. Installations may also spawn tasks via templates.

**Events:** `TaskCreated`, `TaskOffered`, `TaskAccepted`, `TaskRejected`, `TaskStarted`, `TaskCompleted`, `TaskBlocked`, `TaskVerified`, `TaskReassigned`.

---

## Inventory (Stage 9)

**Owns:** products, serials, quantity items, warehouses/offices/towers/sites locations, **immutable** stock transactions.

**Events:** `StockReserved`, `StockTransferred`, `StockSold`, `StockAdjusted` (adjustment = txn type, not direct qty edit).

**Must not:** allow `UPDATE products SET qty`.

---

## Assets / Sites / Towers (Stage 9)

**Owns:** fixed assets, tower/office/vehicle/power equipment, custodians, maintenance, warranties, photos/docs; site and tower registries.

**Events:** `AssetAssigned`, `MaintenanceLogged`, `SiteCommissioned`.

---

## Purchasing (Stage 11-ish / after inventory)

**Owns:** supplier workflows and operational PO tracking.

**Accounting impact:** Zoho bills/purchases via Zoho Integration with full idempotency.

---

## Finance

**Owns:** local finance approval workflows that require accounting impact.

**Must always attach:** Zoho status, Zoho ID, idempotency key, retry state, reconciliation state, audit.

---

## Zoho Integration

**Owns:** OAuth, org structure sync, customer/invoice sync, location/prefix mapping, payment/credit note push, API logs, dry-run controls.

**Consumes:** domain events needing accounting posting.

**Must not:** own inventory qty or ticket SLA clocks.

---

## Radius Integration (Stage 10)

**Owns:** branch adapter configs, cached subscriber views, command queue, package/customer mappings, health checks.

**Must not:** block page loads on live Radius DB connectivity — use cache + async commands.

---

## WhatsApp (Stage 6 — complete / foundation)

**Owns:** connection, templates, outbound messages, delivery events, webhook ingress, basic inbound storage, opt-outs, phone normalization.

**Must not:** implement full support desk business rules (Stage 7 Ticketing owns intake → ticket).

**Emits:** delivery/inbound events for Notifications and Ticketing intake.

---

## Notifications

**Owns:** multi-channel orchestration, branch/role/event rules, dedupe keys.

**Channels:** `in_app`, `whatsapp`, future `email` / `sms`.

**Rule:** Domain services call orchestrator only — never Meta/Zoho Graph clients.

---

## Reporting (Stage 11)

**Owns:** aggregated dashboards and exports. Reads from domain projections; does not mutate operational state.

---

## Audit

**Owns:** append-only audit log. All domains write through `AuditLogger`.

---

## Event flow example (payment receipt WhatsApp)

```
PaymentService::confirm
  └─ DB commit (payment + wallet + receipt)
  └─ dispatch BusinessNotificationRequested(payment_confirmed)
       └─ OrchestrateBusinessNotification (listener)
            └─ NotificationOrchestrator
                 ├─ in_app notify
                 └─ queue WhatsApp template job (if rule enabled)
```
