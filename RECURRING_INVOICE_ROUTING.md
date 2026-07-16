# Recurring invoice routing

On Zoho invoice upsert (`ZohoInvoiceSyncService::upsertInvoice`), the customer work queue is recalculated and a `recurring_invoice_routing_logs` row is written.

- One queue row per customer (`customer_work_queues`)
- Aggregates open invoices / balances / overdue
- Sources: `permanent|temporary|manual|unassigned|conflict`
