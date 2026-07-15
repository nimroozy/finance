# Security

- HTTPS (Let's Encrypt) after DNS
- Secrets only in `.env` (gitignored)
- Account lockout after 5 failed logins (15 minutes)
- Strong password rules (12+ mixed case, number, symbol)
- Force password change on first admin login
- Spatie permissions + Laravel policies + branch query scoping
- API rate limiting on login
- Security headers via Nginx
- Postgres/Redis not published publicly
- Production `APP_DEBUG=false`
- Audit log for auth and administrative actions

Rotate secrets by regenerating `APP_KEY` / DB / Redis passwords carefully and restarting containers after updates. Prefer a dedicated rotate command in Stage 8.
