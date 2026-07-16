# UI_FUNCTIONAL_AUDIT.md

**Audit phase:** Stage 5.1  
**Date (UTC):** 2026-07-16  
**App URL:** `https://finance.mns.af`  
**Locales:** `/en/...`, `/fa/...` (prefix always)  
**Source SHA:** `c0b1ebd076a65c4c098a0cc37e68825a644f8189`

## Method & limits

- **Route inventory:** Next.js `frontend/src/app/[locale]/**/page.tsx` (**58** pages).  
- **HTTP smoke (unauthenticated):** key manager routes return **200** (SPA shell); `/api/v1/health` **200**.  
- **Authenticated API smoke:** admin login using `/opt/collection-system/.secrets/admin-pass` returned **401** during this audit — full button/API matrix against live session **not completed**. Do **not** rotate admin password in audit phase.  
- **Browser automation (Playwright):** not fully executed this pass due to auth blocker; recommended as first repair-phase diagnostic with labeled test users.  
- Code + prior Stage smoke used to classify broken vs missing vs partial.

A control is **broken** if it does nothing, hits a missing API, shows placeholder success, requires raw internal IDs without help, reports success before backend success, opens incomplete pages, or fails without feedback.

---

## Unauthenticated HTTP smoke

| URL | HTTP |
|-----|------|
| `/en/login`, `/fa/login` | 200 |
| `/en/dashboard`, `/en/zoho`, `/en/customers`, `/en/debtors`, `/en/invoices` | 200 |
| `/en/assignments`, `/en/payments`, `/en/handovers`, `/en/cashboxes`, `/en/wallets`, `/en/receipts` | 200 |
| `/en/collector`, `/en/collector/handovers/new` | 200 |
| `/en/branches`, `/en/users`, `/en/settings`, `/en/audit-logs` | 200 |
| `/api/v1/health` | 200 |

(Shell loads; data calls require auth.)

---

## Route audit (minimum set)

| URL (locale prefix omitted) | Role intent | Permission | Primary APIs | Loading/empty/error | Mobile/RTL | Actions | Result / issues | Severity | Redesign note |
|-----------------------------|-------------|------------|--------------|---------------------|------------|---------|-----------------|----------|---------------|
| `/login` | Public | — | `POST /auth/login` | Expected | OK expected | Login | Auth secret 401 in audit — **verify creds** | High | — |
| `/change-password` | Any | — | `POST /auth/change-password` | — | — | Submit | Works in tests; force-change gate | Med | — |
| `/dashboard` | Manager | various | dashboard aggregates | — | — | Nav | Depends on mapped data | Med | Keep simple |
| `/zoho` | Admin | zoho.* | status, oauth, sync | — | — | Connect, sync, links | Sync actions hit **broken incremental** | Critical | Surface sync health errors |
| `/zoho/sync-jobs` | Admin | zoho.view | sync-jobs | lists fails | — | Retry | Retry amplifies failures | High | Disable retry when root fail |
| `/zoho/api-logs` | Admin | zoho.view | api-logs | — | — | Filter | Working for forensics | — | — |
| `/zoho/branch-mappings` | Admin | zoho.configure | branch-mappings CRUD | empty table prod | — | Create mapping | **Requires raw Zoho ID** in `zoho_value` — broken UX | Critical | Pickers for locations/tags |
| `/zoho/unmapped` | Admin | zoho/customers | customers/unmapped | ~4137 | — | Map branch | Manual map scale impossible | Critical | Bulk map from locations |
| `/branches` | Admin | branches.* | branches | 3 rows | — | CRUD | Working as local config | Med | Align naming with Zoho locations |
| `/customers`, `/customers/[id]` | Staff | customers.view | customers | mostly unmapped | — | View | Data present but unscoped for ops | High | Filter clarity |
| `/invoices` | Staff | invoices.view | invoices | list only | — | — | **No invoice detail page** | Med | Add detail |
| `/debtors` | Staff | debtors.view | debtors | empty-ish ops | — | Export | Blocked by mapping | High | — |
| `/assignments` (+ new/bulk/unassigned/workload/[id]) | Manager | assignments.* | assignments* | — | — | Assign/reassign | Partial — few mappable debtors | High | — |
| `/collectors` | Manager | collectors | workload | — | — | — | — | Med | — |
| `/routes` (+ new/[id]) | Manager | routes.* | routes | — | — | Start/complete | Partial | Med | — |
| `/visits` (+ [id]) | Manager | visits.* | visits | — | — | View | Partial | Med | — |
| `/promises` | Manager | promises | promises | — | — | — | Partial | Med | — |
| `/escalations` | Manager | escalations.* | **visits filter** | — | — | — | **No escalations API** — proxy page | Med | Real workflow or remove |
| `/reports/collection`, `/reports/payments` | Manager | reports.* | reports | — | — | — | Partial | Low | — |
| `/payments` (+ [uuid], sync-failures, reversals) | Manager | payments.* | payments* | 1 payment | — | Reverse, inspect | Prior Stage4 OK; scale pending | High | — |
| `/receipts` | Manager | receipts | lookup | — | — | UUID → payment | Thin (not full receipt index) | Med | List + search |
| `/wallets` | Manager | wallets.view | wallet + collector_id | 1 wallet | — | View | Partial | Med | — |
| `/handovers` | Manager | handovers.review | cash-handovers | empty | — | Approve/reject | BE exists; no prod volume | High | Partial approval UX |
| `/cashboxes` | Manager | cashboxes.view | cashboxes | empty / list only | — | **View only** | No ensure/detail/txns | High | Add actions |
| `/users`, `/roles` | Admin | users/roles | users, roles | — | — | User CRUD; roles **view-only** | Role edit missing | Low | — |
| `/settings` | Admin | settings.manage | settings | — | — | Save | No payment-settings UI | Med | Merge Zoho payment settings |
| `/audit-logs` | Admin | audit.view | audit-logs | — | — | Filter | Partial UAT | Low | — |
| `/collector` (+ assignments/routes/visits/payments/wallet/handovers/promises/notifications) | Collector | various | collector APIs | — | **Needs mobile UAT** | Field flows | Blocked by mapping + sync | High | Mobile-first pass |
| `/verify-receipt/[token]` | Public | — | verify | — | — | — | Page loads; token UAT pending | Med | — |

### APIs without UI (broken workflow completeness)

| Capability | API | UI | Classification |
|------------|-----|----|----------------|
| Cashbox transfers | Y | **Missing** | Missing / High |
| Bank deposits | Y (transfer type) | **Missing** | Missing / High |
| Cash reconciliation | Y | **Missing** | Missing / High |
| Reporting tag mappings | Y | **Missing** (client helpers unused) | Missing / High |
| Payment settings | Y | **Missing** | Missing / Med |
| Cashbox ensure | Y | **Missing** | Missing / Med |
| Invoice detail | Y | **Missing** | Missing / Low |

---

## Broken / poor controls (count)

| Category | Count (approx) | Examples |
|----------|----------------|----------|
| Broken for production ops | **6+** | Auto/manual incremental sync buttons; branch mapping free-text IDs; retry-failed amplification; unmapped flood; admin login secret mismatch during audit |
| Missing primary actions | **7+** | Transfers, bank deposits, cash recon, tag mapping page, payment settings, cashbox ensure, invoice detail |
| Incomplete / proxy pages | **3+** | Escalations, receipts lookup-only, cashboxes view-only |
| Console/network errors (Playwright) | **Not measured** | Auth blocked; defer to repair-phase script |
| Mobile issues | **Not measured** | Collector routes exist; viewport audit pending |
| RTL issues | **Not measured** | `/fa` shells load; layout audit pending |

---

## Nav findings (`app-shell` NAV_ITEMS)

- Manager nav includes Zoho hub, cash handovers, cashboxes — **not** transfers/reconciliation.  
- Zoho subpages reachable from Zoho hub, not top nav.  
- Collector nav covers assignment → payment → wallet → handover.  
- Escalations in manager nav without dedicated backend.

---

## Mobile & RTL (interim)

| Area | Finding |
|------|---------|
| Locale shells | `/fa/*` HTTP 200 |
| Collector pages | Present for field use |
| Hardcoded bilingual strings | Cashboxes/handovers (i18n polish) |
| Playwright desktop/mobile | **Deferred** — blocked on safe test-account login |

---

## Severity grouping

### Must fix before staff use

1. Sync error visibility + underlying LMT format fix (see `AUTO_SYNC_ROOT_CAUSE.md`)  
2. Branch mapping UX (location picker) + actual mappings for Nimruz  
3. Confirm admin/test user login paths without changing password unless ops-approved  
4. Hide or disable useless `zoho_branch` method given 404  

### Must fix before Stage 6

1. Transfers / bank deposits / cash reconciliation UIs  
2. Reporting-tag mapping UI  
3. Playwright suite: failed network, console errors, RTL, mobile overflow  
4. Handover partial-approval / variance UX hardening  

### Can defer

- Role editor  
- Invoice detail page  
- Escalations productization  
- Cosmetic i18n  

---

## Recommended UI repair phases

1. **Ops unblock:** Zoho hub health (last success, last error, failed_jobs count); mapping wizard from Locations API.  
2. **Cash complete:** Transfers + deposits + reconciliation pages wired to existing APIs.  
3. **Field polish:** Mobile Playwright on collector payment + handover.  
4. **Cleanup:** Remove or implement escalations; receipts index.

---

## Browser inspection checklist (for next phase)

- [ ] Labeled manager + collector test users (no prod admin password change in audit)  
- [ ] Desktop 1280×720 and mobile 390×844  
- [ ] Capture failed XHR, console errors, unhandled rejections  
- [ ] Click every visible button on Zoho, mappings, payments, handovers, cashboxes  
- [ ] RTL overflow checks on `/fa/collector/payments/new` and `/fa/handovers`

*No broad UI redesign deployed in this audit phase.*
