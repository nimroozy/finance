# ISP Operations Platform (Zoho Books + Collections)

Multi-branch ISP operations platform. Collections, Zoho Books accounting sync, and WhatsApp notifications are live foundations; CRM, ticketing, inventory, and Radius adapters follow a staged roadmap.

**Branch staff goal:** complete daily work without logging into Zoho Books or individual SAS Radius servers.

**Sources of truth:** Zoho Books = accounting · SAS Radius = subscriber auth/service (until adapters) · This app = operational workflows.

## Stage status

| Stage | Focus | Status |
|-------|--------|--------|
| 1–5.3 | Foundation → collections → cash → ownership → prefix mapping cleanup | Complete |
| **6** | WhatsApp Cloud API + notification orchestration | Foundation deployed (live Meta verify when credentials set) |
| 7 | Ticketing & tasks | Not started |
| 8 | CRM, sales, installations | Not started |
| 9 | Inventory, assets, sites, towers | Not started |
| 10 | SAS Radius branch adapters | Not started |
| 11 | Unified dashboards & reporting | Not started |

**Do not auto-start Stage 7 after Stage 6.**

## Architecture docs

| Document | Purpose |
|----------|---------|
| [docs/ISP_PLATFORM_ARCHITECTURE.md](docs/ISP_PLATFORM_ARCHITECTURE.md) | Platform vision, domains, departments, coupling rules |
| [docs/DOMAIN_BOUNDARIES.md](docs/DOMAIN_BOUNDARIES.md) | Service boundaries, events, permissions |
| [docs/STAGE_ROADMAP.md](docs/STAGE_ROADMAP.md) | Staged delivery plan |
| [docs/TICKETING_MODEL.md](docs/TICKETING_MODEL.md) | Stage 7 tickets vs tasks |
| [docs/CRM_INSTALLATION_MODEL.md](docs/CRM_INSTALLATION_MODEL.md) | Stage 8 CRM + installs |
| [docs/INVENTORY_MODEL.md](docs/INVENTORY_MODEL.md) | Stage 9 immutable stock + assets |
| [docs/STAGE_9_INVENTORY_ASSETS.md](docs/STAGE_9_INVENTORY_ASSETS.md) | Stage 9 delivery overview |
| [docs/RADIUS_INTEGRATION_MODEL.md](docs/RADIUS_INTEGRATION_MODEL.md) | Stage 10 branch adapters |
| [docs/ZOHO_ACCOUNTING_BOUNDARY.md](docs/ZOHO_ACCOUNTING_BOUNDARY.md) | Zoho SoT vs local ops |
| [docs/openapi.yaml](docs/openapi.yaml) | OpenAPI |

## Stack

- Backend: Laravel 12 + Sanctum + Spatie Permission + PostgreSQL + Redis
- Frontend: Next.js 15 + TypeScript + next-intl (EN/FA, RTL)
- Infra: Docker Compose + Nginx + Let's Encrypt
- Feature flags: `frontend/src/config/feature-flags.ts` (roadmap placeholders only for unfinished modules)

## Quick links (operations)

| Doc | Purpose |
|-----|---------|
| [INSTALL.md](INSTALL.md) | Local / first install |
| [DEPLOYMENT.md](DEPLOYMENT.md) | VPS deployment |
| [OPERATIONS.md](OPERATIONS.md) | Day-2 commands |
| [BACKUP_RESTORE.md](BACKUP_RESTORE.md) | Backups |
| [SECURITY.md](SECURITY.md) | Security notes |
| [API.md](API.md) | API overview |
| [WHATSAPP_SETUP.md](WHATSAPP_SETUP.md) | Stage 6 WhatsApp |
| [ZOHO_SETUP.md](ZOHO_SETUP.md) | Zoho OAuth and sync |
| [COLLECTOR_GUIDE.md](COLLECTOR_GUIDE.md) | Collector mobile workflow |
| [MANAGER_GUIDE.md](MANAGER_GUIDE.md) | Manager assignment & ops |

## Production

- Directory: `/opt/collection-system`
- URL: https://finance.mns.af
- Login: `/en/login` or `/fa/login`

## Completed stages (summary)

### Stage 1 — Foundation
Authentication, users, roles, branches, audit logs, settings, EN/FA UI, Docker stack, SSL.

### Stage 2 — Zoho Books
OAuth, customer & invoice sync, branch mapping, unmapped queue, debtors, sync jobs + API logs.

### Stage 3 — Assignments & field collection
Assignments, routes, visits, promises, notes, evidence, in-app notifications.

### Stage 4 — Payments / receipts / wallets
Payment confirm, receipts, collector wallets, Zoho payment sync, reversals, reconciliation.

### Stage 5 — Cash handovers / branch cashboxes
Handovers, cashboxes, transfers, custody-aware reversals.

### Stage 5.1–5.3
Sync hardening, permanent ownership, temporary assignments, customer prefix mapping, number backfill & conflict cleanup.

### Stage 6 — WhatsApp (foundation)
Cloud API connection, webhooks, templates, queued outbound, delivery status, notification rules/orchestrator, basic inbound storage. Full support inbox deferred to Stage 7.

## Remaining stages

See [docs/STAGE_ROADMAP.md](docs/STAGE_ROADMAP.md). Next planned: **Stage 7 Ticketing & tasks** (manual kickoff only).
