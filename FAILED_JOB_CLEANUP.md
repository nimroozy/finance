# FAILED_JOB_CLEANUP.md

**Stage:** 5.1 P0

## Command

```bash
php artisan zoho:failed-jobs-cleanup
php artisan zoho:failed-jobs-cleanup --apply
php artisan zoho:failed-jobs-cleanup --only-timestamp=0   # broader grouping (use carefully)
```

## Behavior

- **Dry-run by default**
- Groups Laravel `failed_jobs` by job class, endpoint/error signature, normalized message
- Identifies timestamp / `last_modified_time` permanent validation failures
- Archives a summary under `storage/app/zoho-reports/` before apply
- `--apply` removes **redundant** timestamp-format failures
- Preserves one representative failure per group
- Preserves unrelated failures
- Writes an audit-friendly report file

## When to run

After deploying the `ZohoDateTime` fix and confirming a successful incremental sync, clean the storm of historical identical 400s.

Do **not** `queue:flush` blindly.
