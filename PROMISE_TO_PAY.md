# Promise to pay

Promises record a customer commitment to pay an amount by a date. **Stage 3 has no payment posting** — fulfillment is manual only until Stage 4.

## Statuses

| Status | Meaning |
|--------|---------|
| `active` | Open, not yet in due window |
| `due_soon` | Promised date within `COLLECTION_PROMISE_DUE_SOON_DAYS` (default 3) |
| `due_today` | Promised date is today |
| `overdue` | Promised date in the past |
| `fulfilled` | Closed by manager (`POST …/fulfill`) |
| `cancelled` | Cancelled by creator/manager |
| `superseded` | Replaced by a newer promise |

Open set (`PromiseToPay::OPEN_STATUSES`): `active`, `due_soon`, `due_today`, `overdue`.

Date-driven statuses are refreshed daily by scheduled `UpdatePromiseStatusesJob`.

## Rules

- Amount must be greater than 0; `promised_date` required.
- Collectors cannot set a past promise date unless a manager allows (`allow_past_date`) or actor is manager/admin.
- Can be created standalone (`POST /promises`) or with a visit when outcome is `promise_to_pay`.
- Currency defaults to `AFN`.
- Config key `COLLECTION_PROMISE_MAX_ACTIVE` (default 1) is defined in `config/collection.php`.

## Manual fulfill only (until Stage 4)

`POST /promises/{id}/fulfill` requires `promises.manage` (Branch Manager / Central Finance / Super Admin).

This marks the promise fulfilled — it does **not** create a payment, receipt, or Zoho cash entry. Those belong to Stage 4 (not started).

## Cancel

`POST /promises/{id}/cancel` — open promises only. Available to `promises.create` or `promises.manage`. Stores `cancel_reason`, `cancelled_by`, `cancelled_at`.

## Supersede

`POST /promises/{id}/supersede` (`promises.manage`):

1. Creates a new active promise for the same customer/assignment/collector.
2. Marks the old promise `superseded` with `superseded_by_id` → new id.

## Permissions

| Action | Permission |
|--------|------------|
| List | `promises.view` |
| Create / cancel | `promises.create` or `promises.manage` |
| Fulfill / supersede | `promises.manage` |

Collectors: view + create + cancel. Managers/finance: also fulfill & supersede. Auditors: view only.
