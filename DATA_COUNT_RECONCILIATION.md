# DATA_COUNT_RECONCILIATION.md

**Stage:** 5.1 P1 — Mandatory data consistency gate  
**Date (UTC):** 2026-07-16  
**Runtime:** `/opt/collection-system`  
**Pre-gate backup:** `/opt/collection-backups/20260716T151456Z-stage51-p1-pregate/`  
**Base tip:** `487cdf59b899b29e08961f629c47b087a555cfbd`

## Gate verdict

**PASS** — Customer and invoice totals reconcile exactly. No duplicate Zoho IDs. The jump from ~4,143 to **6,907** customers is a real Zoho import during P0 full sync, not a query multiplication bug. Failed-job 8→13 is explained by five post-cleanup heartbeat-duration failures. Ledgers unchanged. Admin password not reset.

---

## A2. Customer count reconciliation

### Exact queries and results

| Metric | Query logic | Result |
|--------|-------------|--------|
| Total rows | `SELECT COUNT(*) FROM customers` | **6907** |
| Distinct IDs | `SELECT COUNT(DISTINCT id) FROM customers` | **6907** |
| Soft deleted | `WHERE deleted_at IS NOT NULL` | **0** |
| Status `unmapped` | `WHERE status = 'unmapped'` | **6282** |
| Status `active` | `WHERE status = 'active'` | **625** |
| With Zoho contact ID | `WHERE zoho_contact_id IS NOT NULL AND zoho_contact_id <> ''` | **6907** |
| Null Zoho ID | (none) | **0** |
| Duplicate Zoho contact IDs | `GROUP BY zoho_contact_id HAVING COUNT(*) > 1` | **0** |
| Duplicate customer numbers | same pattern on `customer_number` | **0** |
| Mapped (P0 report style) | `WHERE branch_id IS NOT NULL AND is_unmapped = false` | **625** |
| Unmapped flag | `WHERE is_unmapped = true` | **6282** |
| Null branch | `WHERE branch_id IS NULL` | **6282** |
| Mapped + unmapped | 625 + 6282 | **6907** |
| Created before P0 window | `WHERE created_at < '2026-07-16 14:00:00'` | **4143** |
| Created during P0 window | `WHERE created_at >= '2026-07-16 14:00:00'` | **2764** |
| Updated during P0 | `WHERE updated_at >= '2026-07-16 14:00:00'` | **6902** |
| `sync_status = synced` | group by | **6907** |
| Placeholder sync status | | **0** |
| Stage3 test customers (name) | `contact_name ILIKE 'STAGE%'` | **6** |
| Entity mappings (customer) | `zoho_entity_mappings WHERE entity_type='customer'` | **6902** |
| Customers without entity map | left join null | **5** (all Stage3 test fake Zoho IDs) |

**Arithmetic check:** `4143 + 2764 = 6907` ✓  
**Mapped report check:** `625 + 6282 = 6907` ✓  
**No join multiplication:** distinct ID count equals row count.

### Why the count rose from ~4,143 to 6,907

1. Pre-P0 local warehouse held **4143** customers (last successful import before incremental sync broke on `last_modified_time`).
2. During P0, after the timestamp fix, job **#2041** (`SyncZohoCustomersJob`, 2026-07-16 14:49:03) fetched **6902** Zoho contacts (`created=2519`, `updated=4383` in job stats). Upserts committed successfully; the job row was later marked `failed` only because heartbeat wrote a fractional `last_duration_ms` into a bigint column (fixed in P0 hotfix).
3. Net new local rows with `created_at` in the P0 window: **2764** (= Zoho contacts that did not previously exist locally, including growth since the broken incremental period, plus any invoice-first inserts later reconciled).
4. Zoho list size ≈ **6902**; five Stage3 synthetic customers lack entity-map rows → local total **6907**.

**Conclusion:** 6,907 is the correct distinct customer count. It is **not** caused by duplicated joins in reporting queries.

### Quarantine / duplicates

- No duplicate Zoho contact IDs.
- Five Stage3 test customers (`STAGE3-TEST-Z-*`) intentionally lack `zoho_entity_mappings`; they are test fixtures, not Zoho org data.

---

## A3. Invoice count reconciliation

| Metric | Query logic | Result |
|--------|-------------|--------|
| Total rows | `COUNT(*)` | **1989** |
| Distinct IDs | `COUNT(DISTINCT id)` | **1989** |
| Soft deleted | | **0** |
| Status sent / draft / overdue / paid / partially_paid / void | group by status | 1897 / 65 / 15 / 10 / 1 / 1 |
| Open (`balance > 0`) | | **1976** |
| Zero balance | | **13** |
| Duplicate Zoho invoice IDs | `GROUP BY zoho_invoice_id HAVING COUNT(*) > 1` | **0** |
| Null customer_id | | **0** |
| Orphan customer FK | left join customers null | **0** |
| Mapped / unmapped branch | branch_id not null / null | **658 / 1331** |
| With Zoho location | non-null `zoho_location_id` | **1984** |
| Created before P0 | `created_at < 2026-07-16 14:00:00` | **1854** |
| Created during P0 | `created_at >= …` | **135** |
| Branch mismatch vs customer | invoice.branch_id ≠ customer.branch_id (both set) | **0** |
| Entity mappings (invoice) | | **1989** |

**Arithmetic:** `1854 + 135 = 1989` ✓  

Invoice growth of **135** during P0 is a real Zoho import from successful invoice sync after the timestamp fix (plus full re-sync updates of 1984 rows).

---

## A4. Failed jobs reconciliation

| Moment | Count | Explanation |
|--------|------:|-------------|
| Peak before P0 cleanup | ~1890 | Timestamp-format storm |
| Immediately after `zoho:failed-jobs-cleanup --apply` | **8** | 1884 redundant timestamp failures removed; 2 timestamp representatives + unrelated preserved |
| Now (P1 gate) | **13** | 8 preserved + **5 new** failures during first P0 deploy heartbeat bug |

### All 13 remaining (classified — not deleted)

| id | failed_at (UTC) | Job class | Classification | Retryable? | Financial? |
|----|-----------------|-----------|----------------|------------|------------|
| 1–4 | 2026-07-15 15:00:02 | SyncZohoCustomers/Invoices | `invalid_configuration` — Zoho not connected (pre-OAuth) | No (stale) | No |
| 5, 8 | 2026-07-15 15:08–15:15 | SyncZoho* | `permanent_validation` — timestamp format (representatives) | No | No |
| 31, 32 | 2026-07-15 15:56 | SyncZohoCustomersJob | `unknown` / ModelNotFound ZohoSyncJob | No (stale) | No |
| 1891 | 2026-07-16 14:50:01 | SyncZohoCustomersJob | `retryable_timeout` — queue timeout on long full sync | Bounded yes | No |
| 1892–1895 | 2026-07-16 14:50:43–50 | Invoices / Structure / Token / Retry | `invalid_request` infra — bigint heartbeat duration float | No (fixed in code) | No |

**No payment, receipt, wallet, handover, or cashbox jobs** appear in `failed_jobs`.

**8 vs 13 discrepancy:** five failures **after** cleanup during the first P0 deploy (timeout + heartbeat float). Sync data itself succeeded; jobs were mis-marked failed. Safe to archive in P1 cleanup UI after review — **not deleted in this gate**.

---

## A5. Gate checklist

| Condition | Status |
|-----------|--------|
| Customer totals reconcile exactly | **PASS** (6907) |
| Invoice totals reconcile exactly | **PASS** (1989) |
| No duplicate Zoho IDs (or quarantined) | **PASS** (0 dups; Stage3 fixtures noted) |
| Count changes explained | **PASS** (real Zoho full sync import) |
| Failed-job discrepancy explained | **PASS** (8→13 = +5 heartbeat/timeout) |
| No critical financial job failure | **PASS** |
| Ledgers unchanged | **PASS** (payments=1, wallets=1, handovers=0, cashboxes=0) |
| Admin password not reset | **PASS** |

**Proceed to Part B+ authorized.**

---

## Snapshot: financial / ops counts at gate

```
payments=1 wallets=1 handovers=0 cashboxes=0
customers=6907 invoices=1989
failed_jobs=13
```

## Failed-jobs export path

`/opt/collection-backups/20260716T151456Z-stage51-p1-pregate/failed_jobs_p1_snapshot.json`
