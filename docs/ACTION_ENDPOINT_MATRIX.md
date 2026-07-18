# Action → Endpoint Matrix (Stage 10.2 stub)

Partial matrix with verified routes. Expand as repair work continues.

| UI / action | Method | Endpoint | Permission | Status |
|-------------|--------|----------|------------|--------|
| List leads | GET | `/api/v1/crm/leads` | `crm.leads.view` | keep |
| Create lead | POST | `/api/v1/crm/leads` | `crm.leads.create` | keep |
| Update lead | PUT | `/api/v1/crm/leads/{id}` | `crm.leads.update` | keep |
| Transition stage | POST | `/api/v1/crm/leads/{id}/transition` | `crm.leads.update` | keep |
| Assign lead | POST | `/api/v1/crm/leads/{id}/assign` | `crm.leads.assign` | keep |
| Search Zoho mirror | GET | `/api/v1/crm/customers/search-zoho-mirror` | `crm.leads.view|customers.view` | keep (new) |
| Link Zoho customer | POST | `/api/v1/crm/leads/{id}/link-zoho-customer` | `crm.leads.update` | keep (new) |
| Convert lead | POST | `/api/v1/crm/leads/{id}/convert` | `crm.leads.convert` | keep — requires `zoho_contact_id` |
| CRM dashboard | GET | `/api/v1/crm/dashboard` | `crm.reports.view|crm.leads.view|dashboard.view` | keep |
| Accept quotation | POST | `/api/v1/crm/quotations/{id}/accept` | `crm.quotations.approve` | keep |
| Services dashboard | GET | `/api/v1/services/dashboard` | `services.dashboard.view` | keep |
| NOC workspace | GET | `/api/v1/services/noc` | `services.noc.view` | keep |
| Activate service | POST | `/api/v1/services/{id}/activate` | `services.activate` | keep — no Radius |
| Purchase requests list | GET | `/api/v1/inventory/purchase-requests` | `inventory.purchasing.view` | hide UI |
| Purchase orders list | GET | `/api/v1/inventory/purchase-orders` | `inventory.purchasing.view` | hide UI |
| List customers | GET | `/api/v1/customers` | `customers.view` | keep |
| Demo cleanup dry-run | CLI | `stage102:cleanup-demo --dry-run` | ops | keep |
| Demo cleanup apply (manifest) | CLI | `stage103:cleanup-demo --manifest=… --apply` | ops | keep |

Placeholder removals (no longer dispatched):

- ~~`PlaceholderRadiusActivationRequested`~~
- ~~`PlaceholderZohoCustomerRequested`~~ (CRM customer create)
