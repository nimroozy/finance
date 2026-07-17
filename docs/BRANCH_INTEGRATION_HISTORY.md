# Branch Integration History — Stage 10.1

Audit trail of how `cursor/stage-10-1-integrated-stable` was assembled. **Do not rewrite history** of superseded PRs.

## Tip branch

| Field | Value |
|-------|-------|
| Branch | `cursor/stage-10-1-integrated-stable` |
| Draft PR | [#17](https://github.com/nimroozy/finance/pull/17) |
| Stage label | `10.1-integrated-stable` |
| Includes | Stages 7, 7.1, 8, 9, 9.1, 10 |

## Stacked lineage (newest integration first)

| Order | Branch | Draft PR | Notes |
|-------|--------|----------|-------|
| Tip | `cursor/stage-10-1-integrated-stable` | [#17](https://github.com/nimroozy/finance/pull/17) | Integrated stable baseline |
| ← | `cursor/stage-9-1-unified-app-ui` | [#16](https://github.com/nimroozy/finance/pull/16) | Launcher / shell / prefs — **superseded; keep open for audit** |
| ← | `cursor/stage-10-service-lifecycle` | [#15](https://github.com/nimroozy/finance/pull/15) | Service lifecycle — **superseded; keep open for audit** |
| ← | `cursor/stage-9-inventory-assets` | [#14](https://github.com/nimroozy/finance/pull/14) | Inventory / sites / towers |
| ← | Stage 8 CRM branch | earlier | CRM sales |
| ← | Stage 7 / 7.1 branches | earlier | Ticketing / ops UX |

## Recommendation (human action later)

- Prefer merging / deploying **#17** as the production tip.
- Close **#15** and **#16** later as **superseded by #17** — **do not close them from this agent run**.
- Preserve PR threads and branch refs for audit.

## Notable tip commits (Stage 10.1 work)

| SHA (short) | Summary |
|-------------|---------|
| `76df534` | Stage 10.1 version label + integrated stable markers |
| `a400477` | Playwright mobile/shell Create+Confirm fixes; ConfirmDialog above bottom nav |

Exact tip SHA is recorded in [STAGE_10_1_DELIVERY_REPORT.md](STAGE_10_1_DELIVERY_REPORT.md) after each VPS deploy.

## Deploy path

Always sync **this tip branch** (includes Stage 10 + 9.1):

```bash
./scripts/sync-to-vps.sh
# on VPS:
./scripts/deploy.sh
```
