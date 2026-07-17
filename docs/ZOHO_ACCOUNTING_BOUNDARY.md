# Zoho Books Accounting Boundary

## Zoho remains source of truth for

- Customers and contacts (where configured)
- Items
- Invoices
- Payments
- Credit notes
- Accounts
- Bills
- Purchase transactions
- Official financial reporting / GL

## Local application provides

- Simpler operational UI with branch restrictions
- Collection workflows (assignments, visits, payments, wallets, handovers)
- Inventory movement (Stage 9) without replacing Zoho GL
- Ticketing, CRM, installations (Stages 7–8)
- Radius orchestration (Stage 10)
- Notifications / WhatsApp (Stage 6)
- Operational reporting (Stage 11)

## Forbidden

- Creating a second independent general ledger
- “Silent” local financial mutations that never reconcile to Zoho when accounting impact is required
- Treating dry-run Zoho IDs as live posted accounting without promotion controls

## Required metadata for accounting-impact operations

Every local financial operation that requires accounting impact **must** carry:

| Field | Purpose |
|-------|---------|
| Zoho status | `pending`, `synced`, `failed`, `skipped`, `manual_review`, … |
| Zoho ID | Remote document/contact/payment ID when known |
| Idempotency key | Prevent duplicate Zoho posts |
| Retry state | Attempts, next retry, terminal failure |
| Reconciliation state | Matched / unmatched / variance |
| Audit record | Who/what/when/why |

## Current collections examples

- Local payment confirm → wallet/receipt local → `SyncPaymentToZohoJob`
- Reversal → local custody rules → Zoho void/credit path as designed
- Customer/invoice sync → Zoho pull with branch mapping

## Future domains

| Domain | Zoho touch |
|--------|------------|
| Installations | Create/update contact + invoice/billable items |
| Inventory purchases | Bills / item receipts |
| Sales quotes | Optional estimate docs if enabled |
| Credit/refund ops | Credit notes |

All via **Zoho Integration** jobs, not inline HTTP inside other domain transactions.

## Reporting split

| Report type | System |
|-------------|--------|
| Official P&L, balance sheet, tax | Zoho Books |
| Branch collections, handovers, ticket SLA, install pipeline | Local Reporting domain |

## Related ops docs

- `ZOHO_SETUP.md`, `ZOHO_PAYMENT_SYNC.md`, `PAYMENT_RECONCILIATION.md`
- Prefix/location mapping docs for branch correctness before posting
