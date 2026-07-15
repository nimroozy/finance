# Receipts

Issued on payment confirm when the payment method has `receipt_enabled` (`ReceiptService::issueForPayment`).

## Statuses

| Value | Meaning |
|-------|---------|
| `issued` | Active receipt |
| `voided` | Voided on approved payment reversal |

## Identifiers

- `uuid` — API route key (`/receipts/{uuid}`)
- `receipt_number` — branch-scoped sequence (`ReceiptNumberService`, pad from `config/payments.php`)
- `verification_token` — 48-char random; public verify only

## Storage (`local` disk → `storage/app/private/`)

| File | Path |
|------|------|
| HTML snapshot | `receipts/{uuid}.html` |
| PDF | `receipts/{uuid}.pdf` (queued via `GenerateReceiptPdfJob`) |

Templates: `resources/views/receipts/pdf.blade.php`, `thermal-58`, `thermal-80`.

## API

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/v1/receipts/{uuid}` | `receipts.view` |
| GET | `/api/v1/receipts/{uuid}/pdf` | `receipts.view` — inline PDF if generated |
| POST | `/api/v1/receipts/{uuid}/print-log` | `receipts.print` \| `receipts.manage` — optional `channel`, `device_info` |
| GET | `/api/v1/verify-receipt/{token}` | public, throttle 30/min |
| GET | `/verify-receipt/{token}` | same verify handler via `web.php` |

Public verify returns: receipt number, status, issued_at, amount, currency, customer name/number, branch codes/names, payment_reference — not allocations or sync internals.

## Config

`PAYMENT_RECEIPT_TEMPLATE_VERSION`, `PAYMENT_RECEIPT_LANGUAGE` (default `en`), plus `/payment-settings` key `payment_receipt_language`.
