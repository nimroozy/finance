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

Rotate secrets by regenerating `APP_KEY` / DB / Redis / Zoho credentials carefully and restarting containers after updates.

