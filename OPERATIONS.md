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
cd backend && php artisan test

# Cache
docker compose exec backend php artisan optimize:clear

# After editing .env (Zoho secrets, etc.), recreate affected containers:
docker compose up -d --force-recreate backend queue-worker scheduler

# Failed jobs
docker compose exec backend php artisan queue:failed
docker compose exec backend php artisan queue:retry all

# Backup / restore
./scripts/backup.sh
./scripts/restore.sh /opt/collection-backups/<timestamp>

# App logs
docker compose logs -f backend queue-worker scheduler nginx
```

## Admin password reset (secure)

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

Health: `GET /api/v1/health` and `GET /up`
