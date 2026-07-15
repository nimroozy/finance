# Collection Management System (Zoho Books)

Multi-branch customer debt collection system integrated with Zoho Books.

**Stage status:** Stage 5 (Cash handovers / branch cashboxes) complete. Stage 6 (WhatsApp Cloud API) not started.

## Stack

- Backend: Laravel 12 + Sanctum + Spatie Permission + PostgreSQL + Redis
- Frontend: Next.js 15 + TypeScript + next-intl (EN/FA, RTL)
- Infra: Docker Compose + Nginx + Let's Encrypt

## Quick links

| Doc | Purpose |
|-----|---------|
| [INSTALL.md](INSTALL.md) | Local / first install |
| [DEPLOYMENT.md](DEPLOYMENT.md) | VPS deployment |
| [OPERATIONS.md](OPERATIONS.md) | Day-2 commands |
| [BACKUP_RESTORE.md](BACKUP_RESTORE.md) | Backups |
| [SECURITY.md](SECURITY.md) | Security notes |
| [API.md](API.md) | API overview |
| [docs/openapi.yaml](docs/openapi.yaml) | OpenAPI 3.0 Stage 1–3 |
| [STAGE3_ASSIGNMENTS.md](STAGE3_ASSIGNMENTS.md) | Assignment workflow |
| [STAGE3_STATUSES.md](STAGE3_STATUSES.md) | Supported Stage 3 status codes |
| [STAGE4_PAYMENTS.md](STAGE4_PAYMENTS.md) | Stage 4 overview |
| [PAYMENT_WORKFLOW.md](PAYMENT_WORKFLOW.md) | Draft → confirm → sync |
| [RECEIPTS.md](RECEIPTS.md) | Receipts & verification |
| [COLLECTOR_WALLETS.md](COLLECTOR_WALLETS.md) | Cash collector wallets |
| [ZOHO_PAYMENT_SYNC.md](ZOHO_PAYMENT_SYNC.md) | Zoho customer payments push |
| [PAYMENT_REVERSALS.md](PAYMENT_REVERSALS.md) | Reversal request / approve |
| [PAYMENT_RECONCILIATION.md](PAYMENT_RECONCILIATION.md) | Daily reconciliation |
| [ZOHO_SETUP.md](ZOHO_SETUP.md) | Zoho OAuth and sync |
| [COLLECTOR_GUIDE.md](COLLECTOR_GUIDE.md) | Collector mobile workflow |
| [MANAGER_GUIDE.md](MANAGER_GUIDE.md) | Manager assignment & ops |
| [ROUTES_AND_VISITS.md](ROUTES_AND_VISITS.md) | Routes, visits, GPS, evidence |
| [PROMISE_TO_PAY.md](PROMISE_TO_PAY.md) | Promise statuses & fulfill |
| [WHATSAPP_SETUP.md](WHATSAPP_SETUP.md) | Stage 6 (pending) |

## Production

- Directory: `/opt/collection-system`
- URL: https://finance.mns.af
- Login: `/en/login` or `/fa/login`

## Completed stages

### Stage 1 — Foundation
Authentication, users, roles, branches, audit logs, settings, EN/FA UI, Docker stack, SSL.

### Stage 2 — Zoho Books
OAuth connect/reconnect/disconnect, encrypted tokens, customer & invoice sync, branch mapping, unmapped queue, debtors list, sync jobs + API logs, scheduled queue workers.

### Stage 3 — Assignments & field collection
Collector profiles, customer assignments (manual / bulk / auto), reassignment & cancel, collection routes & stops, field visits with outcomes + GPS risk flags, promise-to-pay (manual fulfill), customer notes, visit evidence uploads, in-app notifications, collector dashboard, assignment/visit/promise reports.

### Stage 4 — Payments / receipts / wallets
Payment draft → confirm with invoice allocations and idempotency; receipts (PDF + public verify token); collector cash wallets (append-only ledger); Zoho customer-payment sync with `ZOHO_PAYMENT_DRY_RUN` (scoped `--live-zoho` verify for labeled tests only); reversal request/approve (no payment delete); daily reconciliation job; payment reports.

### Stage 5 — Cash handovers / branch cashboxes
Eligible cash payment selection; handover draft/submit/approve/reject; collector wallet debit + cashbox credit on approval only; handover numbering; cashbox transfers & reconciliation foundations; custody-aware reversal gate for handed-over payments.

## Remaining stages

6 WhatsApp Cloud API · 7 Offline PWA · 8 Reports/Hardening
