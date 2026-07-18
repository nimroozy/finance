# Stage 10.4 — Final SHA verification

**Status:** pending final deploy of Stage 10.4 tip  
**Stage label:** `10.4-production-acceptance-closure`

## Starting SHA (verified before implementation)

See `docs/STAGE_10_4_STARTING_SHA.md` — all probes matched:

`87f04ab63c0c4ffa50e7cdc264ad35212938d01f`

## Final SHA (fill after deploy — must match everywhere)

| Probe | Value |
|-------|-------|
| GitHub branch head | _(final tip)_ |
| Production source / sync tip | _(final tip)_ |
| `/opt/collection-system/.deployed-sha` | _(final tip)_ |
| `APP_COMMIT_SHA` (backend container) | _(final tip)_ |
| `/api/v1/health` → `deployment.commit_sha` | _(final tip)_ |
| `/api/v1/system/version` → `commit_sha` | _(final tip)_ |
| Frontend build `NEXT_PUBLIC_APP_COMMIT_SHA` / `FRONTEND_BUILD_ID` | _(final tip)_ |

**Rule:** Do not leave an undeployed documentation-only tip. If docs land after verify, re-sync and re-verify.
