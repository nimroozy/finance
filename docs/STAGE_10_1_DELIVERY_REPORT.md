# Stage 10.1 Delivery Report

**Date (UTC):** 2026-07-17  
**Branch:** `cursor/stage-10-1-integrated-stable`  
**Draft PR:** https://github.com/nimroozy/finance/pull/17  
**VPS:** `root@209.38.194.184` (`/opt/collection-system`)  
**Do not merge until human review.** **Stage 11 not started.** **No Radius.**  
**KEY_KEPT** (`~/.ssh/id_ed25519` preserved).

---

## Summary

Stage **10.1 integrated stable** unifies Stages 7–10 + 9.1 shell on one tip, with regression gates green and VPS deploy recorded below.

Superseded drafts (preserve for audit — **not closed**):

- PR [#15](https://github.com/nimroozy/finance/pull/15) Stage 10 only
- PR [#16](https://github.com/nimroozy/finance/pull/16) Stage 9.1 only

Recommend closing #15/#16 later as superseded by #17.

---

## Local verification

| Gate | Result |
|------|--------|
| `php artisan test --filter='Stage7\|Stage71\|Stage8\|Stage9\|Stage91\|Stage10\|Authentication\|WhatsApp'` | **109 passed** (483 assertions) |
| `npm run lint` | **OK** |
| `npm run build` | **OK** |
| `npm run i18n:check` | **OK** (1795 keys) |
| Playwright stage7/71/8/9/91/10 (desktop+mobile) | **88 passed** |

### Playwright fixes on tip

- Mobile Create/Confirm clicks intercepted by Stage 9.1 shell / bottom nav → `e2e/helpers.ts` + ConfirmDialog `z-[60]` / `pb-24`
- Shell `quickCreate` renamed to **Quick create** / **ایجاد سریع** (a11y name collision)

---

## Deploy results

_Filled after VPS deploy._

| Item | Value |
|------|-------|
| STAMP | _TBD_ |
| Tip SHA deployed | _TBD_ |
| BEFORE `.deployed-sha` | _TBD_ |
| AFTER `.deployed-sha` | _TBD_ |
| `/api/v1/system/version` stage | _TBD_ |
| Pre backup | _TBD_ |
| Post backup | _TBD_ |
| Financial / inventory / service counts | _TBD — must MATCH_ |
| Smoke `/en/apps` `/fa/apps` `/en/services/dashboard` | _TBD_ |

---

## Explicit non-goals

- Does not merge PRs or close #15/#16
- Does not start Stage 11
- Does not enable Radius
- Does not alter payment/wallet/handover/cashbox math
- Does not delete SSH keys

## Related docs

- [STAGE_10_1_INTEGRATED_STABLE.md](STAGE_10_1_INTEGRATED_STABLE.md)
- [BRANCH_INTEGRATION_HISTORY.md](BRANCH_INTEGRATION_HISTORY.md)
- [REGRESSION_TEST_MATRIX.md](REGRESSION_TEST_MATRIX.md)
- [PRODUCTION_SMOKE_TEST.md](PRODUCTION_SMOKE_TEST.md)
