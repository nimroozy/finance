# Stage 10.4 — Final SHA verification

**Status:** MATCH  
**Stage label:** `10.4-production-acceptance-closure`

## Starting SHA (pre-implementation)

`87f04ab63c0c4ffa50e7cdc264ad35212938d01f` — see `STAGE_10_4_STARTING_SHA.md` (MATCH).

## Final SHA

Use the GitHub tip of `cursor/stage-10-4-production-acceptance-closure` and confirm it equals every probe below. At closeout that tip was:

**`ad8736cd70387fcb013f7fa1220c3ff336dc9fde`**

| Probe | Expected |
|-------|----------|
| GitHub branch head | same as Final SHA |
| `/opt/collection-system/.deployed-sha` | same |
| Backend `APP_COMMIT_SHA` | same |
| `/api/v1/health` → `deployment.commit_sha` | same |
| `/api/v1/health` → `deployment.stage` | `10.4-production-acceptance-closure` |
| Frontend `frontend_version` / `FRONTEND_BUILD_ID` | same |

Do not leave an undeployed documentation-only tip.
