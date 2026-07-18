# Stage 10.4 — Final SHA verification

**Status:** MATCH (after final tip deploy)  
**Stage label:** `10.4-production-acceptance-closure`  
**Final SHA:** `ceb84eff63848097da8caff1252847b297fb2145`

## Starting SHA (pre-implementation)

`87f04ab63c0c4ffa50e7cdc264ad35212938d01f` — MATCH (`STAGE_10_4_STARTING_SHA.md`).

## Final probes

Confirmed equal across:

| Probe | Value |
|-------|-------|
| GitHub branch head | `ceb84eff63848097da8caff1252847b297fb2145` |
| Host `.deployed-sha` | `ceb84eff63848097da8caff1252847b297fb2145` |
| Backend `APP_COMMIT_SHA` | `ceb84eff63848097da8caff1252847b297fb2145` |
| `/api/v1/health` `commit_sha` / `frontend_version` | `ceb84eff63848097da8caff1252847b297fb2145` |
| Stage | `10.4-production-acceptance-closure` |

Acceptance endpoints return **404** in production. AcceptanceSeeder users on production: **0**.
