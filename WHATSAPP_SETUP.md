# WhatsApp setup (Stage 6)

WhatsApp Business Cloud API foundation and notification orchestration.

Full support-desk inbox, ticket creation from WhatsApp, and staff bot commands belong to **Stage 7 (Ticketing)**. Stage 6 stores inbound messages and supports outbound templates, delivery status, rules, and opt-out.

See also:

- [docs/STAGE_ROADMAP.md](docs/STAGE_ROADMAP.md)
- [docs/ISP_PLATFORM_ARCHITECTURE.md](docs/ISP_PLATFORM_ARCHITECTURE.md)
- [docs/DOMAIN_BOUNDARIES.md](docs/DOMAIN_BOUNDARIES.md)

## Environment variables

Set on the VPS `/opt/collection-system/.env` (never commit secrets):

| Variable | Purpose |
|----------|---------|
| `WHATSAPP_PHONE_NUMBER_ID` | Meta phone number ID |
| `WHATSAPP_BUSINESS_ACCOUNT_ID` | WABA ID |
| `WHATSAPP_ACCESS_TOKEN` | Permanent/system user token (encrypted at rest in DB connection row) |
| `WHATSAPP_VERIFY_TOKEN` | Webhook verification challenge token |
| `WHATSAPP_API_VERSION` | Graph API version (e.g. `v21.0`) |
| `WHATSAPP_WEBHOOK_SECRET` | App secret for `X-Hub-Signature-256` (recommended in production) |
| `WHATSAPP_DEFAULT_COUNTRY` | Default country for local numbers without `+` (default `AF`) |
| `WHATSAPP_OUTGOING_PAUSED` | Kill-switch for outbound (`true`/`false`) |

Template names are documented in `.env.example`.

## Webhook URL

`https://finance.mns.af/api/v1/whatsapp/webhook`

Configure verify token to match `WHATSAPP_VERIFY_TOKEN`.

## Admin UI

- `/settings/whatsapp` — connection, pause/resume, template sync, send test
- `/settings/whatsapp-rules` — event → template routing (WhatsApp channel off by default until templates approved)
- `/whatsapp/messages`, `/whatsapp/templates`, `/whatsapp/failures`
- `/whatsapp/inbox` — **basic inbound storage only**

## Architecture note

Domain services emit `BusinessNotificationRequested`. `NotificationOrchestrator` routes to in-app and queued WhatsApp jobs. Do not call Meta Graph API inside payment, handover, or wallet database transactions.
