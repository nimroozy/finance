# Deployment

## Target

- Host: `209.38.194.184`
- Domain: `finance.mns.af`
- Path: `/opt/collection-system`
- Backups: `/opt/collection-backups`

## DNS / SSL

`finance.mns.af` must resolve to the VPS. HTTPS is terminated by Compose Nginx using Let's Encrypt certs in `docker/nginx/certs/`. HTTP redirects to HTTPS. Renew via Certbot deploy hook.

## First-time VPS setup

Handled by `scripts/vps-bootstrap.sh` (swap, Docker, UFW 22/80/443).

## Deploy / update

```bash
cd /opt/collection-system
./scripts/deploy.sh
# or:
docker compose build && docker compose up -d
docker compose exec backend php artisan migrate --force
# Stage 3 added many Spatie permissions (assignments.*, visits.*, routes.*, etc.).
# Always refresh the seeder after migrate so roles pick up new permissions:
docker compose exec backend php artisan db:seed --class=RolePermissionSeeder --force
```

Stage 3 also stores visit evidence under the Laravel `local` disk (`storage/app/private/visit-evidence/…`). Ensure the backend volume persists `storage/` across deploys.

## Security rules

- Never publish Postgres or Redis ports
- Never commit `.env`
- Keep UFW enabled
- Zoho client secret only in `.env`
