# Operations

```bash
cd /opt/collection-system

# Status
docker compose ps
docker compose logs -f --tail=200

# Restart
docker compose restart
docker compose up -d --build

# Stop
docker compose down

# Migrations
docker compose exec backend php artisan migrate --force

# Tests (inside backend image with dev deps; prefer CI/local)
# NEVER run phpunit / php artisan test against the production Postgres DB.
# Local/CI only:
cd backend && php artisan test
# On the VPS, use a one-off image with sqlite and do not mount production .env, or run tests locally.
# Production images are built with --no-dev (no PHPUnit).

# Cache
docker compose exec backend php artisan optimize:clear

# After editing .env (Zoho secrets, etc.), recreate affected containers:
docker compose up -d --force-recreate backend queue-worker scheduler

# Failed jobs (prefer classified cleanup over blind retry-all)
docker compose exec backend php artisan queue:failed
docker compose exec backend php artisan zoho:failed-jobs-cleanup
docker compose exec backend php artisan zoho:failed-jobs-cleanup --apply
# docker compose exec backend php artisan queue:retry all   # only for non-permanent failures

# Zoho auto-sync ops (Stage 5.1 P0)
# See AUTO_SYNC_OPERATIONS.md, AUTO_SYNC_REPAIR.md, ZOHO_LOCATION_MAPPING.md, FAILED_JOB_CLEANUP.md
docker compose exec backend php artisan zoho:scheduler-tick
docker compose exec backend php artisan zoho:sync-organization-structure --apply
docker compose exec backend php artisan zoho:reprocess-customer-branches
docker compose exec backend php artisan zoho:reprocess-invoice-branches
# UI: /en/zoho/sync-health  /en/zoho/branch-mappings

# Backup / restore
./scripts/backup.sh
./scripts/restore.sh /opt/collection-backups/<timestamp>

# App logs
docker compose logs -f backend queue-worker scheduler nginx
```

## Admin credential lifecycle

Rules:

1. **Deployments never reset an existing Super Administrator password.**
2. `.env` `ADMIN_PASSWORD` is **first-time bootstrap only** (when no Super Admin exists).
3. `/opt/collection-system/.secrets/admin-pass` is an **optional emergency reset source**, not a permanent authentication source and not synced on every deploy.
4. After any emergency `admin:reset-password`, operators must log in and change the password (or keep `force_password_change`).
5. Rotating credentials requires an **explicit** `php artisan admin:reset-password` (or `app:install --reset-password`).

Root cause fixed (Stage 4→5 gate): older `scripts/deploy.sh` re-ran `app:install` whenever a fragile `setup_completed` tinker|grep check failed during container recreation. That overwrote the DB hash with `ADMIN_PASSWORD` from `.env`, which no longer matched a previously rotated `.secrets/admin-pass` value.

Deploy now checks whether a Super Administrator **user** already exists and skips install entirely.

## Admin credential recovery

If the Super Administrator password is unknown (for example `.env` `ADMIN_PASSWORD` no longer matches the database hash):

```bash
mkdir -p /opt/collection-system/.secrets
python3 - <<'PY'
import secrets, string
alphabet = string.ascii_letters + string.digits + "!@#$%^&*"
while True:
    p = "".join(secrets.choice(alphabet) for _ in range(20))
    if (any(c.islower() for c in p) and any(c.isupper() for c in p)
        and any(c.isdigit() for c in p) and any(c in "!@#$%^&*" for c in p)):
        open("/opt/collection-system/.secrets/admin-pass", "w").write(p)
        break
PY
chmod 600 /opt/collection-system/.secrets/admin-pass

docker cp /opt/collection-system/.secrets/admin-pass collection-system-backend-1:/tmp/admin-pass
docker compose exec -T backend php artisan admin:reset-password admin@finance.mns.af \
  --password-file=/tmp/admin-pass --unlock --force-change --no-interaction
docker compose exec -T backend rm -f /tmp/admin-pass

docker cp /opt/collection-system/.secrets/admin-pass collection-system-backend-1:/tmp/smoke-pass
docker compose exec -T backend php artisan app:stage3-smoke --password-file=/tmp/smoke-pass --keep
docker compose exec -T backend rm -f /tmp/smoke-pass
```

Login URL: `https://finance.mns.af/en/login` (email `admin@finance.mns.af` or username `admin`). Do not print the password file contents in logs.

## Stage 3 — field collection

**Promise status job** — scheduled daily as `UpdatePromiseStatusesJob` (`update-promise-statuses` in `routes/console.php`). Open promises move between `active` / `due_soon` / `due_today` / `overdue` from `promised_date`. Requires the Compose `scheduler` + `queue-worker` containers running.

**Visit evidence** — files land on the `local` disk at:

`storage/app/private/visit-evidence/{visit_id}/{uuid}.{ext}`

Downloads only via authenticated `GET /api/v1/files/{id}/download` (not public URLs).

**GPS / map config** (`config/collection.php`, env overrides):

| Key / env | Default | Purpose |
|-----------|---------|---------|
| `COLLECTION_GPS_WARNING_METERS` | `200` | Distance ≥ this → `gps_risk_level=warning` |
| `COLLECTION_GPS_HIGH_RISK_METERS` | `1000` | Distance ≥ this → `high_risk` |
| `COLLECTION_MAP_PROVIDER` | `leaflet` | `leaflet` or `google` |
| `MAP_GOOGLE_API_KEY` | — | Required if provider is `google` |
| `COLLECTION_MAP_TILE_URL` | OSM tiles | Leaflet tile URL |
| `COLLECTION_VISIT_EDIT_GRACE_MINUTES` | `30` | Visit edit grace window |
| `COLLECTION_BULK_SYNC_THRESHOLD` | `50` | Bulk assign sync vs queue split |
| `COLLECTION_PROMISE_DUE_SOON_DAYS` | `3` | Days before due → `due_soon` |
| `COLLECTION_PROMISE_MAX_ACTIVE` | `1` | Configured max open promises per customer |

After changing these env values: `docker compose up -d --force-recreate backend queue-worker scheduler`.

## Stage 4 — payments / receipts / wallets

**Stage 4 smoke** (labeled `STAGE4-TEST` records; requires `--password-file`):

```bash
# Prefer dry-run Zoho so smoke never posts real customerpayments:
docker compose exec -T backend php artisan app:stage4-smoke \
  --password-file=/tmp/smoke-pass --dry-run-zoho
# Optional: --keep to commit STAGE4-TEST rows (default rolls back)
```

`--dry-run-zoho` forces `config('zoho.payments.dry_run')=true` for that run. Without the flag, behavior follows `ZOHO_PAYMENT_DRY_RUN` / stored `zoho_payment_dry_run` setting. Operational default: always pass `--dry-run-zoho` on shared/prod-like databases.

**Dry-run Zoho payments** — with `ZOHO_PAYMENT_DRY_RUN=true` (or smoke `--dry-run-zoho`), `ZohoPaymentSyncService` skips the live Books POST and stores a `DRYRUN-…` id with `zoho_sync_status=dry_run`. Keep dry-run on until live push is intentionally enabled.

**Receipt files** — private `local` disk:

`storage/app/private/receipts/{uuid}.html`  
`storage/app/private/receipts/{uuid}.pdf`

Authenticated download: `GET /api/v1/receipts/{uuid}/pdf`. Public verify: `GET /api/v1/verify-receipt/{token}` (and web `/verify-receipt/{token}`).

**Daily reconciliation** — scheduled as `RunPaymentReconciliationJob` (`payment-reconciliation-daily` in `routes/console.php`). Requires Compose `scheduler` + `queue-worker`.

**Never** run PHPUnit against production Postgres. Prefer `app:stage4-smoke` for VPS checks.

Health: `GET /api/v1/health` and `GET /up`

## Temporary assignment expiry

```bash
php artisan assignments:expire-temporary
```

Scheduled hourly. Restores permanent ownership in the work queue.
