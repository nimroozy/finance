# Stage 5.2 Final Report

**Branch:** `cursor/stage-5-2-customer-ownership`  
**SHA:** `ade8d52de31611d091583566c64898cee0a2c371` (+ receivables count fix commit)  
**Draft PR:** https://github.com/nimroozy/finance/pull/8 → `cursor/stage-5-1-p1-ui`  
**Do not merge:** #4 #5 #6 #7 #8 until reviewed. **Stage 6 not started.**

## Backups
- Pre-deploy: `/opt/collection-backups/20260716T161339Z-stage52-predeploy/`
- Post-deploy: `/opt/collection-backups/20260716T161716Z-stage52-postdeploy/`

## Migrations
- `2026_07_16_170000_stage52_customer_ownership_and_branch_payment.php` (batch 9)

## Production verification
- Admin login OK; deploy did not reset password
- Nimruz branch payment mapping: Zoho account `303766000000000358` (Undeposited Funds), mode Bank Remittance → readiness **ready**
- Permanent ownership assigned on labeled `STAGE5 TEST CUSTOMER - DO NOT USE` → collector 35
- Temporary assignment to collector 37, then expired → restored to 35
- Work queue source `permanent`, balance 10.0000
- Branch receivables Nimruz receivable ~696562 AFN; owned=1
- Payments remain **1**; handovers **0**
- Last sync job invoices **completed**
- Public ports **22/80/443**; containers healthy
- Live Zoho customer payment not forced against production customers (dry-run mode / dedicated test only). Handover Zoho-guard covered by automated tests (`Http::assertNothingSent` + audit `cash_handover.completed_without_zoho_payment`)

## Counts
| Metric | Pre (partial) | Notes |
|--------|---------------|-------|
| Customers | 6917 | growth from scheduled sync |
| Payments | 1 | unchanged |
| Handovers | 0 | unchanged |

## Tests
- Backend: **147 passed** (545 assertions)
- Playwright: **34 passed** (2 skipped)
- Frontend lint/build: pass
- OpenAPI: validates (warnings only)

## Known issues / deferred
- Bulk UI still uses ID entry for speed; dropdown enrichment can follow
- Full authenticated Playwright against live Zoho deferred
- Stage 6 / WhatsApp not started
