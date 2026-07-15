#!/usr/bin/env bash
set -euo pipefail

# Run on the VPS from /opt/collection-system after code sync.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [ ! -f .env ]; then
  echo "Missing .env — copy from .env.example and fill secrets." >&2
  exit 1
fi

# shellcheck disable=SC1091
set -a
source .env
set +a

if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "base64:" ]; then
  echo "Generating APP_KEY..."
  KEY=$(docker compose run --rm --no-deps backend php -r "echo 'base64:'.base64_encode(random_bytes(32));")
  sed -i "s|^APP_KEY=.*|APP_KEY=${KEY}|" .env
fi

docker compose build --pull
docker compose up -d

echo "==> Waiting for backend health..."
for i in $(seq 1 60); do
  if docker compose exec -T backend curl -sf http://127.0.0.1:8080/up >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

docker compose exec -T backend php artisan migrate --force
docker compose exec -T backend php artisan db:seed --class=RolePermissionSeeder --force || true

if ! docker compose exec -T backend php artisan tinker --execute="echo \\App\\Models\\SystemSetting::where('key','setup_completed')->value('value');" 2>/dev/null | grep -q true; then
  PASS="${ADMIN_PASSWORD:?Set ADMIN_PASSWORD in .env}"
  docker compose exec -T backend php artisan app:install \
    --name="${ADMIN_NAME:-Super Administrator}" \
    --email="${ADMIN_EMAIL:-admin@finance.mns.af}" \
    --username="${ADMIN_USERNAME:-admin}" \
    --password="${PASS}" \
    --company="${COMPANY_NAME:-MNS Collection}"
fi

docker compose ps
echo "Deploy complete."
