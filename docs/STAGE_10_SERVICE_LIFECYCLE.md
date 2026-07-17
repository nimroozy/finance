# Stage 10 — ISP Service Lifecycle

Backend foundation for commercial/operational service lifecycle after installation.

## Hard constraints

- No SAS Radius / Radius DB connectivity
- No automated network provisioning
- Zoho remains SoT for customers/invoices/payments (billing views read-only)
- Radius feature flag remains `false` (deferred to Stage 12)

## Tables

`service_types`, `service_access_technologies`, `service_sla_templates`, `service_locations`, `service_packages`, `service_package_versions`, `service_sequences`, `service_contracts`, `services`, `service_status_transitions`, `service_activations`, `service_suspensions`, `service_cancellations`, `service_change_requests`, `service_relocations`, `service_renewals`, `service_finance_holds`

Also: nullable `tickets.service_id`, nullable `inventory_customer_equipment.service_id`.

## Key API (prefix `/api/v1`)

| Method | Path |
|--------|------|
| GET/POST | `/services` |
| GET/PUT | `/services/{id}` |
| POST | `/services/{id}/activate\|suspend\|reactivate\|cancel\|transition` |
| POST | `/services/{id}/change-requests\|relocations\|renewals\|finance-holds` |
| GET | `/services/{id}/billing\|timeline` |
| GET/POST | `/service-packages`, `/service-packages/{id}/versions` |
| GET/POST/PUT | `/service-locations` |
| GET | `/service-types`, `/service-access-technologies`, `/service-sla-templates` |
| GET/POST | `/service-contracts` |
| POST | `/installations/{id}/convert-to-service` |
| GET | `/services/dashboard`, `/services/noc-workspace`, `/services/reports/status` |
| POST | `/services/migration/dry-run\|apply` |

## Tests

`tests/Feature/Stage10ServiceLifecycleTest.php` — 19 tests covering critical lifecycle paths, Radius flag off, payment count stability, mocked WhatsApp notifications.
