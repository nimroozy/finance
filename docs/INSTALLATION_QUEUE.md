# Installation Queue (Stage 7)

Stage 7 delivers an **operational installation queue** only. Full CRM, leads, quotations, and Zoho/Radius activation remain **Stage 8+**.

## What Stage 7 includes

- Installation numbers (`INS` sequence)
- Branch-scoped installation records (customer or prospect contact fields)
- Status pipeline with enforced transitions
- Optional expansion of task templates into field/office tasks
- Attachments, work logs (via linked tasks), finance-oriented dashboard counts
- API: `GET/POST /installations`, `GET /installations/{id}`, `POST /installations/{id}/transition`

## Status pipeline

```
request_received
  → customer_verification → coverage_check
  → site_survey_pending → finance_review
  → equipment_waiting → scheduled → assigned
  → travelling → installing → noc_activation_pending
  → customer_confirmation → completed

Any active step may move to delayed or cancelled where allowed.
delayed can resume into earlier operational steps.
```

Full map: `Installation::allowedTransitions()`.

| Status | Intent |
|--------|--------|
| `request_received` | New queue item |
| `customer_verification` | Confirm identity / contact |
| `coverage_check` | Serviceability check |
| `site_survey_pending` | Survey needed |
| `finance_review` | Local finance gate (no Zoho invoice create here) |
| `equipment_waiting` | Waiting gear (no inventory ledger yet) |
| `scheduled` / `assigned` | Ready for tech |
| `travelling` / `installing` | Field execution |
| `noc_activation_pending` | Placeholder for later Radius handoff |
| `customer_confirmation` | Acceptance |
| `completed` / `cancelled` / `delayed` | Terminal / hold |

## Create payload (practical)

`POST /installations`:

- Required: `branch_id`
- Optional: `customer_id`, `prospect_name`, `contact_name`, `phone`, `address`, GPS, `requested_package`, `requested_date`, `notes`, `template_code`, `expand_template`

Transition: `POST /installations/{id}/transition` `{ status, notes? }`.

## Explicitly deferred to Stage 8 (CRM)

Do **not** claim these as Stage 7:

- Leads, opportunities, follow-ups, lost reasons
- Quotations and commercial pipeline
- Sales ownership / targets
- Formal site-survey CRM objects and quote acceptance
- Zoho customer/invoice creation jobs triggered by install win
- Inventory stock reservation (Stage 9)
- Live Radius activation (Stage 10)

Stage 7 `finance_review` and `noc_activation_pending` are **queue markers** so operations can track work; they are not the Stage 8/10 integration implementations.

See [CRM_INSTALLATION_MODEL.md](CRM_INSTALLATION_MODEL.md) for the Stage 8 target model.

## Permissions

| Permission | Actions |
|------------|---------|
| `installations.view` | List / show |
| `installations.create` | Create |
| `installations.update` | Transition |

## Events

`InstallationStatusChanged` → notification orchestrator.
