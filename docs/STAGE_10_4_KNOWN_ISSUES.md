# Stage 10.4 — Known issues

**Critical defects:** none known  
**High-severity defects:** none known

Do not claim zero bugs. Medium/cosmetic items below.

## Medium

1. **Deep payment write-path E2E** — full draft→confirm→reversal against Zoho sandbox is gated behind `ZOHO_WRITE_MODE=test_adapter` / optional `E2E_ZOHO_WRITE`; not every payment bullet is a dedicated Playwright scenario yet. Backend payment suites remain authoritative for calculation safety.
2. **Collections field GPS** — visit GPS recording remains environment/device dependent; mobile acceptance verifies UI reachability, not live GPS hardware.
3. **Notification content crawl** — assignment notification matrix is covered primarily by backend events/tests; UI mark-read paths need continued expansion in the real suite.
4. **Attachment camera capture** — mobile camera upload is platform-dependent; file upload permission/denial covered more strongly in backend Attachment tests.

## Cosmetic

1. Login page still lacks dedicated `data-testid` hooks (selectors use `#login` / role names).
2. Some queue tables use generic `main` visibility assertions where row testids are not yet universal.
3. Frontend build metadata (`NEXT_PUBLIC_APP_COMMIT_SHA`) is plumbed; older cached images may show null until rebuild.

## Deferred (not Stage 11)

* Unified dashboards / operational reporting (Stage 11)
* SAS Radius integration (later stage)
* Live WhatsApp send during acceptance (explicitly disabled)
