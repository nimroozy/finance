# Stage 10.4 — Acceptance cleanup result

**Generated:** 2026-07-18T09:04:55+00:00
**Mode:** apply
**Manifest:** `/workspace/backend/storage/app/stage103-test-manifest-6a5b41b7bb3533.20455278.json`
**Environment:** Stage 10.4 acceptance/demo run (not production)

> Recovered record. This run's output previously overwrote the Stage 10.3
> historical production report at `docs/STAGE_10_3_CLEANUP_RESULT.md`
> because `Stage103CleanupDemoCommand` wrote every run's markdown report to
> that fixed docs path. The production record has been restored from
> `cursor/stage-10-3-functional-acceptance`; this file preserves the Stage
> 10.4 acceptance run's own (much smaller, non-production) result instead
> of discarding it. The command no longer writes to any `docs/` path — see
> `storage/app/stage103-cleanup-result.md` for future runs.

## Integrity

| Metric | Before | After | Unchanged |
|--------|-------:|------:|:---------:|
| payments | 0 | 0 | yes |
| stock_transactions | 0 | 0 | yes |

## Action summary

- `disabled_renamed`: 1

## Per-item results

| Table | ID | Action | Status | Label |
|-------|---:|--------|--------|-------|
| users | 1 | disable_rename | disabled_renamed |  |

Payments and stock_transactions were not deleted.
