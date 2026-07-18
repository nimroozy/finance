# Stage 10.3 — Demo cleanup result

**Generated:** 2026-07-18T05:32:40+00:00 (VPS apply)  
**Mode:** apply  
**Manifest:** `docs/manifests/stage103-demo-cleanup.json`  
**Backup:** `/opt/collection-backups/20260718T053222Z`  
**KEY_KEPT:** yes (`~/.ssh/id_ed25519` preserved)

## Integrity

| Metric | Before | After | Unchanged |
|--------|-------:|------:|:---------:|
| payments | 3 | 3 | yes |
| stock_transactions | 14 | 14 | yes |

## Action summary

| Status | Count |
|--------|------:|
| archived | 12 |
| soft_deleted | 5 |
| disabled_renamed | 6 |
| **total** | **23** |

## Post-cleanup counts (VPS)

| Metric | Value |
|--------|------:|
| payments | 3 |
| stock_transactions | 14 |
| customers alive (`deleted_at` null) | 6993 |
| customers soft-deleted | 7 |
| customers status=archived | 7 |
| leads alive | 0 |
| leads soft-deleted | 1 |
| tickets soft-deleted | 2 |
| tasks soft-deleted | 3 |
| tasks remaining | 1 (non-candidate) |
| installations soft-deleted | 4 |
| users active | 4 |
| users disabled | 4 |
| branches active | 7 |
| branches inactive | 2 |

Customer **4183** (payment-linked): status=`archived`, name prefixed `[ARCHIVED TEST]`, soft-deleted, **payments still 3**.

Branches 3/4: `is_active=false`, names prefixed `[ARCHIVED TEST]`, codes `XS3A`/`XS3B`.

## Per-item results

| Table | ID | Action | Status | Label |
|-------|---:|--------|--------|-------|
| customers | 7000 | archive | archived | STAGE8 TEST customer - DO NOT USE |
| customers | 4138 | delete | soft_deleted | STAGE3-TEST Customer 1 |
| customers | 4139 | delete | soft_deleted | STAGE3-TEST Customer 2 |
| customers | 4140 | delete | soft_deleted | STAGE3-TEST Customer 3 |
| customers | 4141 | delete | soft_deleted | STAGE3-TEST Customer 4 |
| customers | 4142 | delete | soft_deleted | STAGE3-TEST Other Branch Customer |
| customers | 4183 | archive | archived | STAGE5 TEST CUSTOMER - DO NOT USE |
| crm_leads | 1 | archive | archived | STAGE8 TEST LEAD - DO NOT USE |
| branches | 3 | disable_rename | disabled_renamed | STAGE3-TEST Branch A |
| branches | 4 | disable_rename | disabled_renamed | STAGE3-TEST Branch B |
| users | 3 | disable_rename | disabled_renamed | STAGE3-TEST Manager |
| users | 4 | disable_rename | disabled_renamed | STAGE3-TEST Collector A |
| users | 5 | disable_rename | disabled_renamed | STAGE3-TEST Collector B |
| users | 6 | disable_rename | disabled_renamed | STAGE3-TEST Collector Other Branch |
| tickets | 2 | archive | archived | STAGE7 TEST ticket - DO NOT USE |
| tickets | 3 | archive | archived | STAGE71 TEST ticket - DO NOT USE |
| tasks | 1 | archive | archived | STAGE7 TEST task - DO NOT USE |
| tasks | 2 | archive | archived | STAGE71 TEST task - DO NOT USE |
| tasks | 3 | archive | archived | 01-TSK-2026-000001 |
| installations | 1 | archive | archived | STAGE7 TEST installation |
| installations | 2 | archive | archived | STAGE71 TEST |
| installations | 3 | archive | archived | STAGE8 TEST |
| installations | 4 | archive | archived | STAGE9 TEST - DO NOT USE |

Payments and stock_transactions were not deleted.

## Related

- Review: [STAGE_10_3_DEMO_DATA_REVIEW.md](STAGE_10_3_DEMO_DATA_REVIEW.md)
- Manifest: [manifests/stage103-demo-cleanup.json](manifests/stage103-demo-cleanup.json)
