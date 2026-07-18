# Stage 10.4 — Known issues

**Critical defects:** none known  
**High-severity defects:** none known

## Medium

1. **Customer search by Zoho contact ID / phone** — list search matches name/customer number (`ACC-CUST-001`) but not `ACCEPTANCE-ZOHO-1` or phone in the current customers index filter. Real acceptance uses supported search keys; global search API still covers Zoho ID/phone fixtures.
2. **Deep installation / inventory transfer / custody / repair / stock-count matrix** — covered primarily by backend Stage 9 PHPUnit; Playwright exercises product create + goods receipt + stock reconcile identity.
3. **Mocked Playwright optional skips** — two `health endpoint is reachable via same host when configured` skips when health host is unset (optional).

## Cosmetic

1. Login page lacks dedicated `data-testid` hooks.
2. Next.js RSC prefetch against Docker hostname `nginx` emits allowlisted access-control pageerrors under Playwright.
3. Some CRM/service detail UI asserts accept API-backed evidence when panel hydration is slow.

## Deferred

* Stage 11 dashboards — not started  
* SAS Radius — not connected  
* Live WhatsApp send during acceptance — disabled  
* Live Zoho write — optional (`E2E_ZOHO_WRITE=1`), skipped in required suite  
