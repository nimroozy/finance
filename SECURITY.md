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

Rotate secrets by regenerating `APP_KEY` / DB / Redis / Zoho credentials carefully and restarting containers after updates.

