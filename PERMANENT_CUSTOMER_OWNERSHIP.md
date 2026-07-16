# Permanent customer ownership

- Table: `customer_collector_ownerships` (+ immutable `customer_collector_ownership_history`)
- One active permanent owner per customer
- Unmapped customers cannot be owned
- Cross-branch collectors rejected
- Transfer ends previous row (`transferred`) and creates a new active row; history preserved; historical payments/receipts unchanged
- Resolution priority: temporary → permanent → manual assignment → unassigned
