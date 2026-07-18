# Stage 10.4 — Console / network audit

**Artifact summarizer:** `scripts/acceptance-summarize-artifacts.mjs`  
**Playwright guards:** fail on unexpected console errors and HTTP 500.

## Allowlist (tiny)

* React DevTools download message
* favicon.ico noise
* Next.js `Failed to fetch RSC payload` / `Falling back to browser navigation` (soft navigation under load; pages still render)

## Latest real acceptance

| Metric | Value |
|--------|-------|
| Unexpected console errors after allowlist | **0** (suite EXIT 0) |
| Unexpected HTTP 500 | **0** |
| Result | **pass** |
