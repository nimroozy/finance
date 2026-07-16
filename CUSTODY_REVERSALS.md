# Custody-Aware Reversals

If a payment has `handover_status=handed_over` (or partially handed over):

1. Standard Stage 4 `/reversals/{id}/approve` is blocked from running the simple wallet path.
2. `/payments/{uuid}/reversal-request` creates a `custody_conflicts` row (`pending_review`).
3. Branch Finance / Central Finance with `custody_reversals.review` (or `reversals.approve`) reviews via `/api/v1/custody-reversals*`.
4. On approval the workflow:
   - locks payment, handover item, and branch cashbox
   - posts a compensating `custody_reversal_debit` cashbox entry
   - reverses local allocations and marks payment/receipt reversed
   - does **not** rewrite the original approved handover amounts
   - voids Zoho payment (idempotent if already gone)
   - queues targeted invoice refresh
   - writes a matched reconciliation record and audit logs
5. Insufficient cashbox balance → `manual_review` (no Zoho void).
6. Local success + Zoho void failure → `zoho_void_status=pending` with idempotent retry job.
7. Zoho already voided + local failure → `critical_recovery` alert for finance recovery.
