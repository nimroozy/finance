# Stage 9.1 Delivery Report

**Date (UTC):** 2026-07-17  
**PR branch (review stack):** `cursor/stage-9-1-unified-app-ui` @ `5e44798118e3e444621bce10eeb56433e99f0fe3`  
**VPS deploy branch:** `cursor/stage-9-1-deploy-from-s10` @ `71ee2d7a371cba353efd4ccffbb33444eb7ab7cb`  
**VPS:** `root@209.38.194.184` (`/opt/collection-system`)  
**Deploy stamp:** `20260717T180304Z`  
**KEY_KEPT:** yes (`~/.ssh/id_ed25519` on agent — not deleted)

---

## Summary

Stage 9.1 unified app launcher / UI preferences is live on production **without downgrading Stage 10**.

Production was already on Stage 10 (`67b4af6`). Syncing pure stage-9.1 with `rsync --delete` would have wiped Stage 10 service lifecycle code. For VPS only, a deploy lineage was built as **stage-10 tip + Stage 9.1 commits cherry-picked**, with Services wired into the new launcher catalog.

PR / review branch `cursor/stage-9-1-unified-app-ui` remains stacked on stage-9 only (no Stage 10 in that HEAD).

---

## Why a separate deploy branch

| Ref | SHA | Contains Stage 10? | Contains Stage 9.1 UI? |
|-----|-----|--------------------|-------------------------|
| `cursor/stage-9-1-unified-app-ui` (PR) | `5e44798` | **No** | Yes |
| VPS before deploy | `67b4af6` | Yes | No |
| `cursor/stage-9-1-deploy-from-s10` (VPS) | `71ee2d7` | Yes (preserved) | Yes |

Deploy branch construction:

1. `git checkout -b cursor/stage-9-1-deploy-from-s10 origin/cursor/stage-10-service-lifecycle`
2. Cherry-pick `647c330` (UI preferences API), `24bb707` (launcher shell), `5e44798` (docs/ThemeProvider)
3. Resolve conflicts favoring 9.1 shell; keep Stage 10 `services` i18n + add Services app to `app-catalog.ts`
4. Sync **that** tree to VPS (safe with `--delete`)

---

## Explicit non-goals

- Does not merge PR #16 / stage-9.1 into main
- Does not begin new Stage 10 features in the PR branch
- Does not remove Stage 10 filesystem/DB from production
- Does not modify payment/wallet/handover/inventory business rows
- Does not create unnecessary business TEST records
- Does not delete SSH deploy keys (**KEY_KEPT**)

---

## Backups

| Phase | Path | Status |
|-------|------|--------|
| Pre-deploy | `/opt/collection-backups/20260717T180304Z-stage91-predeploy/` | **Done** — postgres.dump (~2.2MB) + gz + storage |
| Post-deploy | `/opt/collection-backups/20260717T180304Z-stage91-postdeploy/` | **Done** — postgres.dump (~2.2MB) + gz |

---

## Pre-deploy snapshot

| Item | Value |
|------|-------|
| BEFORE `.deployed-sha` | `67b4af6d0bbbff9e5a0a2b9b44d39695bc642e61` (Stage 10) |
| Compose | backend/frontend healthy; nginx/postgres/redis/queue/scheduler up |
| Stage 10 on VPS | migration `create_stage10_service_tables` present; `services=1`; Service controllers present |
| `user_ui_preferences` | **absent** |

### Financial / ops counts (pre)

| Metric | Count |
|--------|-------|
| payments | 3 |
| cash_handover_requests | 1 |
| collector_wallets | 3 |
| branch_cashboxes | 2 |
| payment_reversals | 3 |
| customers | 6964 |
| tickets | 2 |
| tasks | 4 |
| installations | 4 |
| crm_leads | 1 |
| crm_lead_sources | 10 |
| services | 1 |
| inv_bal_rows | 5 |
| inv_products | 2 |
| inv_equipment | 3 |
| inv_on_hand_sum | 18.000 |

---

## Deploy steps

1. Confirmed PR HEAD `5e44798` has **no** Stage 10 `Service` model / `create_services_table` lifecycle code
2. Built & pushed `cursor/stage-9-1-deploy-from-s10` @ `71ee2d7`
3. `./scripts/sync-to-vps.sh` from deploy branch → `root@209.38.194.184:/opt/collection-system/`
4. VPS `./scripts/deploy.sh` (build, up, migrate, `RolePermissionSeeder`, skip admin reinstall)
5. Migration `2026_07_17_190000_create_user_ui_preferences_table` → **Ran** (batch **18**); table exists
6. Stage 10 migration remains Ran (batch **17**); Service controllers still on disk
7. Wrote host `.deployed-sha` = `71ee2d7a371cba353efd4ccffbb33444eb7ab7cb` + `APP_COMMIT_SHA` in `.env`
8. Re-ran `RolePermissionSeeder`

---

## Smoke

| Check | Result |
|-------|--------|
| `https://finance.mns.af/up` | **200** |
| `/en/apps` | **200** (login redirect OK if unauthenticated) |
| `/fa/apps` | **200** |
| `user_ui_preferences` table | **exists** (0 rows — no seed noise) |
| Stage 10 `services` table / controllers | **preserved** (`services=1`) |
| SSH key `~/.ssh/id_ed25519` | **KEY_KEPT** |

---

## Financial / ops counts (post) — unchanged

| Metric | Pre | Post | Match |
|--------|-----|------|-------|
| payments | 3 | 3 | **MATCH** |
| cash_handover_requests | 1 | 1 | **MATCH** |
| collector_wallets | 3 | 3 | **MATCH** |
| branch_cashboxes | 2 | 2 | **MATCH** |
| payment_reversals | 3 | 3 | **MATCH** |
| customers | 6964 | 6964 | **MATCH** |
| tickets | 2 | 2 | **MATCH** |
| tasks | 4 | 4 | **MATCH** |
| installations | 4 | 4 | **MATCH** |
| crm_leads | 1 | 1 | **MATCH** |
| crm_lead_sources | 10 | 10 | **MATCH** |
| services | 1 | 1 | **MATCH** |
| inv_on_hand_sum | 18.000 | 18.000 | **MATCH** |
| inv_products | 2 | 2 | **MATCH** |
| inv_equipment | 3 | 3 | **MATCH** |

---

## Notes for reviewers

- **PR #16** (or current stage-9.1 PR) should stay based on `cursor/stage-9-inventory-assets` for the stacked review. Do not force-merge Stage 10 into that PR just to match production.
- Production now runs **Stage 10 + Stage 9.1 UI**. Future stage-9.1-only syncs must either use the deploy lineage or an equivalent merge before `sync-to-vps.sh --delete`.
- Deploy branch: https://github.com/nimroozy/finance/tree/cursor/stage-9-1-deploy-from-s10 (no PR required unless desired for audit).

---

## Verdict

**Stage 9.1 UI deployed to VPS successfully.** Stage 10 preserved. Financial/inventory/CRM counts unchanged. `user_ui_preferences` migrated. Health + `/en/apps` + `/fa/apps` OK. **KEY_KEPT.**
