# Stage 10.4 — Console / network audit

**Sources:** Playwright page console + response listeners; `scripts/acceptance-summarize-artifacts.mjs`  
**Artifact:** `artifacts/acceptance/console/console-network-summary.json`

## Failure rules

Real acceptance fails on unexpected:

* Browser console error / unhandled rejection / hydration error
* Missing JS chunk
* HTTP 500
* Unexpected 401/403/404 (tests may annotate expected statuses)
* Infinite loading / blank page / dead controls (covered in workflow specs)

## Allowlist (tiny)

* React DevTools download message
* favicon.ico noise

No broad allowlist is used to force green results.

## Latest summary

| Metric | Value |
|--------|-------|
| Unexpected console errors | _(fill)_ |
| Unexpected network errors | _(fill)_ |
| Result | _(fill)_ |
