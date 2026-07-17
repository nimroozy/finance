# Demo / Placeholder Cleanup

Stage 10.2 removes lifecycle “instant completion” shortcuts and CRM placeholder Radius/Zoho-create handlers that were unsafe for production.

## CLI

```bash
php artisan stage102:cleanup-demo --dry-run
php artisan stage102:cleanup-demo --apply
```

Dry-run reports candidates; `--apply` archives/tags demo-like records (see `Stage102CleanupDemoCommand`).

## Product flags

- Purchasing and Radius remain **feature-flagged off** in the launcher (`feature-flags.ts`) until later stages.
- Disabled modules must not appear as functional cards.

## Related tests

- `Stage102CleanupDemoTest`
- `Stage102ServiceLifecycleTest` (no instant cancel/change/relocate completion by default)
