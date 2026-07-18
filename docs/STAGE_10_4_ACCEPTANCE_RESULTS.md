# Stage 10.4 — Acceptance results

**Stage:** `10.4-production-acceptance-closure`  
**Runner tip:** `99aa37b4b89ffff54888df6284de3e70f05be7c6`  
**Environment:** VPS sidecar (`collection_acceptance` DB on shared postgres, Redis DB 2, URL `http://127.0.0.1:18080`)  
**Zoho mode:** `test_adapter` (no uncontrolled production writes)  
**WhatsApp send / Radius:** disabled

## Suite totals (separate — do not combine)

| Suite | Passed | Failed | Skipped |
|-------|--------|--------|---------|
| Backend (`php artisan test`) | **301** | 0 | 0 |
| Mocked Playwright (`npm run e2e:mocked`) | retained Stage 10.3 regression (not re-gated this run) | — | — |
| Real acceptance (`npm run e2e:acceptance`) | **54** | **0** | **0 required** |

Optional external Zoho write: **6 skipped** (one per project) — reported separately; required skipped count = **0**.

## Browser matrix

| Project | Result |
|---------|--------|
| desktop-en | **passed** |
| desktop-fa | **passed** |
| mobile-en | **passed** |
| mobile-fa | **passed** |
| small-mobile-en | **passed** |
| small-mobile-fa | **passed** |

## Workflow coverage (real acceptance)

| Area | Result |
|------|--------|
| Authentication (UI + invalid/disabled) | passed |
| App launcher primary apps + counts | passed |
| Customers Zoho-mirrored search + DB assert | passed |
| CRM lead create + Zoho link (API+UI) + DB assert | passed |
| Service activation checklist evidence (API+UI) | passed |
| Change-request queue + NOC workspace | passed |
| Global search (`/operations/search`) | passed |
| Administration system version / no secrets | passed |
| Stock reconciliation | passed (0=0 on acceptance reset) |

## Artifacts

`/opt/collection-system/artifacts/acceptance/` (host) — HTML/JUnit/JSON reports, screenshots/traces on failure, DB assert JSON. Not committed (binaries).
