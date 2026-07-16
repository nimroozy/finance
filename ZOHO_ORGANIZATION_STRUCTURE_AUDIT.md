# ZOHO_ORGANIZATION_STRUCTURE_AUDIT.md

**Audit phase:** Stage 5.1  
**Date (UTC):** 2026-07-16  
**Organization:** Mobin Net — Zoho org id **`929233857`**  
**Connection:** `zoho_connections.status=connected`, data center **us**  
**Runtime probes:** read-only Zoho API via production `ZohoApiClient` (no local branch creation, no remaps)

## Executive finding

This Zoho Books organization does **not** expose native **Branches** via `settings/branches` (HTTP **404**, Zoho code 5 “Invalid URL Passed”).

Operational “branch-like” structure is represented as:

1. **Locations** (8) — primary geographic units (Kabul, Nimruz, Kandahar, …)  
2. **Reporting tags** (2) — including inactive tag with stringified options  
3. Possibly **customer custom fields** (org metadata shows custom field slots; not fully inventory-mapped this pass)

Local app “branches” (3 rows) are **application constructs**. Mapping tables that would link Zoho structure → local branches are currently **empty**.

---

## API probe results

| Entity | Endpoint | HTTP | Zoho code | Count | Pagination | Permission failure? | Consumed by code? | Local table? | UI? | Mapping mode |
|--------|----------|------|-----------|-------|------------|---------------------|-------------------|--------------|-----|--------------|
| Native branches | `GET settings/branches` | **404** | 5 Invalid URL | 0 | n/a | No (URL invalid for org/edition) | Mapping method `zoho_branch` exists but useless here | `zoho_branch_mappings` | Branch mappings page offers `zoho_branch` | Manual (IDs typed) |
| Locations | `GET locations` | **200** | 0 | **8** | list | No | Via `zoho_location` mapping method | mappings table | Same page method `zoho_location` | Manual `zoho_value` = location id |
| Reporting tags | `GET settings/tags` | **200** | 0 | **2** | page_context present | No | `reporting_tag` method + `zoho_reporting_tag_mappings` | both mapping tables | Branch mappings page method only; **no dedicated tag-option UI** | Manual / free-text |
| Tag options | nested under tags | 200 | 0 | see note | — | No | Yes if IDs known | `zoho_reporting_tag_mappings` | Missing picker | Manual |
| Chart of accounts | `GET chartofaccounts` | **200** | 0 | **672** | — | No | Payment account config paths | not mirrored wholesale | No CoA browser | Manual settings |
| Payment modes | `GET settings/paymentmodes` | **200** | 0 | **5** | — | No | Payment mode mappings | `zoho_payment_mode_mappings` (**1** row) | Payment settings UI missing | Manual |
| Org variables | `GET settings/orgvariables` | **200** | 0 | 0 | — | No | No | — | — | — |

### Locations (sanitized)

| location_id | location_name |
|-------------|-----------------|
| 303766000000172007 | Headquarter |
| 303766000000299061 | Buldak |
| 303766000000132956 | Ghazni |
| 303766000000132990 | Helmand |
| 303766000000093054 | Kabul |
| 303766000000132921 | Kandahar |
| 303766000000093149 | Nimruz |
| 303766000000396827 | test |

### Reporting tags (sanitized)

| tag_id | tag_name | associated_with | is_active | tag_options shape |
|--------|----------|-----------------|-----------|-------------------|
| 303766000000000333 | Group | item | false | **string**: `"Best market,New market"` (not an array of option objects) |
| (2nd tag) | present in count=2 | — | — | Probe truncated after TypeError on string options in first audit script; treat options as **non-structured** until option-id API confirmed |

**Implication:** Code that expects `tag_options` as an array of `{tag_option_id, tag_option_name}` will mis-handle this org’s payload. Dedicated option-ID discovery may need alternate Zoho endpoints or contact/invoice payload samples.

### Payment modes

Five modes returned (ids + names available via API). Local `zoho_payment_mode_mappings` = **1**.

---

## How “branches” are represented in this org

| Model | Present? | Recommendation |
|-------|----------|----------------|
| Native Zoho Books branches | **No** (404) | Do not build process around `settings/branches` |
| Locations | **Yes (8)** | Strong candidate for geographic branch mapping (`zoho_location` → local branch) |
| Reporting tag options | **Partial / awkward shape** | Investigate further before relying on them as branch keys |
| Customer custom fields | Possible | Inspect live contact payloads for location/branch custom fields |
| Combination | **Likely** | Location for geography + tags for product grouping |

**Do not assume** one model. Do **not** auto-create local branches in this audit phase.

---

## Local branch mapping design (current code)

`ZohoBranchMappingService::resolveBranchId`:

1. Iterate active `zoho_branch_mappings` by method: `reporting_tag`, `custom_field`, `zoho_branch`, `zoho_location`, … comparing `zoho_value` (and optional label).  
2. Fall back to `zoho_reporting_tag_mappings` matching `tag_id` + `tag_option_id` from payload.  
3. Else return `null` → customer/invoice marked unmapped.

UI `/zoho/branch-mappings` requires operators to type **raw Zoho IDs/values** into `zoho_value` — no location picker, no tag-option picker.

### Production mapping reality (2026-07-16)

| Metric | Value |
|--------|-------|
| Local branches | **3** — `01` Nimruz, `S3A` STAGE3-TEST A, `S3B` STAGE3-TEST B |
| Zoho locations | **8** |
| Zoho reporting tags | **2** |
| Native Zoho branches | **0** (API unavailable) |
| `zoho_branch_mappings` | **0** |
| `zoho_reporting_tag_mappings` | **0** |
| Customers mapped (`branch_id` not null) | **6** |
| Customers unmapped (`branch_id` null / `is_unmapped`) | **4137** |
| Invoices mapped | **1** |
| Invoices unmapped | **1853** |
| Customer by branch_id | null=4137, S3A(3)=4, S3B(4)=1, Nimruz(1)=1 |
| Invoice by branch_id | null=1853, Nimruz(1)=1 |
| Conflicts / cross-branch inconsistencies | No mapping rows → no mapping conflicts; **consistency = almost everything unmapped**. Stage3 test branches hold most of the few mapped customers. |

### Behavioral notes

| Question | Finding |
|----------|---------|
| How local branches created? | App install / admin CRUD — not from Zoho locations |
| How customers get `branch_id`? | Sync upsert → `resolveBranchId`; without mappings → null + `is_unmapped` |
| How invoices get `branch_id`? | Same |
| Raw Zoho IDs typed manually? | **Yes** on branch-mappings form |
| Names vs IDs? | Service matches **IDs/values** in `zoho_value`, not friendly names alone |
| Duplicate mappings possible? | Schema/API allow multiple active rows; first match wins (order-dependent) |
| Unmapped customers hidden? | Unmapped list page + isolation rules exist; debtors/assignments should not expose cross-branch data — **ops impact: almost no mapped debtors** |
| Branch changes propagate? | Only on subsequent sync/remap; no remapping run while mappings empty |
| 4143 / 1854 consistency? | **Not mapped consistently** — effectively unmapped warehouse of Zoho data |

---

## Severity & repair sequencing (after audit approval)

1. **Critical / before staff use:** Choose mapping strategy (prefer **locations** for this org). Create mappings for Nimruz (and others as approved). Re-resolve existing customers/invoices under controlled job with backup.  
2. **High / before Stage 6:** Dedicated reporting-tag mapping UI if tags carry branch meaning; fix option parsing; hide or disable `zoho_branch` method when API 404.  
3. **Do not** create local branches blindly 1:1 with all 8 locations without business sign-off (includes `test`).

---

*Read-only probes only. No local branch creation. No customer/invoice remapping performed.*
