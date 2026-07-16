# Stage 5 — Cash Handover and Branch Cashbox

Collectors hold cash after Stage 4 cash payments (`settled_pending_handover`). Stage 5 moves that cash into branch custody through immutable ledgers.

## Workflow

1. Collector lists eligible payments (`GET /cash-handovers/eligible`)
2. Draft handover with payment IDs + declared amount
3. Submit for branch review
4. Manager counts cash, then approves / partially approves / rejects
5. On approval: collector wallet debit + branch cashbox credit + payments marked handed over + handover receipt number `{BRANCH}-HO-{YEAR}-{SEQ}`
6. Rejection / draft does **not** move money

## Rules

- One payment may appear in only one active/approved handover
- Frontend totals are recalculated server-side
- No float money — `App\Support\Money` / DECIMAL(18,4)
- Payments already handed over cannot use simple Stage 4 reversal — custody review required
- `ZOHO_PAYMENT_DRY_RUN` stays true for collectors; Stage 5 smoke uses dry-run payments only

## Smoke

```bash
php artisan app:stage5-smoke --password-file=/path/to/pass
```

## UI

- Collector: `/collector/handovers/new`
- Manager: `/handovers`, `/cashboxes`
