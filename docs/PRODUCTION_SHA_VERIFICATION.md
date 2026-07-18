# Production SHA Verification

Strict four-way match required after every Stage 10.3 deploy.

## Four SHAs (must be identical)

| Source | How to read |
|--------|-------------|
| GitHub tip | `git rev-parse HEAD` on `cursor/stage-10-3-functional-acceptance` (after push) |
| Host `.deployed-sha` | `cat /opt/collection-system/.deployed-sha` (and `backend/.deployed-sha`) |
| Health SHA | `GET /api/v1/health` → `data.deployment.git_sha` / `commit_sha` |
| System version SHA | `GET /api/v1/system/version` → `data.git_sha` / `commit_sha` |

Also confirm:

| Field | Expected |
|-------|----------|
| `deployment.stage` / `stage` | `10.3-functional-acceptance` |
| `deployment.branch` / `branch` | `cursor/stage-10-3-functional-acceptance` |

## Deploy rules

1. Commit **all** code + docs before the final deploy tip.
2. `FINAL_SHA=$(git rev-parse HEAD)` — deploy **that** SHA only.
3. After sync + `deploy.sh`, write `.deployed-sha=$FINAL_SHA` on host and into backend containers.
4. Set `APP_STAGE`, `APP_COMMIT_SHA`, `APP_BRANCH` in host `.env`; recreate backend/queue/scheduler so env matches.
5. If a docs-only commit lands **after** deploy (e.g. filled delivery report), **re-sync and update `.deployed-sha` / `APP_COMMIT_SHA` to the new tip** — never leave an orphan undeployed docs tip.

## Quick check

```bash
TIP=$(git rev-parse HEAD)
HOST=$(ssh -i ~/.ssh/id_ed25519 root@209.38.194.184 'cat /opt/collection-system/.deployed-sha')
HEALTH=$(curl -sf https://finance.mns.af/api/v1/health | jq -r '.data.deployment.git_sha')
VERSION=$(curl -sf https://finance.mns.af/api/v1/system/version | jq -r '.data.git_sha')
echo "tip=$TIP host=$HOST health=$HEALTH version=$VERSION"
test "$TIP" = "$HOST" -a "$TIP" = "$HEALTH" -a "$TIP" = "$VERSION" && echo FOUR_WAY_MATCH
```

## KEY_KEPT

Deploy uses `~/.ssh/id_ed25519`. **Never delete** this key.
