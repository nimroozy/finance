# PRODUCTION_FEATURE_AUDIT.md

**Audit phase:** Stage 5.1 (Audit only — no broad repair)  
**Date (UTC):** 2026-07-16  
**Source branch:** `cursor/stage-5-production` @ `c0b1ebd076a65c4c098a0cc37e68825a644f8189`  
**Audit branch:** `cursor/stage-5-1-audit`  
**Draft PR #4 head (do not merge):** `c0b1ebd076a65c4c098a0cc37e68825a644f8189`  
**Runtime:** VPS `/opt/collection-system` → `https://finance.mns.af`  
**Backup:** `/opt/collection-backups/20260716T140903Z-stage51-audit/`  
**Source manifest SHA256:** `676fa721be7001f96cdc451d4e6ac4772267f814909c91cdbc4b9de7accc092d`

## Safety checklist (completed)

| # | Check | Result |
|---|--------|--------|
| 1 | PR #4 head SHA | `c0b1ebd076a65c4c098a0cc37e68825a644f8189` |
| 2 | Deployed source checksum | Manifest above; tree under `/opt/collection-system` |
| 3 | DB backup | `postgres.dump` / `postgres.dump.gz` in backup dir |
| 4 | Source/config backup | `source-config.tgz`, `env.backup` (mode 600) |
| 5 | Customer / invoice counts | **4143** / **1854** |
| 6 | Payments / wallets / handovers / cashboxes | **1** / **1** / **0** / **0** |
| 7 | Docker | All services Up; backend/frontend/postgres/redis healthy |
| 8 | Public ports | **22, 80, 443** only (compose DB/Redis not published) |
| 9 | Zoho | Connection `status=connected`, org `929233857` (Mobin Net) |
| 10 | Admin password | Not changed during this audit |
| 11 | PHPUnit vs prod PG | Not run |
| 12 | Reset/reseed | Not performed |

**Note:** GitHub branch and VPS runtime config are not assumed identical. Runtime evidence below comes from the VPS unless marked “code-only.”

---

## Snapshot counts (production DB, 2026-07-16 ~14:09–14:12 UTC)

| Entity | Count / note |
|--------|----------------|
| Customers | 4143 (branch_id set: **6**; null/`is_unmapped`: **4137**) |
| Invoices | 1854 (branch_id set: **1**; null: **1853**) |
| Local branches | 3 (`01` Nimruz, `S3A`, `S3B`) |
| `zoho_branch_mappings` | **0** |
| `zoho_reporting_tag_mappings` | **0** |
| `zoho_payment_mode_mappings` | 1 |
| `zoho_entity_mappings` | 5389 |
| Payments | 1 (prior Stage 4 live-test path; reversed historically) |
| Collector wallets | 1 |
| Cash handovers / cashboxes | 0 / 0 |
| Assignments / visits | low / operational volume not staff-ready given unmapped debtors |
| `failed_jobs` | **1830** (954 customer sync, 876 invoice sync) |
| `zoho_sync_jobs` | ~1975+; recent hourly runs **failed** |

---

## Status legend

| Status | Meaning |
|--------|---------|
| **Working** | Backend + (where needed) UI exist; production evidence of intended success |
| **Partial** | Implemented but incomplete, blocked by dependency, or only verified in limited smoke |
| **Broken** | Runtime fails or core path unusable |
| **Missing** | Required capability absent (API and/or UI) |

“Implemented” is not used as a substitute for Working.

---

## Feature matrix

### Foundation

| Feature | BE | FE | Perm | Auto test | Manual prod | Status | Evidence | Root cause | Repair | Pri | Fin risk | User impact |
|---------|----|----|------|-----------|-------------|--------|----------|------------|--------|-----|----------|-------------|
| Login | Y | Y | n/a | Y | Partial | **Partial** | `/en/login` HTTP 200; Sanctum login API exists. Audit API login with stored admin secret returned **401** (credential/state drift vs `.secrets`; not investigated further to avoid password changes) | Creds / force-password / secret mismatch possible | Verify admin login + rotate only if ops-approved | High | Low | Staff cannot enter if creds wrong |
| Password change | Y | Y | n/a | Y | Code+prior | **Partial** | `change-password` page + API; `force_password_change` used historically | — | Confirm force-change UX after login | Med | Low | Forced change blocks app routes |
| User management | Y | Y | Y | Y | Code | **Partial** | Users CRUD API + `/users` | — | Staff UAT | Med | Med | Admin ops |
| Roles | Y | Y | view | Y | Code | **Partial** | Roles list API/UI; no role-edit manage surface | Design: seeded roles | Defer role editor or document | Low | Low | Role changes via seed/tinker |
| Permissions | Y | — | Y | Y | Code | **Working** | Spatie permissions seeded for Stages 1–5 | — | — | — | — | — |
| Branch access | Y | Y | Y | Y | Code | **Partial** | Branch isolation tests; prod has 3 branches but almost all customers unmapped | Mapping gap | Fix mappings first | Critical | High | Cross-branch leakage risk if scoping bugs; operationally data invisible |
| Audit logs | Y | Y | Y | Y | Code | **Partial** | `/audit-logs` + API | — | Spot-check entries | Low | Low | Compliance |
| Persian / English | Y | Y | — | — | HTTP | **Partial** | `/fa/*` and `/en/*` pages return 200 | Some Stage 5 strings hardcoded bilingual | i18n polish | Low | Low | UX |
| RTL | — | Y | — | — | Not browser-verified this pass | **Partial** | Locale `fa` + RTL CSS expected | — | Playwright RTL pass | Med | Low | Layout |
| Mobile layout | — | Y | — | — | Not full device pass | **Partial** | Collector pages exist; no automated viewport audit completed this phase | — | Playwright mobile | Med | Low | Field use |

### Zoho

| Feature | BE | FE | Perm | Auto test | Manual prod | Status | Evidence | Root cause | Repair | Pri | Fin risk | User impact |
|---------|----|----|------|-----------|-------------|--------|----------|------------|--------|-----|----------|-------------|
| OAuth | Y | Y | Y | Y | Y | **Working** | `zoho_connections.status=connected`, org 929233857 | — | — | — | Low | Setup |
| Token refresh | Y | — | — | Y | Schedule | **Partial** | Job scheduled `*/30`; ran at 14:00:00 | Confirm refresh success rows separately | Monitor | Med | Med | Sync stops if expired |
| Org selection | Y | Y | Y | Y | Prior | **Working** | Org row present (Mobin Net) | — | — | — | Low | — |
| Customers sync | Y | Y | Y | Y | Fail | **Broken** | Hourly + retry jobs FAIL; Zoho 400 `Invalid value passed for last_modified_time` | Timestamp format `P` (`+00:00`) rejected; Zoho accepts `O` (`+0000`) | See `AUTO_SYNC_ROOT_CAUSE.md` | Critical | High | Stale customer data |
| Invoices sync | Y | Y | Y | Y | Fail | **Broken** | Same as customers | Same | Same | Critical | High | Stale invoices |
| Branches (Zoho native) | probe | UI maps | — | — | Y | **Missing** (Zoho) | `GET settings/branches` → **404** Invalid URL | Org has no native Books branches API | Map via locations/tags | High | Med | Wrong mental model |
| Locations | API OK | via mapping method | — | — | Y | **Partial** | 8 locations returned (Kabul, Nimruz, …) | Not auto-imported to local branches | Location→branch mapping UI/ops | High | Med | Branching |
| Reporting tags | API OK | API Y / UI thin | Y | Y | Y | **Partial** | 2 tags; tag options returned as **comma string**, not structured options array | UI for dedicated tag-option mappings missing | Dedicated UI + option ID picker | High | Med | Mapping |
| Reporting tag options | Partial API | Missing dedicated UI | Y | Y | Y | **Partial** | Options not cleanly enumerable from tags payload | Zoho shape | Fetch option IDs properly | High | Med | Mapping |
| Accounts | API OK | — | — | — | Y | **Partial** | chartofaccounts count **672**; no local CoA UI | Not consumed for Stage 5 UI | Defer unless payment account mapping needs | Med | Med | Accounting |
| Payment modes | API OK | settings API | — | — | Y | **Partial** | 5 modes from API; 1 local mapping row | Incomplete mapping coverage | Map modes in settings UI | Med | Med | Payment sync |
| Branch mappings | Y | Y | Y | Y | Empty | **Broken** (ops) | Table count **0**; 4137 unmapped customers | Never configured / no auto from locations | Configure mappings after structure audit | Critical | High | Collection blocked |
| Auto sync | Y | health UI | — | Y | Observed fail | **Broken** | Scheduler dispatches; worker runs; API fails | Format bug + retry amplification | Fix format; flush failed_jobs carefully | Critical | High | Continuous failure noise |
| Manual sync | Y | Y | Y | Y | Same path | **Broken** | Uses same incremental timestamp format when cursor set | Same | Same (+ full sync escape hatch) | Critical | High | Ops cannot refresh |
| Retry | Y | sync-jobs UI | Y | — | Amplifies | **Broken** | `zoho-retry-failed` every 15m requeues failing jobs → 1830 failed_jobs | Retry without fixing root cause | Pause retry until format fix; clear dead failures | High | Med | Queue pollution |
| API logs | Y | Y | Y | — | Y | **Working** | `zoho_api_logs` rows show 400s with message | — | Use for monitoring | — | Low | Ops |
| Reconciliation (payment) | Y job | Missing UI | perm | — | Scheduled | **Partial** | Daily `payment-reconciliation-daily` scheduled | No UI | Add UI later | Med | Med | Ops |

### Field collection

| Feature | BE | FE | Perm | Auto test | Manual prod | Status | Evidence | Root cause | Repair | Pri | Fin risk | User impact |
|---------|----|----|------|-----------|-------------|--------|----------|------------|--------|-----|----------|-------------|
| Assignments | Y | Y | Y | Y | Limited | **Partial** | Pages + APIs exist; debtors mostly unmapped → not staff-ready | Unmapped Zoho data | Mapping + sync repair | High | Med | Core workflow |
| Bulk assignment | Y | Y | Y | Y | Code | **Partial** | `/assignments/bulk` | Same | Same | High | Med | Throughput |
| Reassignment | Y | Y | Y | Y | Code | **Partial** | API + UI | Same | Same | Med | Med | Ops |
| Visits | Y | Y | Y | Y | Code | **Partial** | Collector visit create | Same | Same | High | Low | Field |
| GPS | Y | Y | — | Y | Code | **Partial** | Visit lat/long fields in schema | Device/browser UAT needed | Mobile UAT | Med | Low | Proof |
| Promise to pay | Y | Y | Y | Y | Code | **Partial** | Pages + daily status job | Same | Same | Med | Med | Follow-up |
| Routes | Y | Y | Y | Y | Code | **Partial** | Manager + collector routes | Same | Same | Med | Low | Planning |
| Evidence uploads | Y | Y | Y | Y | Code | **Partial** | Evidence API/tests | Storage UAT | Spot-check | Med | Low | Proof |
| Escalations | thin | Y | seeded | — | Proxy | **Partial** | UI filters visits; **no escalations API** | Incomplete product | Real escalate workflow or remove nav | Med | Low | Confusion |

### Payments

| Feature | BE | FE | Perm | Auto test | Manual prod | Status | Evidence | Root cause | Repair | Pri | Fin risk | User impact |
|---------|----|----|------|-----------|-------------|--------|----------|------------|--------|-----|----------|-------------|
| Preview / full / partial / multi-alloc | Y | Y | Y | Y | Prior Stage4 | **Partial** | Prior live Zoho payment gate passed; prod volume=1 test payment | Not used at scale; unmapped debtors | Enable after mapping+sync | High | **Critical** | Collections |
| Idempotency | Y | — | — | Y | Prior | **Working** (code+prior) | Stage 4 tests + live gate | — | Keep | — | Critical | Dupes |
| Receipt / PDF / thermal | Y | Y | Y | Y | Prior | **Partial** | Receipt routes + verify page HTTP 200 | Staff UAT layouts | Print UAT | Med | Med | Receipts |
| Wallet | Y | Y | Y | Y | Prior | **Partial** | 1 wallet row | Scale UAT | — | Med | High | Custody |
| Zoho payment sync | Y | failures UI | Y | Y | Prior live | **Partial** | Global `ZOHO_PAYMENT_DRY_RUN` expected true; live via scoped flag | Dry-run default intentional | Keep dry-run until staff go-live | High | Critical | Zoho books |
| Reversal | Y | Y | Y | Y | Prior | **Partial** | Reversals page + API | — | Custody-aware path UAT | High | Critical | Corrections |
| Public verification | Y | Y | — | Y | HTTP | **Partial** | `/verify-receipt/[token]` 200 | Token UAT | — | Med | Med | Customer trust |
| Payment settings UI | Y API | **N** | Y | — | — | **Missing** | `PaymentSettingController` no page | Gap | Add settings page | Med | Med | Config |

### Cash custody (Stage 5)

| Feature | BE | FE | Perm | Auto test | Manual prod | Status | Evidence | Root cause | Repair | Pri | Fin risk | User impact |
|---------|----|----|------|-----------|-------------|--------|----------|------------|--------|-----|----------|-------------|
| Handover draft/submit/approve/reject | Y | Y | Y | Y | Smoke prior | **Partial** | UI `/handovers`, `/collector/handovers/new`; prod count 0 (smoke rolled back) | Not yet operationally used | Staff UAT after sync/mapping | High | Critical | Cash chain |
| Partial approval / variance | Y | partial | Y | Y | Code | **Partial** | Services/models exist | UI depth | Harden UI | High | Critical | Cash |
| Wallet debit / cashbox credit | Y | thin | Y | Y | Code | **Partial** | Cashboxes list only; 0 cashboxes | Ensure + handover missing | UI for ensure/transfers | High | Critical | Custody |
| Transfers | Y | **N** | Y | **N** | — | **Missing** (UI) | API `CashboxTransferController` | No page | Build UI | High | Critical | Ops |
| Bank deposits | Y (type) | **N** | Y | **N** | — | **Missing** (UI) | Transfer type `bank_deposit` | No page | Build UI | High | Critical | Ops |
| Reconciliation (cash) | Y | **N** | Y | **N** | — | **Missing** (UI) | `CashReconciliationController` | No page | Build UI | High | Critical | Ops |
| Custody-aware reversal | partial | — | seeded | — | — | **Partial/Missing** | Perm `custody_reversals.review` without full surface | Incomplete | Complete before Stage 6 | High | Critical | Corrections |
| Variances / adjustments | models/perms | **N** | Y | — | — | **Missing** | Seeded perms; no full API/UI | Incomplete Stage 5 surface | Defer or implement | Med | High | Ops |

---

## Totals (audited minimum set)

Approximate classification across matrix rows above (features, not pages):

| Status | Count (approx) |
|--------|----------------|
| Working | **6** |
| Partial | **42** |
| Broken | **7** |
| Missing | **8** |

Interpretation: most Stage 1–5 surfaces are **implemented** but production readiness is dominated by **Broken auto-sync**, **empty branch mappings**, and **Missing Stage 5 cash UIs**.

---

## Critical issues (must fix before staff use)

1. **Incremental auto-sync fails** — Zoho rejects `last_modified_time` formatted with colon offset (`+00:00`). Scheduler and queue are healthy; API call fails. Amplification via `RetryFailedZohoSyncJob` → 1830 `failed_jobs`.
2. **Branch mapping empty** — 0 mappings; **4137/4143** customers and **1853/1854** invoices unmapped → field collection/payments against real debtors not viable under branch scoping.
3. **Zoho “branches” are not native Books branches** — `settings/branches` 404; real structure is **locations (8)** + **reporting tags (2)**. Mapping design must follow that.

## High-priority (must fix before Stage 6)

- Cashbox transfers / bank deposits / cash reconciliation UIs  
- Reporting-tag option mapping UI (API exists, unused)  
- Pause/clear failed sync retry storm after format fix  
- Staff UAT of payments + handovers on mapped data  
- Mobile/RTL Playwright pass  

## Can defer

- Role editor UI  
- Payment settings polish  
- CoA browsing UI  
- Escalations as first-class entity  
- Cosmetic i18n on Stage 5 pages  

## Recommended repair phases (do not start until audit reviewed)

1. **P0 Sync:** Change incremental timestamp format `P` → `O`; stop retry flood; verify one scheduled customer + invoice success cycle.  
2. **P0 Mapping:** Decide location vs tag model; create mappings; re-resolve branch_id for existing rows (controlled, backed-up).  
3. **P1 Cash UI:** Transfers, bank deposits, reconciliation.  
4. **P1 Staff UAT:** Assignments → visits → payments → handovers on mapped Nimruz subset.  
5. **P2 Polish:** RTL/mobile, settings, escalations decision.

---

*No financial records were modified. No PR #4 merge. No Stage 6 work. No broad deploy during this audit.*
