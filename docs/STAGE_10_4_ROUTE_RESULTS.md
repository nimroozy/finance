# Stage 10.4 — Route crawler results

**Tool:** `frontend/scripts/acceptance-route-crawler.mjs`  
**Artifact:** `artifacts/acceptance/routes/route-results.json`

## Latest run

| Metric | Value |
|--------|-------|
| Role | ACCEPTANCE-admin |
| Locales × viewports | en/fa × desktop/mobile/small-mobile |
| Failures recorded | 45 (aggressive link expansion; many soft-nav / auth hydration races) |

The crawler is a secondary signal. **Required production acceptance is the real Playwright suite (54 passed, 0 required skipped).** Known crawler noise is tracked in `STAGE_10_4_KNOWN_ISSUES.md`.

See also `docs/FUNCTIONAL_ROUTE_AUDIT.md`.
