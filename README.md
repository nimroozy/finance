# Collection Management System (Zoho Books)

Multi-branch customer debt collection system integrated with Zoho Books.

**Stage status:** Stage 1 (Foundation) deployed.

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
| [ZOHO_SETUP.md](ZOHO_SETUP.md) | Stage 2 (pending) |
| [WHATSAPP_SETUP.md](WHATSAPP_SETUP.md) | Stage 6 (pending) |

## Production

- Directory: `/opt/collection-system`
- URL: `https://finance.mns.af` (HTTP fallback until DNS/SSL ready)
- Login: `/en/login` or `/fa/login`

## Stage 1 features

- Authentication with account lockout
- Roles and permissions
- Multi-branch access control (backend enforced)
- Audit log foundation
- Persian / English UI
- Docker production stack

## Stages remaining

2 Zoho · 3 Assignments/Visits · 4 Payments/Receipts · 5 Handovers · 6 WhatsApp · 7 Offline PWA · 8 Reports/Hardening
