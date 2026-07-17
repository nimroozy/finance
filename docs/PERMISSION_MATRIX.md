# Permission Matrix (Stage 10.2 stub)

Source of truth: `backend/database/seeders/RolePermissionSeeder.php`.

## Role summary

| Permission area | Super Admin | Central Finance | Branch Manager | Collector | Auditor |
|-----------------|-------------|-----------------|----------------|-----------|---------|
| users / roles / branches / settings | all | partial | view users/branches | branches.view | audit-focused |
| zoho.* | all | view/sync | — | — | view |
| customers / invoices / debtors | all | all listed | view | view customers/invoices | view |
| assignments / visits / routes / promises | all | view/manage subset | broad | field create/view | view |
| payments / receipts / wallets / handovers | all | broad | broad | create/confirm/view | view |
| customer_ownership / prefix mapping | all | yes | yes | limited | view |
| whatsapp.* | all | manage | view + ticket intake | — | — |
| tickets / tasks / installations / SLA | all | limited | broad branch | — | view |
| crm.leads.* / quotations / pipeline | all | view + quote approve | **full CRM** | — | — |
| inventory.* / purchasing.* | all | purchasing view/approve | broad inventory | — | — |
| services.* (lifecycle) | all | billing/dashboard/reports | **full services** | — | — |

## CRM permissions (detail)

| Permission | Super Admin | Central Finance | Branch Manager | Collector |
|------------|-------------|-----------------|----------------|-----------|
| `crm.leads.view` | ✓ | ✓ | ✓ | — |
| `crm.leads.create` | ✓ | — | ✓ | — |
| `crm.leads.update` | ✓ | — | ✓ | — |
| `crm.leads.assign` | ✓ | — | ✓ | — |
| `crm.leads.convert` | ✓ | — | ✓ | — |
| `crm.activities.manage` | ✓ | — | ✓ | — |
| `crm.follow_ups.manage` | ✓ | — | ✓ | — |
| `crm.surveys.view/manage` | ✓ | — | ✓ | — |
| `crm.quotations.view` | ✓ | ✓ | ✓ | — |
| `crm.quotations.create` | ✓ | — | ✓ | — |
| `crm.quotations.approve` | ✓ | ✓ | ✓ | — |
| `crm.coverage.manage` | ✓ | — | ✓ | — |
| `crm.targets.view/manage` | ✓ | view | ✓ | — |
| `crm.reports.view` | ✓ | ✓ | ✓ | — |
| `crm.pipeline.manage` | ✓ | — | ✓ | — |

## Service lifecycle permissions

| Permission | Notes |
|------------|-------|
| `services.view` … `services.types.manage` | Full set in seeder; Radius intentionally absent |
| `services.noc.view` | NOC operational workspace |
| `services.activate` | Lifecycle only — no Radius provisioning |

## Purchasing

| Permission | UI exposure |
|------------|-------------|
| `inventory.purchasing.view/manage/approve` | Seeded, but **purchasing app card hidden** (`FEATURE_FLAGS.purchasing.enabled = false`) |
