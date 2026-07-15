#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_ROOT="${BACKUP_ROOT:-/opt/collection-backups}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DEST="${BACKUP_ROOT}/${STAMP}"

mkdir -p "${DEST}"

echo "==> Backing up PostgreSQL"
docker compose -f "${ROOT_DIR}/docker-compose.yml" exec -T postgres \
  sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "${DEST}/postgres.dump"

echo "==> Backing up .env and compose config"
cp -a "${ROOT_DIR}/.env" "${DEST}/env.backup" 2>/dev/null || true
cp -a "${ROOT_DIR}/docker-compose.yml" "${DEST}/docker-compose.yml"
cp -a "${ROOT_DIR}/docker/nginx" "${DEST}/nginx"

echo "==> Backing up uploaded files volume (if present)"
docker run --rm \
  -v collection-system_backend_storage:/data:ro \
  -v "${DEST}:/backup" \
  alpine tar czf /backup/storage.tar.gz -C /data . 2>/dev/null || true

# Verify dump is non-empty
if [ ! -s "${DEST}/postgres.dump" ]; then
  echo "ERROR: postgres.dump is empty" >&2
  exit 1
fi

# Compress dump copy for portability
gzip -c "${DEST}/postgres.dump" > "${DEST}/postgres.dump.gz"

# Retention: keep 14 daily directories
ls -1dt "${BACKUP_ROOT}"/20* 2>/dev/null | tail -n +15 | xargs -r rm -rf

echo "Backup complete: ${DEST}"
