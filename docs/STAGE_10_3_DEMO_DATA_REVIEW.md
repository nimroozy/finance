# Stage 10.3 — Demo data review

**Source:** VPS dry-run `stage102:cleanup-demo --dry-run` (23 candidates)  
**Host:** `root@209.38.194.184` `/opt/collection-system`  
**Reviewed:** 2026-07-18 via SSH `artisan tinker` + FK counts  
**KEY_KEPT:** `~/.ssh/id_ed25519` preserved  

Protected forever: **payments** and **stock_transactions** are never deleted.

## Classification legend

| Class | Meaning | Manifest action |
|-------|---------|-----------------|
| Safe to delete | Clear STAGE*/DO NOT USE, no financial/inventory FKs | `delete` (soft-delete only) |
| Archive only | Demo row; keep history / soft-archive | `archive` |
| Disable-rename | Users/branches: deactivate + rename prefix | `disable_rename` |
| Preserve financial | Linked to payments/invoices — archive never delete | `archive` (customers) / `preserve` (payment rows) |
| Preserve inventory | Linked to stock txs — do not delete | `preserve` |
| False positive | Pattern match but not demo after review | `preserve` |

## Summary counts

| Class | Count |
|-------|------:|
| Safe to delete | 5 |
| Archive only | 11 |
| Disable-rename | 6 |
| Preserve financial (customer archive) | 1 |
| Preserve inventory | 0 |
| False positive | 0 |
| **Total** | **23** |

---

## Customers (7)

| ID | Label | FK checks | Class | Action |
|----|-------|-----------|-------|--------|
| 7000 | STAGE8 TEST customer - DO NOT USE | pay=0 inv=0 svc=1 (cancelled STAGE10 TEST) inst=1 lead=1 | Archive only | `archive` |
| 4138 | STAGE3-TEST Customer 1 | pay=0 inv=0; already `archived`; branch 3 | Safe to delete | `delete` |
| 4139 | STAGE3-TEST Customer 2 | pay=0 inv=0; already `archived`; branch 3 | Safe to delete | `delete` |
| 4140 | STAGE3-TEST Customer 3 | pay=0 inv=0; already `archived`; branch 3 | Safe to delete | `delete` |
| 4141 | STAGE3-TEST Customer 4 | pay=0 inv=0; already `archived`; branch 3 | Safe to delete | `delete` |
| 4142 | STAGE3-TEST Other Branch Customer | pay=0 inv=0; already `archived`; branch 4 | Safe to delete | `delete` |
| 4183 | STAGE5 TEST CUSTOMER - DO NOT USE | **pay=3** (all reversed) inv=3; status=unmapped | Preserve financial → ARCHIVE | `archive` |

**Rule:** Customer 4183 gets inactive/archived + name prefix `[ARCHIVED TEST]` — never hard-delete. Payments 2/4/5 untouched.

---

## CRM leads (1)

| ID | Label | FK checks | Class | Action |
|----|-------|-----------|-------|--------|
| 1 | STAGE8 TEST LEAD - DO NOT USE | converted_customer_id=7000, installation_id=3 | Archive only | `archive` |

---

## Branches (2)

| ID | Label | FK checks | Class | Action |
|----|-------|-----------|-------|--------|
| 3 | STAGE3-TEST Branch A (`S3A`) | cust=4 (all STAGE3-TEST), pay=0, svc=0, tix=2 (STAGE7/71 TEST) | Disable-rename | `disable_rename` |
| 4 | STAGE3-TEST Branch B (`S3B`) | cust=1 (STAGE3-TEST), pay=0 | Disable-rename | `disable_rename` |

No production customers or payments on these branches. Deactivate (`is_active=false`) + rename with `[ARCHIVED TEST]`; soft-delete branch row only (no cascade to payments).

---

## Users (4)

| ID | Label | FK checks | Class | Action |
|----|-------|-----------|-------|--------|
| 3 | STAGE3-TEST Manager (`stage3.manager@test.local`) | pay_refs=0; no SoftDeletes | Disable-rename | `disable_rename` |
| 4 | STAGE3-TEST Collector A | pay_refs=0 | Disable-rename | `disable_rename` |
| 5 | STAGE3-TEST Collector B | pay_refs=0 | Disable-rename | `disable_rename` |
| 6 | STAGE3-TEST Collector Other Branch | pay_refs=0 | Disable-rename | `disable_rename` |

Set `status=disabled` and prefix name/email with `[ARCHIVED TEST]`.

---

## Tickets (2)

| ID | Label | FK checks | Class | Action |
|----|-------|-----------|-------|--------|
| 2 | S7-TEST-… / STAGE7 TEST ticket - DO NOT USE | cust=null; branch 3 | Archive only | `archive` |
| 3 | S71-TEST-… / STAGE71 TEST ticket - DO NOT USE | cust=null; branch 3 | Archive only | `archive` |

---

## Tasks (3)

| ID | Label | FK checks | Class | Action |
|----|-------|-----------|-------|--------|
| 1 | S7-TASK-… / STAGE7 TEST task - DO NOT USE | clear demo title | Archive only | `archive` |
| 2 | S71-TASK-… / STAGE71 TEST task - DO NOT USE | clear demo title | Archive only | `archive` |
| 3 | 01-TSK-2026-000001 | Title looks real; **description** embeds STAGE8 TEST LEAD contact/address (lead_id=1) | Archive only (not false positive) | `archive` |

---

## Installations (4)

| ID | Label | FK checks | Class | Action |
|----|-------|-----------|-------|--------|
| 1 | id=1 (notes only) | notes=`STAGE7 TEST installation - DO NOT USE`; cust=null; branch 3 | Archive only (resolved ambiguity) | `archive` |
| 2 | STAGE71 TEST | cust=null | Archive only | `archive` |
| 3 | STAGE8 TEST | cust=7000; linked from lead 1 | Archive only | `archive` |
| 4 | STAGE9 TEST - DO NOT USE | cust=3430 (`NMZ-0000 0000`, pay=0) — **customer preserved** | Archive only (install) | `archive` |

Customer 3430 is not a dry-run candidate and is left untouched.

---

## Ambiguity resolution (Stage 10.2 holdovers)

| Candidate | Stage 10.2 note | Stage 10.3 verdict |
|-----------|-----------------|--------------------|
| tasks/3 | matched description only | Demo — description is STAGE8 lead survey task → **archive** |
| installations/1 | label `1`, notes only | Demo — notes explicit STAGE7 TEST → **archive** |
| customers/4183 | payments_count=3 | **archive** with `[ARCHIVED TEST]`; payments preserved |

---

## Manifest

Reviewed actions: [docs/manifests/stage103-demo-cleanup.json](manifests/stage103-demo-cleanup.json)  
Apply: `php artisan stage103:cleanup-demo --manifest=docs/manifests/stage103-demo-cleanup.json --apply`  
Result report: [STAGE_10_3_CLEANUP_RESULT.md](STAGE_10_3_CLEANUP_RESULT.md) (written on apply)
