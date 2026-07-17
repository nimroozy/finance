# Production Smoke Test — Stage 10.1

Run after VPS deploy of `cursor/stage-10-1-integrated-stable`. Login redirect to `/apps` is **OK**.

## Preflight

1. Confirm `.deployed-sha` matches tip SHA.
2. `GET https://<host>/api/v1/system/version` → `stage` = `10.1-integrated-stable`.
3. `GET /api/v1/health` → 200.
4. Compose: backend/frontend healthy; nginx/postgres/redis/queue/scheduler up.
5. `grep -i radius /opt/collection-system/.env` → empty / no live Radius.

## Auth + launcher

| Step | Expect |
|------|--------|
| Open `/en/login` | Login form |
| Sign in (ops admin) | Redirect to `/en/apps` (or `default_app_id` target) |
| Open `/fa/apps` | RTL (`dir=rtl`), launcher visible |
| Open `/en/apps` | Favorites / all apps grid; Services app visible if permitted |

## Services smoke

| Step | Expect |
|------|--------|
| `/en/services/dashboard` | Dashboard metrics; Radius deferred banner if present |
| Navigate packages / list | No 500; permission gates intact |

## Module quick hits (permission-aware)

| Path | Expect |
|------|--------|
| `/en/tickets` | List loads |
| `/en/crm/dashboard` | CRM KPIs |
| `/en/inventory/dashboard` | Stock metrics |
| `/en/payments` | Payments list (collections) |

## Count integrity (deploy gate)

Record **pre** and **post** deploy:

- Financial: `payments`, `cash_handover_requests`, `collector_wallets`, `branch_cashboxes`, `payment_reversals`
- Ops: `customers`, `tickets`, `tasks`, `installations`, `crm_leads`
- Inventory: `SUM(on_hand)` on stock balances
- Services: `services` row count (+ packages if seeded)

**Post counts must MATCH pre** (unless a documented seeder intentionally adds catalog rows — never mutate financial rows).

## Backup gate

- Pre-deploy labeled backup under `/opt/collection-backups/<STAMP>-stage10-1-predeploy/`
- Post-deploy labeled backup + `./scripts/backup.sh`

## Explicit non-goals

- No Stage 11 work
- No Radius enablement
- No live Meta WhatsApp sends required for smoke
