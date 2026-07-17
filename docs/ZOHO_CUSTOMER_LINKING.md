# Zoho Customer Linking

Stage 10.2 keeps Zoho as an **accounting / customer mirror** boundary — the platform links to existing Zoho contacts rather than creating contacts from CRM conversion shortcuts.

## Local fields

| Entity | Field | Role |
|--------|-------|------|
| Customer | `zoho_contact_id` | Local mirror of Zoho contact |
| Service | `zoho_customer_id` | Service-level Zoho customer reference (often copied from customer on create) |

## Flows

1. Search local Zoho mirror: `GET /api/v1/crm/customers/search-zoho-mirror`
2. Link lead → customer: `POST /api/v1/crm/leads/{id}/link-zoho-customer`
3. Service activation checklist requires evidence of Zoho link (not client-only flags)

## Global search

Operational search includes customer phone / Zoho id and service `zoho_customer_id`, scoped by branch + `customers.view` / `services.view`.

## See also

- `ZOHO_ACCOUNTING_BOUNDARY.md`
- `STAGE_8_CRM_SALES.md`
