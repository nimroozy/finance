# PR #17 update note — Stage 10.1 integrated stable

**PR:** https://github.com/nimroozy/finance/pull/17 (draft)  
**Branch:** `cursor/stage-10-1-integrated-stable`  
**Tip:** `2d990ea4b45401897771829e36813e5a18196a54`

## Status

Stage 10.1 continue pass is **complete**: regression green, VPS live at `10.1-integrated-stable`, financial/inventory counts MATCH.

## Recommend (human)

- Prefer this PR as the production tip.
- Close [#15](https://github.com/nimroozy/finance/pull/15) and [#16](https://github.com/nimroozy/finance/pull/16) **later** as superseded by #17 — **do not close now** (preserve audit).
- Do **not** start Stage 11 from this PR.

## Verification checklist

- [x] PHPUnit Stage7–10 + Auth + WhatsApp
- [x] lint / build / i18n:check
- [x] Playwright stage7/71/8/9/91/10 (88)
- [x] VPS stamp `20260717T195940Z`; smoke `/en/apps` `/fa/apps` `/en/services/dashboard`
- [x] KEY_KEPT

Full write-up: [STAGE_10_1_DELIVERY_REPORT.md](STAGE_10_1_DELIVERY_REPORT.md).
