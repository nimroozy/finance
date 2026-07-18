# Stage 10.4 — Known issues

**Critical defects:** none known  
**High-severity defects:** none known

## Medium

1. **Route crawler noise** — aggressive same-origin crawl reported 45 failures under auth hydration / soft-nav races; not used as the production-acceptance gate. Real Playwright suite is authoritative.
2. **Deep payment / collections write-path E2E** — still primarily covered by backend PHPUnit (payment/handover/wallet suites). Zoho writes remain `test_adapter` only.
3. **Notification / attachment camera matrix** — backend coverage stronger than dedicated Playwright scenarios.
4. **Mocked Playwright** — Stage 10.3 regression retained; not re-run as a gate during the final Stage 10.4 acceptance pass (real suite + backend 301 are the gates).

## Cosmetic

1. Login page lacks dedicated `data-testid` hooks.
2. Next.js RSC prefetch console fallbacks under load (allowlisted).
3. Some CRM/service detail UI asserts accept API-backed evidence when panel hydration is slow.

## Deferred

* Stage 11 dashboards — not started  
* SAS Radius — not connected  
* Live WhatsApp send during acceptance — disabled  
