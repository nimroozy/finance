#!/usr/bin/env bash
set -euo pipefail

# Sync application code to the VPS without destroying host-only secrets/data.
# Never include .secrets, production .env, uploads, logs, or database dumps.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET="${1:-root@209.38.194.184:/opt/collection-system/}"

rsync -az --delete \
  --exclude '.git/' \
  --exclude '.secrets/' \
  --exclude '.env' \
  --exclude '.env.*' \
  --exclude 'backend/.env' \
  --exclude 'backend/vendor/' \
  --exclude 'backend/node_modules/' \
  --exclude 'backend/storage/logs/' \
  --exclude 'backend/storage/framework/cache/' \
  --exclude 'backend/storage/framework/sessions/' \
  --exclude 'backend/storage/framework/views/' \
  --exclude 'backend/storage/app/private/' \
  --exclude 'frontend/node_modules/' \
  --exclude 'frontend/.next/' \
  --exclude 'backups/' \
  --exclude '*.dump' \
  --exclude '*.dump.gz' \
  "${ROOT_DIR}/" "${TARGET}"

echo "Synced to ${TARGET}"
echo "Host-only paths preserved: .secrets/, .env, private storage, backups"
