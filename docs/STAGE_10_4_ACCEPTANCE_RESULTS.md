# Stage 10.4 — Acceptance results

**Final tip:** `ceb84eff63848097da8caff1252847b297fb2145`  
**Environment:** VPS sidecar `http://127.0.0.1:18080` / DB `collection_acceptance` / Redis DB 2  
**Zoho mode:** `test_adapter`  
**WhatsApp send / Radius:** disabled  

## Suite totals (separate)

| Suite | Passed | Failed | Skipped |
|-------|--------|--------|---------|
| Backend (`php artisan test` / PHPUnit) | **301** | **0** | **0** |
| Mocked Playwright (`npm run e2e:mocked`) | **60** | **0** | **2** (optional health probe when host health unset) |
| Real acceptance (`npm run e2e:acceptance`) | **108** | **0** | **0 required** (+**6** optional Zoho write) |

Required real acceptance skipped count: **0**.

## Browser matrix

| Project | Result |
|---------|--------|
| desktop-en | passed |
| desktop-fa | passed |
| mobile-en | passed |
| mobile-fa | passed |
| small-mobile-en | passed |
| small-mobile-fa | passed |

## Workflow coverage (real)

Auth edges, launcher, customers, CRM Zoho link, service activation/queues/NOC, global search, admin version, stock reconcile, collections assignment/route/visit, payments draft/confirm/reverse (test adapter), tickets lifecycle, tasks offer→complete, inventory receive + ledger reconcile, notifications, attachments, administration users/Zoho status.

## Artifacts

Under `artifacts/acceptance/` on the acceptance host: HTML report, JUnit, JSON summary, screenshots/traces on failure, route crawler JSON, console/network summaries, DB assert JSON.
