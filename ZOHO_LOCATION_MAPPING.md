# ZOHO_LOCATION_MAPPING.md

**Stage:** 5.1 P0

## Zoho structure for this organization

- Native Books **branches** API: unavailable (404)
- **Locations**: primary geographic units (synced to `zoho_locations`)
- **Reporting tags / options**: synced to `zoho_reporting_tags` / `zoho_reporting_tag_options`
- Accounts and payment modes synced for reference

```bash
php artisan zoho:sync-organization-structure          # dry-run
php artisan zoho:sync-organization-structure --apply
```

## Local branches

Local `branches` retain operational fields (Persian name, receipt prefix, phone, address, managers).

New links (not overwriting local-only fields on Zoho sync):

- `zoho_location_id`
- optional reporting tag/option ids
- `mapping_type`, `zoho_sync_status`, `last_structure_sync_at`, `local_override`

## Resolution priority

Configurable `zoho.sync.mapping_priority` (default):

1. Zoho location (`branches.zoho_location_id` / mapping method `zoho_location`)
2. Reporting tag option
3. Customer custom field
4. Verified local mapping
5. Unmapped

**Org-specific note:** Contact *list* payloads for Mobin Net often omit `location_id`. Invoice payloads include `location_id` / `location_name`. Customers inherit branch from related invoice locations during sync/reprocess when contact location is absent.

## UI

`/en/zoho/branch-mappings`

- Location / tag dropdowns (no raw IDs in normal use)
- Fetch structure, preview auto-match, apply confirmed matches
- Import location as local branch / link location
- Advanced raw-ID entry for Super Administrator only

Auto-match classifications: `exact`, `probable`, `ambiguous`, `no_match`. Ambiguous matches are never auto-applied.

## Reprocess

```bash
php artisan zoho:reprocess-customer-branches
php artisan zoho:reprocess-customer-branches --apply --branch=1
php artisan zoho:reprocess-invoice-branches --apply
```

Customers with payments or active assignments that would change branch are **conflicts** and skipped for automatic apply. Changes are logged in `customer_branch_change_logs`.
