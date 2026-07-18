# Stage 10.4 — Final SHA verification

**Status:** MATCH (after final tip deploy)  
**Stage label:** `10.4-production-acceptance-closure`  
**Final SHA:** `7868043d25a9e8c4badb7b528b75e3f36fbb14fa`

## Starting SHA (pre-implementation)

`87f04ab63c0c4ffa50e7cdc264ad35212938d01f` — MATCH (`STAGE_10_4_STARTING_SHA.md`).

## Final probes

Confirmed equal across:

| Probe | Value |
|-------|-------|
| GitHub branch head | `7868043d25a9e8c4badb7b528b75e3f36fbb14fa` |
| Host `.deployed-sha` | `7868043d25a9e8c4badb7b528b75e3f36fbb14fa` |
| Backend `APP_COMMIT_SHA` | `7868043d25a9e8c4badb7b528b75e3f36fbb14fa` |
| `/api/v1/health` `commit_sha` / `frontend_version` | `7868043d25a9e8c4badb7b528b75e3f36fbb14fa` |
| Stage | `10.4-production-acceptance-closure` |

Acceptance endpoints return **404** in production. AcceptanceSeeder users on production: **0**.
