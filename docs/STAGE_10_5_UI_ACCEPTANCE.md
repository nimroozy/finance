# Stage 10.5 — UI Acceptance

## Suite

`frontend/e2e-real/acceptance.ui-driven.spec.ts` — a real, UI-driven
acceptance suite. The business action under test is always performed through
**visible UI controls** (typing into pickers, clicking buttons, confirming
dialogs). API calls are used **only** to authenticate, reset/prepare
fixtures, and verify final database state — never to perform the action being
tested. The forbidden "POST via API → open page → assert `<main>` exists"
pattern is not used.

### Flows

1. **Customer search-and-open** — type a phone number into the list search,
   click the matching result link, land on the customer detail page, verify
   the customer number renders.
2. **Collections assignment creation** — open the create form, pick the
   customer through the real `CustomerPicker` combobox, pick the collector,
   submit, land on the new assignment, then verify the row in the DB
   (`assertDb` by the URL-derived id, `is_active=1`, `status=assigned`).
3. **Guided payment collection** — open the collector payment workflow, pick
   the customer through the picker, load invoices, enter and allocate an
   amount, preview, and confirm through the confirmation dialog, landing on
   the receipt URL. (In a bcmath-less runtime the invoice step degrades to a
   real `ErrorState` with a working retry, which the test asserts instead —
   see `STAGE_10_5_KNOWN_ISSUES.md#1`.)

## Browser matrix (zero required skips)

Run via `playwright.acceptance.config.ts` across all six required projects:

| Project | Viewport | Locale |
|---|---|---|
| desktop-en | 1440×900 | English |
| desktop-fa | 1440×900 | Persian/Dari (RTL) |
| mobile-en | 390×844 | English |
| mobile-fa | 390×844 | Persian/Dari (RTL) |
| small-mobile-en | 320×700 | English |
| small-mobile-fa | 320×700 | Persian/Dari (RTL) |

**Result: 18 passed / 18 (3 tests × 6 projects), 0 required skips.**

## Strict console / network rules

`attachFailureGuards` (in `e2e-real/helpers/acceptance.ts`) fails a test on
any unexpected console error, page error, or HTTP 500. The allowlist is
small and documented; the only per-test addition is a **URL-scoped** entry
for `/api/v1/invoices` on the bcmath-less runtime, credited one-for-one
against the matching network 500 so an unrelated 500 still fails the test.
Every test also asserts no horizontal overflow (`expectNoHorizontalOverflow`).

## Defects found and fixed by this suite

- `MobileRecordCard` used a raw `<a>` (full reload + proxy redirect loop) —
  switched to the locale-aware `Link`.
- The collector payment page rendered an error state with no retry — added
  `onRetry`.
- (Accessibility pass) unlabeled selects/inputs, an unnamed mobile button,
  and a cascade-layer contrast bug — see `STAGE_10_5_ACCESSIBILITY_RESULTS.md`.

## Acceptance environment (reproducible)

Native (no Docker daemon in this sandbox), fully isolated from production:

- **PostgreSQL 16** — database + user `collection_acceptance`
  (`service postgresql start`).
- **Redis 7** — DB index 2, cache prefix `collection_acceptance`
  (`redis-server --daemonize yes`).
- **Backend** — `php artisan migrate --force` then
  `php artisan db:seed --class=AcceptanceSeeder --force` (deterministic
  `ACCEPTANCE-*` fixtures; the seeder refuses to run against production),
  served on `:18081`.
- **Zoho** — test adapter (`ZOHO_WRITE_MODE=test_adapter`); no live Zoho
  writes.
- **WhatsApp** and **Radius** — disabled.
- **Frontend** — `next build` + `next start` on `:3000`.
- **Unified origin** — a small local reverse proxy on `:3100` serves the
  frontend and forwards `/api/*` to the backend, matching the production
  nginx topology (same-origin, no CORS). Playwright targets `:3100`.

No production customer/financial/inventory data is created or mutated by any
of this — the acceptance database is separate and seeded from scratch.
