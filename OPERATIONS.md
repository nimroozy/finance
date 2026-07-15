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

# Failed jobs
docker compose exec backend php artisan queue:failed
docker compose exec backend php artisan queue:retry all

# Backup / restore
./scripts/backup.sh
./scripts/restore.sh /opt/collection-backups/<timestamp>

# App logs
docker compose logs -f backend queue-worker scheduler nginx
```

Health: `GET /api/v1/health` and `GET /up`
