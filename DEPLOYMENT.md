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
# Stage 3–4 add Spatie permissions (assignments.*, payments.*, receipts.*, wallets.*, reversals.*, etc.).
# Always refresh the seeder after migrate so roles pick up new permissions:
docker compose exec backend php artisan db:seed --class=RolePermissionSeeder --force
```

Stage 3 stores visit evidence and Stage 4 stores receipts under the Laravel `local` disk (`storage/app/private/…`). Ensure the backend volume persists `storage/` across deploys.

### Stage 4 env notes

| Env | Default | Purpose |
|-----|---------|---------|
| `ZOHO_PAYMENT_DRY_RUN` | `false` | When `true`, confirmed payments do **not** POST to Zoho Books; sync records a fake `DRYRUN-…` id (`zoho_sync_status=dry_run`). Prefer `true` until live payment push is validated. |
| `PAYMENT_DRAFT_EXPIRY_HOURS` | `24` | Draft TTL |
| `PAYMENT_WALLET_ENABLED` | `true` | Cash wallet credits on confirm |
| `PAYMENT_RECONCILIATION_DAILY` | `true` | Config flag for daily reconciliation |

After changing `.env`: `docker compose up -d --force-recreate backend queue-worker scheduler`.

## Security rules

- Never publish Postgres or Redis ports
- Never commit `.env`
- Keep UFW enabled
- Zoho client secret only in `.env`
