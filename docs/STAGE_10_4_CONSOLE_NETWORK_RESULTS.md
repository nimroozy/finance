# Stage 10.4 — Console / network audit

Real acceptance attaches per-test console and network guards (`attachFailureGuards`).

## Result

* Unexpected console errors (non-allowlisted): **0** in the green pass  
* Unexpected HTTP 500 responses: **0**  
* Allowlist (tiny): React DevTools, favicon 401 noise, Next RSC prefetch fallbacks, Playwright→`nginx` hostname access-control checks  

## Notes

Allowlist is not used to hide application failures. Missing chunks, hydration crashes, validation mismatches, and blank pages still fail tests.
