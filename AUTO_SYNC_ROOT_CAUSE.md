# AUTO_SYNC_ROOT_CAUSE.md

**Audit phase:** Stage 5.1 — Auto-sync root cause (evidence-based)  
**Date (UTC):** 2026-07-16  
**Runtime:** `/opt/collection-system` on VPS  
**Source SHA:** `c0b1ebd076a65c4c098a0cc37e68825a644f8189`  
**Backup:** `/opt/collection-backups/20260716T140903Z-stage51-audit/`

## Verdict (concrete)

Automatic customer/invoice sync is **not** broken because the scheduler is down, the queue is wrong, Redis is dead, or tokens are expired.

**Root cause:** Incremental Zoho API calls send `last_modified_time` formatted with PHP `Y-m-d\TH:i:sP`, which produces offsets like `2026-07-15T19:28:16+00:00`. Zoho Books for this organization rejects that value with HTTP **400**, Zoho code **2**, message **`Invalid value passed for last_modified_time`**.

**Confirmed working format:** PHP `Y-m-d\TH:i:sO` → `2026-07-15T19:28:16+0000` (offset **without** colon). Read-only probes against live Zoho:

| Format sample | Result |
|---------------|--------|
| `…+00:00` (`P` / ISO `c`) | **FAIL** 400 code 2 |
| `…+0000` (`O`) | **OK** HTTP 200, contacts/invoices returned |
| No `last_modified_time` | **OK** (full list page) |

Code sites:

- `backend/app/Services/Zoho/ZohoCustomerSyncService.php` — `$lastModifiedTime->format('Y-m-d\TH:i:sP')`
- `backend/app/Services/Zoho/ZohoInvoiceSyncService.php` — same

**Amplifying cause:** `RetryFailedZohoSyncJob` runs every **15 minutes** and re-dispatches the same failing incremental jobs, filling `failed_jobs` (**1830** rows: 954 customers + 876 invoices from `2026-07-15 15:00:02` through `2026-07-16 14:03:12`).

---

## Chain analysis (where it stops)

```
scheduler (healthy)
  → schedule:list shows zoho-sync-customers / invoices hourly
  → at :00 dispatches SyncZoho*Job + refresh + retry
  → Redis queue `default`
  → queue-worker consumes jobs (RUNNING → FAIL ~2s)
  → ZohoApiClient GET contacts|invoices with last_modified_time=+00:00
  → Zoho 400  ✗  CHAIN STOPS HERE
  → zoho_sync_jobs status=failed
  → job retries + RetryFailedZohoSyncJob amplify failures
```

Not the root cause (ruled out with evidence):

| Hypothesis | Evidence against |
|------------|------------------|
| Scheduler not running | Container Up 21h; logs show `2026-07-16 14:00:00 Running [zoho-sync-customers] … DONE` (dispatch success) |
| Scheduler not dispatching | Same log lines; `schedule:list` Next Due times present |
| Queue not consuming | queue-worker logs `SyncZohoCustomersJob RUNNING` then `FAIL` |
| Wrong queue name | Jobs reach worker; `jobs` table empty after consume |
| Redis problem | Redis healthy; jobs processed |
| Stale overlap lock | Jobs run every hour and on retry; failures are API 400 not lock skips |
| Dynamic schedule not reloaded | `console.php` intervals match `schedule:list` (hourly / */30 / */15) |
| Token failure | Connection `connected`; probes without bad LMT succeed; accounts/locations OK |
| Rate limit | Error is code 2 invalid value, not throttle |
| Timezone mismatch alone | App/DB/host clocks all UTC ~`2026-07-16T14:12:02+00:00`; Zoho org TZ Asia/Kabul — irrelevant once format `O` works with same instant |
| Swallowed exception | Failures recorded in `failed_jobs`, `zoho_sync_jobs.error_message`, `zoho_api_logs` |

---

## Evidence — Docker / schedule / queues

### `docker compose ps` (2026-07-16 ~14:12 UTC)

| Service | Status |
|---------|--------|
| backend | Up (healthy) |
| frontend | Up (healthy) |
| nginx | Up — host **80/443** |
| postgres | Up (healthy) — not published publicly |
| redis | Up (healthy) — not published publicly |
| queue-worker | Up |
| scheduler | Up |

### `schedule:list` (excerpt)

```
0    * * * *  zoho-sync-customers
0    * * * *  zoho-sync-invoices
*/30 * * * *  zoho-refresh-token
*/15 * * * *  zoho-retry-failed
0    0 * * *  update-promise-statuses
0    0 * * *  payment-reconciliation-daily
```

At ~14:12 UTC, next customer/invoice sync was ~47 minutes later (**15:00 UTC**).

### Manual `schedule:run -v` (~14:10 UTC)

```
INFO  No scheduled commands are ready to run.
```

Expected: mid-hour, nothing due.

### Scheduler log — observed automatic cycle (14:00 UTC)

```
2026-07-16 14:00:00 Running [zoho-sync-customers] ............. 16.52ms DONE
2026-07-16 14:00:00 Running [zoho-sync-invoices] ............... 1.24ms DONE
2026-07-16 14:00:00 Running [zoho-refresh-token] ............... 0.96ms DONE
2026-07-16 14:00:00 Running [zoho-retry-failed] ................ 1.14ms DONE
```

“DONE” here means **Laravel finished dispatching the scheduled event**, not that Zoho sync succeeded.

### Queue worker — same cycle (excerpt)

```
2026-07-16 14:02:28 SyncZohoInvoicesJob RUNNING → 2s FAIL
2026-07-16 14:02:31 SyncZohoCustomersJob RUNNING → 2s FAIL
… continues through …
2026-07-16 14:03:12 SyncZohoInvoicesJob FAIL
```

### Database sync job rows (latest)

| id | type | status | created_at | finished_at | error |
|----|------|--------|------------|-------------|-------|
| 1978 | invoices | failed | 14:01:13 | 14:01:16 | Invalid value passed for last_modified_time |
| 1977 | customers | failed | 14:01:11 | 14:01:13 | same |
| 1976 | invoices | failed | 14:00:10 | 14:00:12 | same |
| 1975 | customers | failed | 14:00:07 | 14:00:10 | same |

### API log sample

- `zoho_api_logs` id 16535: `contacts` GET, http_status **400**, zoho_code **2**, error includes `Invalid value passed for last_modified_time`, created_at `2026-07-16 14:12:00` (diagnostic probe during audit)
- id 16536: `invoices` GET with format **O** → http_status **200** (diagnostic probe)

### Incremental cursor (production)

- Customers `max(zoho_modified_at)` ≈ `2026-07-15 19:29:16`
- Invoices ≈ `2026-07-15 19:29:17`
- Incremental mode therefore always sends `last_modified_time` (cursor − 1 minute in service logic), so **every** scheduled incremental run hits the bad format.

### Config / clocks

- `app.timezone` = **UTC**
- DB `now()` = UTC aligned with container host
- Zoho org timezone (metadata): Asia/Kabul — not the failure mode once `O` works
- Queue: Redis `default` (worker consuming successfully)

---

## Observed automatic sync cycle (required)

| Field | Customers | Invoices |
|-------|-----------|----------|
| Expected run | **2026-07-16 14:00:00 UTC** (hourly) | same |
| Actual dispatch | **14:00:00** (scheduler log) | same |
| Queue start | ~**14:00–14:03** (worker; retries from retry job) | same |
| Completion | **Failed** (~2s per attempt) | **Failed** |
| Records created/updated/skipped | **0 / 0 / 0** (no successful upsert this cycle) | same |
| Errors | Zoho 400 `last_modified_time` | same |
| Next expected run | **15:00:00 UTC** | same |

**Conclusion:** An automatic cycle **did occur**. It is **not working**. Calling manual sync “working” would be incorrect for the same reason: incremental path shares the broken formatter.

Controlled interval testing: mid-hour `schedule:run` correctly idle; no need to wait indefinitely. Format probe proved the fix shape without mutating financial records.

---

## Recommended repair (audit only — do not apply in this phase)

1. Change both sync services to `format('Y-m-d\TH:i:sO')` (or equivalent without colon in offset).  
2. Add a Feature test that asserts the query string uses `+0000`-style offsets.  
3. Temporarily disable or drain `RetryFailedZohoSyncJob` / clear obsolete `failed_jobs` **after** fix (ops-approved).  
4. Observe next `:00` cycle: expect `zoho_sync_jobs.status=completed` and non-zero processed counts if Zoho has deltas (or clean empty incremental).  
5. Do **not** treat full sync without LMT as the long-term fix alone (works but heavier).

---

## Severity

- **Critical** for data freshness and ops reliability.  
- **Must fix before staff use** and **before Stage 6**.  
- Financial risk: medium–high (stale balances/assignments); not duplicate money by itself, but blocks accurate collection.
