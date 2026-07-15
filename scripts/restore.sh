#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="${1:-}"

if [ -z "${SRC}" ] || [ ! -f "${SRC}/postgres.dump" ]; then
  echo "Usage: $0 /opt/collection-backups/TIMESTAMP" >&2
  exit 1
fi

echo "WARNING: This will restore the database from ${SRC}"
read -r -p "Type YES to continue: " confirm
if [ "${confirm}" != "YES" ]; then
  echo "Aborted"
  exit 1
fi

docker compose -f "${ROOT_DIR}/docker-compose.yml" exec -T postgres \
  sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists' < "${SRC}/postgres.dump"

echo "Restore finished."
