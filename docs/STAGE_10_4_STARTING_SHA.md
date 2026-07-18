# Stage 10.4 — Starting SHA verification

**Recorded at:** 2026-07-18 (pre-implementation)  
**Expected starting SHA:** `87f04ab63c0c4ffa50e7cdc264ad35212938d01f`  
**Base branch:** `cursor/stage-10-3-functional-acceptance`

## Result: MATCH

All production identity probes equal the expected Stage 10.3 tip. No production correction required before Stage 10.4 implementation.

| Probe | Actual value | Match |
|-------|--------------|-------|
| GitHub branch head (`cursor/stage-10-3-functional-acceptance`) | `87f04ab63c0c4ffa50e7cdc264ad35212938d01f` | yes |
| `/api/v1/health` → `data.deployment.git_sha` / `commit_sha` | `87f04ab63c0c4ffa50e7cdc264ad35212938d01f` | yes |
| `/api/v1/health` → `data.deployment.stage` | `10.3-functional-acceptance` | yes |
| `/api/v1/health` → `data.deployment.branch` | `cursor/stage-10-3-functional-acceptance` | yes |
| Host `/opt/collection-system/.deployed-sha` | `87f04ab63c0c4ffa50e7cdc264ad35212938d01f` | yes |
| Backend container `APP_COMMIT_SHA` | `87f04ab63c0c4ffa50e7cdc264ad35212938d01f` | yes |
| Backend container `APP_STAGE` | `10.3-functional-acceptance` | yes |
| Backend container `APP_ENV` | `production` | yes (expected) |
| Host `.env` `APP_COMMIT_SHA` | `87f04ab63c0c4ffa50e7cdc264ad35212938d01f` | yes |
| Running containers | nginx, frontend, backend, postgres, redis, queue-worker, scheduler — healthy/up | yes |

### Notes

- `/api/v1/system/version` requires authentication; SHA/stage confirmed via public health `deployment` block and container env.
- Compose file on VPS is `docker-compose.yml` (not `docker-compose.prod.yml`).
- No value differed from the expected starting SHA; documentation records actuals above without substitution.
