# CRM & Installation Model (Stage 8)

## CRM

### Entities

- **Lead** — inbound interest (WhatsApp, walk-in, referral, campaign)
- **Opportunity** — qualified deal with pipeline stage
- **Follow-up** — scheduled contact activity
- **Quotation** — commercial offer (items may map to Zoho items later)
- **Lost reason** — structured loss taxonomy
- **Sales ownership** — primary salesperson + branch

### Pipeline (illustrative)

`new` → `contacted` → `qualified` → `survey` → `quoted` → `won` / `lost`

### Rules

- Branch isolation for leads/opportunities.
- Sales targets and attribution by employee and branch.
- Winning an opportunity may create an **Installation request** (event), not inline Radius/Zoho calls.

## New installation workflow

```
Installation request
  → Site survey
  → Quotation / commercial confirm
  → Finance approval
  → Equipment reservation (Inventory event → Stage 9)
  → Technical assignment (Tasks)
  → Radius activation command (queued → Stage 10)
  → Zoho customer / invoice creation (queued → Zoho Integration)
  → Customer acceptance
  → Closed / activated
```

### Statuses (illustrative)

`draft`, `survey_scheduled`, `survey_done`, `awaiting_finance`, `approved`, `equipment_reserved`, `in_install`, `awaiting_activation`, `activated`, `accepted`, `cancelled`

### Customer-installed equipment

Track CPE / customer-premises gear linked to:

- Customer
- Installation
- Inventory serial (when Stage 9 live)
- Warranty / return eligibility

Until Stage 9, store lightweight equipment records without claiming full stock ledger.

## Integration boundaries

| Step | Domain | Mechanism |
|------|--------|-----------|
| Reserve stock | Inventory | `StockReservationRequested` job |
| Activate subscriber | Radius | Queued adapter command |
| Create Zoho contact/invoice | Zoho | Idempotent sync job |
| Notify staff/customer | Notifications | Orchestrator |

## Reporting hooks (Stage 8 + 11)

- Branch sales
- Sales by employee
- New internet sales
- Equipment sales
- Installation pipeline / pending installs
