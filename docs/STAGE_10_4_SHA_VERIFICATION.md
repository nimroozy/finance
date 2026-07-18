# Stage 10.4 — Final SHA verification

**Status:** MATCH  
**Stage label:** `10.4-production-acceptance-closure`  
**Final SHA:** `a0831c99e57d5624bef4561d233b865cc33246aa`

## Starting SHA (pre-implementation)

`87f04ab63c0c4ffa50e7cdc264ad35212938d01f` — see `STAGE_10_4_STARTING_SHA.md` (MATCH).

## Final SHA probes (verified 2026-07-18)

| Probe | Value | Match |
|-------|-------|-------|
| GitHub branch head | `a0831c99e57d5624bef4561d233b865cc33246aa` | yes |
| `/opt/collection-system/.deployed-sha` | `a0831c99e57d5624bef4561d233b865cc33246aa` | yes |
| Backend `APP_COMMIT_SHA` | `a0831c99e57d5624bef4561d233b865cc33246aa` | yes |
| `/api/v1/health` → `deployment.commit_sha` | `a0831c99e57d5624bef4561d233b865cc33246aa` | yes |
| `/api/v1/health` → `deployment.stage` | `10.4-production-acceptance-closure` | yes |
| Frontend `frontend_version` | `a0831c99e57d5624bef4561d233b865cc33246aa` | yes |

If a later documentation tip is required, it must be deployed and this table updated to that tip.
