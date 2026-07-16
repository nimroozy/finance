# Security

- HTTPS (Let's Encrypt) on `finance.mns.af`
- Secrets only in `.env` (gitignored)
- Bearer token API auth (Sanctum); stateful cookie CSRF disabled for SPA token login
- Account lockout after 5 failed logins (15 minutes)
- Strong password rules (12+ mixed case, number, symbol)
- Force password change on first admin login; tokens revoked after password change
- Active/locked user checks on every authenticated request
- Spatie permissions + Laravel policies + branch query scoping
- Users list scoped to shared branches for non-global roles
- Only Super Administrators may assign Super Administrator role
- API rate limiting on login
- Security headers via Nginx + HSTS
- Postgres/Redis not published publicly
- Production `APP_DEBUG=false`
- Zoho tokens encrypted at rest; OAuth state validated; API logs sanitized (no Authorization headers)
- Audit log for auth and administrative actions

### Stage 3 additions

- **Collector assignment isolation** — Collector-only users are scoped via `CollectorOwnedScope` to rows with their `collector_id` (assignments, visits, routes, promises, notes). They cannot list or act on other collectors’ work.
- **Secure evidence downloads** — Visit files are stored on the private `local` disk (`storage/app/private/visit-evidence/…`), not under public web roots. Access is only through authenticated `GET /api/v1/files/{id}/download` plus `UploadedVisitFilePolicy` (collector must own the visit; managers restricted to branch).
- **GPS privacy** — Location is captured **once per visit** (browser `getCurrentPosition`), not continuous tracking. If permission is `denied`, coordinates are cleared server-side. GPS is optional; visits still save with `gps_permission_state`.
- **Branch isolation** — Non-global roles remain constrained by `BelongsToBranchScope` for Stage 3 entities. Branch managers only see their branches; Super Admin / Central Finance retain cross-branch visibility where permitted.

### Stage 4 additions

- **Idempotency** — `POST /payments/draft` requires `idempotency_key` (per user, TTL from `PAYMENT_IDEMPOTENCY_TTL_HOURS`, default 48h). Same key + same payload returns the original draft; key reuse with a different payload is rejected. Confirm may send an optional idempotency key (`confirm:{uuid}:{key}`).
- **Receipt verification tokens** — Each receipt gets a random 48-char `verification_token`. Public `GET /api/v1/verify-receipt/{token}` (throttled) returns limited fields (number, amount, masked customer name, branch) — not full payment internals. PDFs/HTML stay on the private disk; authenticated users need `receipts.view`.
- **Wallet ledger immutability** — `CollectorWalletTransaction` rows are append-only (no updates/deletes after insert). Credits/debits adjust balances via new ledger rows only.
- **No payment delete** — There is no payment delete API. Confirmed payments are reversed (`status=reversed`) with allocation unwind, optional wallet debit, and receipt void. Soft-deletes on the model are not used as the operational cancel path.
- **Scoped live Zoho** — Collectors always respect `ZOHO_PAYMENT_DRY_RUN`. Controlled live posts use `app:stage4-live-zoho-verify --live-zoho` (`forceLive` in-process only). Real Zoho payment IDs are always voided on approved reversal (even when dry-run remains enabled for create).
- **Admin password deploy guard** — Existing Super Administrator passwords are never overwritten by deploy/`app:install`. Explicit `admin:reset-password` or `app:install --reset-password` required. `.secrets/admin-pass` is emergency-only.

Rotate secrets by regenerating `APP_KEY` / DB / Redis / Zoho credentials carefully and restarting containers after updates.


## Branch payment mapping

Live Zoho customer payments require a validated branch account mapping (`ready`). Collectors cannot alter mappings. Handover never creates Zoho customer payments.
