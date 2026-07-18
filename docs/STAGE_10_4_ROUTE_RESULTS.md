# Stage 10.4 — Route crawler results

**Tool:** `frontend/scripts/acceptance-route-crawler.mjs`  
**Invoked by:** `./scripts/run-acceptance.sh`  
**Machine output:** `artifacts/acceptance/routes/route-results.json`

## Matrix

For role `ACCEPTANCE-admin`, locales `en`/`fa`, viewports `desktop` / `mobile` / `small-mobile`, the crawler seeds:

`/apps`, `/customers`, `/payments`, `/collections`, `/tasks`, `/tickets`, `/crm`, `/installations`, `/inventory`, `/services`, `/services/noc`, `/reports`, `/settings/users`, `/search`

and follows same-origin visible links (capped).

## Failure rules

Fail on HTTP 404/5xx, blank render, hydration/application errors, or unexpected console errors (tiny allowlist only).

## Latest summary

| Metric | Value |
|--------|-------|
| Routes exercised | _(from artifact)_ |
| Failures | _(from artifact)_ |
| Unexpected 403 | _(from artifact)_ |
| Blank pages | _(from artifact)_ |

See also `docs/FUNCTIONAL_ROUTE_AUDIT.md` for the maintained human-readable audit.
