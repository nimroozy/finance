# UI_FUNCTIONAL_AUDIT.md

Audit document lives on `cursor/stage-5-1-audit` / PR #5.

## Repair status (Stage 5.1 P0)

P0 repair on `cursor/stage-5-1-p0-repair` addresses auto-sync timestamp format, retry storm, org structure sync, and location mapping UI. See `AUTO_SYNC_REPAIR.md` and `ZOHO_LOCATION_MAPPING.md`.

## Stage 5.1 P1 status

| Area | Status | Notes |
|------|--------|-------|
| Grouped navigation + RTL/LTR | **verified** | Overview / Debt / Field / Payments / Cash / Admin |
| Role dashboards (summary API) | **verified** | Super Admin / Branch Manager / Collector cards |
| KPI cards + page sections | **verified** | Shared UI primitives |
| Server-side data table shell | **fixed** | Pagination/search/export hooks; full presets deferred |
| Alerts page | **fixed** | Linked from dashboard |
| Location mapping review wizard | **fixed** | `/zoho/location-mapping` |
| Mapping conflicts UI | **fixed** | `/zoho/mapping-conflicts` |
| Branch mappings page | **verified** | Improved; raw IDs diagnostic-only |
| Sync health actions | **verified** | Sync now / retry / cleanup confirmations |
| Transfers UI | **fixed** | List / create / detail approve-send-receive-reverse |
| Bank deposits UI | **fixed** | Uses cash transfer `type=bank_deposit` |
| Reconciliation UI | **fixed** | Daily workflow + drilldown links |
| Admin credential recovery | **verified** | `admin:reset-password` + OPERATIONS.md; deploy never resets |
| Generic API error surfaces | **fixed** | i18n friendly messages; support ref when present |
| Unmapped / debtors / invoices | **verified** | Existing Stage 4–5 pages retained |
| Assignment / visits / promises | **verified** | Existing flows |
| Payments / receipts / handovers | **verified** | Existing flows |
| “Coming soon” operational nav | **hidden** | No unlabeled stubs in primary nav |
| Saved filter presets | **deferred** | API + UI schema ready later |
| Column visibility persistence | **deferred** | Client toggle only |
| Full Playwright authenticated flows | **deferred** | Smoke + mocked shells in CI; prod smoke separate |
| WhatsApp / Stage 6 | **deferred** | Explicitly out of scope |

Legend: **fixed** | **hidden** | **deferred** | **verified**
