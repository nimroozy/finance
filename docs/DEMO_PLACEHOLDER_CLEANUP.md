# Demo / Placeholder Cleanup

Stage 10.2 removed lifecycle “instant completion” shortcuts and CRM Radius/Zoho-create side-effect handlers that were unsafe for production.

Stage 10.3 adds a **reviewed manifest** cleanup path after human FK review.

## CLI

### Discover (pattern scan)

```bash
php artisan stage102:cleanup-demo --dry-run
```

### Apply reviewed actions (Stage 10.3)

```bash
php artisan stage103:cleanup-demo --manifest=docs/manifests/stage103-demo-cleanup.json --dry-run
php artisan stage103:cleanup-demo --manifest=docs/manifests/stage103-demo-cleanup.json --apply
```

`--apply` writes `docs/STAGE_10_3_CLEANUP_RESULT.md`. Actions: `delete` | `archive` | `disable_rename` | `preserve`.

Customers with payments are always archived (inactive + `[ARCHIVED TEST]` prefix), never hard-deleted. Payments and stock transactions are never deleted.

Review: [STAGE_10_3_DEMO_DATA_REVIEW.md](STAGE_10_3_DEMO_DATA_REVIEW.md)

## Product flags

- Purchasing and Radius remain **feature-flagged off** in the launcher (`feature-flags.ts`) until later stages.
- Disabled modules must not appear as functional cards.

## Related tests

- `Stage102CleanupDemoTest`
- `Stage103CleanupDemoTest`
- `Stage102ServiceLifecycleTest` (no instant cancel/change/relocate completion by default)
