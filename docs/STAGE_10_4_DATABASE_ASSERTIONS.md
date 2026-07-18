# Stage 10.4 — Database assertions

Acceptance-only helpers (`AcceptanceDbAssertions`, `POST /api/v1/acceptance/assert`, `php artisan acceptance:assert`).

## Assertable entities

`customer`, `lead`, `service`, `user`, `audit`, `ticket`, `task`, `payment`, `assignment`, `product`

## Green-pass checks

| Check | Result |
|-------|--------|
| Customer `ACCEPTANCE-ZOHO-1` status=active | ok |
| Lead / service fixture asserts in Playwright | ok |
| Assignment / payment / ticket / task / product after writes | ok |
| Audit `ticket.created` | ok |
| Production `/api/v1/acceptance/*` | **404** |
| Production ACCEPTANCE users | **0** |
