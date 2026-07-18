# Stage 10.4 — Route crawler results

**Runner:** `frontend/scripts/acceptance-route-crawler.mjs`  
**Auth:** API login + `auth-storage` init (not fragile UI login)  
**Locales:** en, fa  
**Viewports:** desktop 1440×900, mobile 390×844, small-mobile 320×700  

## Result

| Metric | Value |
|--------|-------|
| Failures | **0** |
| Routes recorded | **192** |
| Exit code | **0** |

Seeds use real App Router paths (`/assignments`, `/noc/services`, `/crm/dashboard`, `/inventory/dashboard`, `/reports/collection`, `/users`, …). Dynamic `/{id}` detail routes are not auto-expanded.

Harmless Next RSC `http://nginx/...` access-control prefetch noise is allowlisted; blank pages, 404, 500, and unexpected console errors still fail the crawler.
