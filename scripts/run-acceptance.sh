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
  set +e
  "${COMPOSE[@]}" exec -T backend php artisan test --log-junit "${RESULTS_DIR}/junit/backend.xml" | tee "${RESULTS_DIR}/reports/backend.txt"
  BACKEND_EXIT=$?
  set -e
  return "${BACKEND_EXIT}"
}

run_mocked_e2e() {
  log "Running mocked Playwright regression"
  set +e
  (
    cd frontend
    npm ci --silent
    npx playwright install chromium
    npm run e2e:mocked -- --reporter=list,junit 2>&1 | tee "${RESULTS_DIR}/reports/mocked-e2e.txt"
  )
  MOCKED_EXIT=$?
  set -e
  return "${MOCKED_EXIT}"
}

run_real_acceptance() {
  log "Running real Playwright acceptance (six projects)"
  export E2E_ACCEPTANCE=1
  export BASE_URL="${BASE_URL:-${PLAYWRIGHT_BASE_URL}}"
  export PLAYWRIGHT_BASE_URL="${BASE_URL}"
  export E2E_USER E2E_PASSWORD
  set +e
  (
    cd frontend
    ACCEPTANCE_ARTIFACT_DIR="${RESULTS_DIR}" npm run e2e:acceptance
  ) 2>&1 | tee "${RESULTS_DIR}/reports/real-acceptance.txt"
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
  (
    cd frontend
    node scripts/acceptance-route-crawler.mjs \
      --base-url "${BASE_URL:-${PLAYWRIGHT_BASE_URL}}" \
      --user "${E2E_USER}" \
      --password "${E2E_PASSWORD}" \
      --out "${RESULTS_DIR}/routes/route-results.json"
  ) | tee "${RESULTS_DIR}/reports/route-crawler.txt"
}

run_console_network_audit() {
  log "Summarizing console/network audits from Playwright output"
  node scripts/acceptance-summarize-artifacts.mjs \
    --results-dir "${RESULTS_DIR}" \
    --out "${RESULTS_DIR}/console/console-network-summary.json"
}

write_summary() {
  local backend_exit=$1 mocked_exit=$2 real_exit=$3 overall=$4
  node -e "
const fs=require('fs');
const path=process.argv[1];
const summary={
  stage:'10.4-production-acceptance-closure',
  generated_at:new Date().toISOString(),
  exits:{backend:Number(process.argv[2]), mocked:Number(process.argv[3]), real:Number(process.argv[4]), overall:Number(process.argv[5])},
  note:'Detailed pass/fail/skip totals are parsed from suite reporters when present.'
};
fs.writeFileSync(path, JSON.stringify(summary,null,2));
" "${SUMMARY_JSON}" "${backend_exit}" "${mocked_exit}" "${real_exit}" "${overall}"
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
