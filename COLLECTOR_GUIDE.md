# Collector guide

Field workflow for users with the **Collector** role (mobile-friendly `/collector/*` pages).

Production: https://finance.mns.af → `/en/collector` or `/fa/collector`.

## Pages

| Path | Purpose |
|------|---------|
| `/collector` | Dashboard — active assignments, visits today, open promises, today’s routes |
| `/collector/assignments` | Own assignments; open detail; call / WhatsApp shortcuts |
| `/collector/assignments/[id]` | Assignment detail; accept / mark viewed; start visit |
| `/collector/visits` | Visit history |
| `/collector/visits/new` | Log a visit (outcome, notes, GPS, optional photo / promise) |
| `/collector/routes` · `/collector/routes/[id]` | Published / in-progress routes; start & complete |
| `/collector/promises/new` | Create a standalone promise |
| `/collector/notifications` | In-app notifications |

## Visit form

1. Open from an assignment (preferred) or enter customer ID.
2. Capture GPS (one-shot browser geolocation).
3. Choose outcome from API list (`GET /visits/outcomes` — see [ROUTES_AND_VISITS.md](ROUTES_AND_VISITS.md)).
4. Notes required for: `wrong_address`, `customer_moved`, `disputed_balance`, `other`.
5. Outcome `promise_to_pay` requires promise amount + date.
6. Outcome `follow_up` requires follow-up date.
7. Optional evidence file (jpg/png/webp/pdf, max 10 MB) after visit create.
8. Submit → `POST /visits` (+ optional `POST /visits/{id}/files`).

## GPS denied / unavailable

- Browser shows “Location permission denied” / “unavailable”.
- Visit **still submits** without coordinates.
- Server clears lat/lng when `gps_permission_state=denied`.
- No continuous GPS tracking — position is requested only when capturing a visit.

## WhatsApp (`wa.me`)

On assignment lists, phone / WhatsApp numbers open `https://wa.me/{digits}` (and `tel:` for calls). This is a deep link only — **not** Stage 6 automated WhatsApp messaging.

## What collectors can do

Permissions from `RolePermissionSeeder` for role `collector`:

- View own branch customers / invoices / assignments / visits / routes / promises / notes / notifications
- Create visits (`visits.create`) and promises (`promises.create`)
- Upload & view evidence for own visits
- Accept / mark viewed on own assignments
- Start / complete own published routes
- Cancel their own open promises (create+cancel share route middleware)

## What collectors cannot do

- Assign, bulk-assign, reassign, or cancel assignments
- Manage collectors, routes (create/publish/reorder), or fulfill / supersede promises
- View other collectors’ data (assignment isolation)
- Export debtors / run manager reports
- Record payments, print receipts, or use wallets (**Stage 4 — not started**)
- Configure Zoho or manage users/settings

If something is missing, ask a branch manager — do not share login credentials.
