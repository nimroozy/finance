# Stage 10.4 — Final SHA verification

**Status:** MATCH  
**Stage label:** `10.4-production-acceptance-closure`  
**Final SHA:** `0f3e1ae3ee916542cbdcdac9032b5bee561073e6`

## Starting SHA (pre-implementation)

`87f04ab63c0c4ffa50e7cdc264ad35212938d01f` — see `STAGE_10_4_STARTING_SHA.md` (MATCH).

## Final SHA probes (verified 2026-07-18)

| Probe | Value | Match |
|-------|-------|-------|
| GitHub branch head | `0f3e1ae3ee916542cbdcdac9032b5bee561073e6` | yes |
| `/opt/collection-system/.deployed-sha` | `0f3e1ae3ee916542cbdcdac9032b5bee561073e6` | yes |
| Backend `APP_COMMIT_SHA` | `0f3e1ae3ee916542cbdcdac9032b5bee561073e6` | yes |
| `/api/v1/health` → `deployment.commit_sha` | `0f3e1ae3ee916542cbdcdac9032b5bee561073e6` | yes |
| `/api/v1/health` → `deployment.stage` | `10.4-production-acceptance-closure` | yes |
| Frontend `frontend_version` | `0f3e1ae3ee916542cbdcdac9032b5bee561073e6` | yes |

This tip was redeployed after documentation alignment so GitHub, `.deployed-sha`, `APP_COMMIT_SHA`, health, and frontend build metadata match.
