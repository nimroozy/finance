# Branch Zoho account mapping

- `branch_zoho_account_mappings` + `branch_payment_configurations`
- Accounts/modes selected from synchronized Zoho catalogs (dropdowns)
- Readiness: `ready|missing_location|missing_account|missing_payment_mode|currency_mismatch|inactive_account|…`
- Live Zoho customer payment blocked unless `ready`
- Dry-run remains available for admin/test flows
- Payment snapshots account/location/mode/version for audit
