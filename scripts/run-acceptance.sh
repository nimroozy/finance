#!/usr/bin/env bash
# Stage 10.4 production acceptance runner.
# Isolated stack only. Exits non-zero on any required failure. No silent skips.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

# ACCEPTANCE_SIDECAR=1 → reuse host postgres/redis (VPS low-memory path)
if [[ "${ACCEPTANCE_SIDECAR:-0}" == "1" ]]; then
  COMPOSE=(docker compose -f docker-compose.acceptance.sidecar.yml --env-file .env.acceptance)
else
  COMPOSE=(docker compose -f docker-compose.acceptance.yml --env-file .env.acceptance --profile acceptance)
fi
RESULTS_DIR="${ROOT_DIR}/artifacts/acceptance"
SUMMARY_JSON="${RESULTS_DIR}/summary.json"
mkdir -p "${RESULTS_DIR}"/{screenshots,traces,reports,junit,db,routes,console}

log() { printf '[acceptance] %s\n' "$*"; }
fail() { printf '[acceptance] ERROR: %s\n' "$*" >&2; exit 1; }

require_env_file() {
  if [[ ! -f .env.acceptance ]]; then
    fail "Missing .env.acceptance — copy from .env.acceptance.example"
  fi
  # shellcheck disable=SC1091
  set -a
  source .env.acceptance
  set +a
}

validate_env() {
  local missing=()
  [[ "${APP_ENV:-}" == "acceptance" ]] || missing+=("APP_ENV=acceptance")
  [[ "${DB_DATABASE:-}" == "collection_acceptance" ]] || missing+=("DB_DATABASE=collection_acceptance")
  [[ "${CACHE_PREFIX:-}" == "collection_acceptance" ]] || missing+=("CACHE_PREFIX=collection_acceptance")
  [[ "${E2E_ACCEPTANCE:-}" == "1" ]] || missing+=("E2E_ACCEPTANCE=1")
  [[ "${WHATSAPP_SEND_ENABLED:-}" == "false" ]] || missing+=("WHATSAPP_SEND_ENABLED=false")
  [[ "${RADIUS_ENABLED:-}" == "false" ]] || missing+=("RADIUS_ENABLED=false")
  [[ -n "${DB_PASSWORD:-}" ]] || missing+=("DB_PASSWORD")
  [[ -n "${REDIS_PASSWORD:-}" ]] || missing+=("REDIS_PASSWORD")
  [[ -n "${E2E_USER:-}" ]] || missing+=("E2E_USER")
  [[ -n "${E2E_PASSWORD:-}" ]] || missing+=("E2E_PASSWORD")
  [[ -n "${BASE_URL:-${PLAYWRIGHT_BASE_URL:-}}" ]] || missing+=("BASE_URL")
  if ((${#missing[@]})); then
    fail "Required acceptance env missing/invalid: ${missing[*]}"
  fi
  if [[ "${DB_DATABASE}" == "collection" ]] || [[ "${DB_DATABASE}" == *"prod"* ]]; then
    fail "Refusing to run against non-acceptance database: ${DB_DATABASE}"
  fi
}

ensure_app_key() {
  if [[ -z "${APP_KEY:-}" || "${APP_KEY}" == "base64:" ]]; then
    log "Generating APP_KEY for acceptance env"
    KEY=$("${COMPOSE[@]}" run --rm --no-deps backend php -r "echo 'base64:'.base64_encode(random_bytes(32));")
    if grep -q '^APP_KEY=' .env.acceptance; then
      sed -i "s|^APP_KEY=.*|APP_KEY=${KEY}|" .env.acceptance
    else
      echo "APP_KEY=${KEY}" >> .env.acceptance
    fi
    # shellcheck disable=SC1091
    set -a; source .env.acceptance; set +a
  fi
}

ensure_acceptance_database() {
  if [[ "${ACCEPTANCE_SIDECAR:-0}" != "1" ]]; then
    return 0
  fi
  log "Ensuring collection_acceptance database exists on shared postgres"
  local pg_user="${ACCEPTANCE_PG_USER:-collection}"
  docker compose -f docker-compose.yml exec -T postgres \
    psql -U "${pg_user}" -d postgres -tc "SELECT 1 FROM pg_database WHERE datname='collection_acceptance'" \
    | grep -q 1 \
    || docker compose -f docker-compose.yml exec -T postgres \
      psql -U "${pg_user}" -d postgres -c "CREATE DATABASE collection_acceptance OWNER ${pg_user};"
}

start_stack() {
  log "Starting acceptance Docker stack (sidecar=${ACCEPTANCE_SIDECAR:-0})"
  ensure_acceptance_database
  "${COMPOSE[@]}" build
  "${COMPOSE[@]}" up -d
  log "Waiting for backend health"
  for _ in $(seq 1 90); do
    if "${COMPOSE[@]}" exec -T backend curl -sf http://127.0.0.1:8080/up >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  fail "Acceptance backend did not become healthy"
}

reset_database() {
  log "Resetting acceptance database (migrate:fresh + seeders)"
  "${COMPOSE[@]}" exec -T backend php artisan migrate --force
  "${COMPOSE[@]}" exec -T backend php artisan acceptance:reset --force
}

verify_health() {
  log "Verifying health + acceptance ping"
  local base="${BASE_URL:-${PLAYWRIGHT_BASE_URL}}"
  curl -sf "${base}/api/v1/health" | tee "${RESULTS_DIR}/health.json" >/dev/null
  curl -sf "${base}/api/v1/acceptance/ping" | tee "${RESULTS_DIR}/acceptance-ping.json" >/dev/null
}

run_backend_tests() {
  log "Running backend PHPUnit"
  # Acceptance images install Composer --no-dev; ensure PHPUnit is available in-container.
  set +e
  "${COMPOSE[@]}" exec -T backend sh -lc '
    if [ ! -x vendor/bin/phpunit ]; then
      composer install --no-interaction --prefer-dist
    fi
    if php artisan list --raw 2>/dev/null | grep -q "^test "; then
      php artisan test --log-junit /var/www/html/storage/app/acceptance-backend.xml
    else
      ./vendor/bin/phpunit --log-junit /var/www/html/storage/app/acceptance-backend.xml
    fi
  ' | tee "${RESULTS_DIR}/reports/backend.txt"
  BACKEND_EXIT=$?
  "${COMPOSE[@]}" exec -T backend sh -lc \
    'test -f storage/app/acceptance-backend.xml && cat storage/app/acceptance-backend.xml' \
    > "${RESULTS_DIR}/junit/backend.xml" 2>/dev/null || true
  set -e
  return "${BACKEND_EXIT}"
}

run_playwright_in_docker() {
  local script=$1
  local out_log=$2
  local base="${BASE_URL:-${PLAYWRIGHT_BASE_URL}}"
  # Prefer host npm when available; otherwise use Playwright Docker image (VPS path).
  if command -v npm >/dev/null 2>&1; then
    (
      cd frontend
      npm ci --silent
      npx playwright install chromium
      ACCEPTANCE_ARTIFACT_DIR="${RESULTS_DIR}" \
        E2E_ACCEPTANCE=1 \
        BASE_URL="${base}" \
        PLAYWRIGHT_BASE_URL="${base}" \
        E2E_USER="${E2E_USER}" \
        E2E_PASSWORD="${E2E_PASSWORD}" \
        bash -lc "${script}"
    ) 2>&1 | tee "${out_log}"
    return "${PIPESTATUS[0]}"
  fi

  local network
  if [[ "${ACCEPTANCE_SIDECAR:-0}" == "1" ]]; then
    network="collection-acceptance-net"
    base="http://nginx"
  else
    network="$("${COMPOSE[@]}" ps --format '{{.Name}}' | head -1 | xargs -I{} docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}}{{end}}' {} | head -1)"
    base="http://nginx"
  fi

  docker run --rm --network "${network}" \
    -v "${ROOT_DIR}/frontend:/work" \
    -v "${RESULTS_DIR}:/artifacts" \
    -w /work \
    -e E2E_ACCEPTANCE=1 \
    -e BASE_URL="${base}" \
    -e PLAYWRIGHT_BASE_URL="${base}" \
    -e E2E_USER="${E2E_USER}" \
    -e E2E_PASSWORD="${E2E_PASSWORD}" \
    -e ACCEPTANCE_ARTIFACT_DIR=/artifacts \
    mcr.microsoft.com/playwright:v1.61.1-jammy \
    bash -lc "npm ci --silent && ${script}" 2>&1 | tee "${out_log}"
  return "${PIPESTATUS[0]}"
}

run_mocked_e2e() {
  log "Running mocked Playwright regression"
  set +e
  run_playwright_in_docker "npm run e2e:mocked -- --reporter=list,junit" "${RESULTS_DIR}/reports/mocked-e2e.txt"
  MOCKED_EXIT=$?
  set -e
  return "${MOCKED_EXIT}"
}

run_real_acceptance() {
  log "Running real Playwright acceptance (six projects)"
  set +e
  run_playwright_in_docker "npm run e2e:acceptance" "${RESULTS_DIR}/reports/real-acceptance.txt"
  REAL_EXIT=$?
  set -e
  return "${REAL_EXIT}"
}

run_db_assertions() {
  log "Running database assertions + stock reconciliation"
  "${COMPOSE[@]}" exec -T backend php artisan acceptance:assert \
    --entity=customer --key=zoho_contact_id --value=ACCEPTANCE-ZOHO-1 \
    --expect=status=active | tee "${RESULTS_DIR}/db/customer-assert.json"
  "${COMPOSE[@]}" exec -T backend php artisan acceptance:assert --stock | tee "${RESULTS_DIR}/db/stock-reconciliation.json"
}

run_route_crawler() {
  log "Running route crawler"
  local base="${BASE_URL:-${PLAYWRIGHT_BASE_URL}}"
  set +e
  if command -v node >/dev/null 2>&1; then
    (
      cd frontend
      node scripts/acceptance-route-crawler.mjs \
        --base-url "${base}" \
        --user "${E2E_USER}" \
        --password "${E2E_PASSWORD}" \
        --out "${RESULTS_DIR}/routes/route-results.json"
    ) | tee "${RESULTS_DIR}/reports/route-crawler.txt"
    ROUTE_EXIT=$?
  else
    local network="collection-acceptance-net"
    docker run --rm --network "${network}" \
      -v "${ROOT_DIR}/frontend:/work" \
      -v "${RESULTS_DIR}:/artifacts" \
      -w /work \
      mcr.microsoft.com/playwright:v1.61.1-jammy \
      bash -lc "npm ci --silent && node scripts/acceptance-route-crawler.mjs --base-url http://nginx --user '${E2E_USER}' --password '${E2E_PASSWORD}' --out /artifacts/routes/route-results.json" \
      | tee "${RESULTS_DIR}/reports/route-crawler.txt"
    ROUTE_EXIT=$?
  fi
  set -e
  return "${ROUTE_EXIT}"
}

run_console_network_audit() {
  log "Summarizing console/network audits from Playwright output"
  if command -v node >/dev/null 2>&1; then
    node scripts/acceptance-summarize-artifacts.mjs \
      --results-dir "${RESULTS_DIR}" \
      --out "${RESULTS_DIR}/console/console-network-summary.json"
    return $?
  fi
  docker run --rm \
    -v "${ROOT_DIR}/scripts:/scripts:ro" \
    -v "${RESULTS_DIR}:/artifacts" \
    mcr.microsoft.com/playwright:v1.61.1-jammy \
    bash -lc "node /scripts/acceptance-summarize-artifacts.mjs --results-dir /artifacts --out /artifacts/console/console-network-summary.json"
}

write_summary() {
  local backend_exit=$1 mocked_exit=$2 real_exit=$3 overall=$4
  python3 - <<PY
import json
from datetime import datetime, timezone
summary={
  "stage":"10.4-production-acceptance-closure",
  "generated_at":datetime.now(timezone.utc).isoformat(),
  "exits":{"backend":${backend_exit},"mocked":${mocked_exit},"real":${real_exit},"overall":${overall}},
  "note":"Detailed pass/fail/skip totals are parsed from suite reporters when present."
}
open("${SUMMARY_JSON}","w",encoding="utf-8").write(json.dumps(summary,indent=2))
PY
  log "Wrote ${SUMMARY_JSON}"
}

cleanup_acceptance_data() {
  log "Cleaning acceptance mutable data (reset fixtures)"
  "${COMPOSE[@]}" exec -T backend php artisan acceptance:reset --force || true
}

main() {
  require_env_file
  validate_env
  ensure_app_key
  start_stack
  reset_database
  verify_health

  BACKEND_EXIT=0
  MOCKED_EXIT=0
  REAL_EXIT=0
  set +e
  run_backend_tests; BACKEND_EXIT=$?
  run_mocked_e2e; MOCKED_EXIT=$?
  run_real_acceptance; REAL_EXIT=$?
  run_db_assertions; DB_EXIT=$?
  run_route_crawler; ROUTE_EXIT=$?
  run_console_network_audit; AUDIT_EXIT=$?
  set -e

  OVERALL=0
  for code in "${BACKEND_EXIT}" "${MOCKED_EXIT}" "${REAL_EXIT}" "${DB_EXIT:-1}" "${ROUTE_EXIT:-1}" "${AUDIT_EXIT:-1}"; do
    if [[ "${code}" -ne 0 ]]; then OVERALL=1; fi
  done

  write_summary "${BACKEND_EXIT}" "${MOCKED_EXIT}" "${REAL_EXIT}" "${OVERALL}"
  cleanup_acceptance_data

  if [[ "${OVERALL}" -ne 0 ]]; then
    fail "Acceptance failed (backend=${BACKEND_EXIT} mocked=${MOCKED_EXIT} real=${REAL_EXIT} db=${DB_EXIT:-?} routes=${ROUTE_EXIT:-?} audit=${AUDIT_EXIT:-?})"
  fi
  log "Acceptance passed"
}

main "$@"
