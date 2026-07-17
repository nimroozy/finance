# Stage 9.1 — Unified App Launcher, Bilingual UI & UX Stabilization

## Status

**In progress / delivered on branch** `cursor/stage-9-1-unified-app-ui`.

**Out of scope:** Stage 10 service lifecycle (separate branch — **not** in this PR). Do not alter accounting calculations, inventory ledger rules, CRM/installation business workflows, or Zoho sync behavior.

## Objective

Turn the platform into an app-based workspace: after login, staff see a permission-aware launcher (`/apps`) instead of a dense menu, with consistent EN/FA + RTL, dark mode, mobile navigation, and shared shell UX.

Visual direction: Odoo-style launcher simplicity + calm enterprise spacing, using the existing brand tokens (teal / sand). Do not copy proprietary assets.

## Delivered on this branch

| Area | What shipped |
|------|----------------|
| App catalog | `frontend/src/config/app-catalog.ts` — id, href, Lucide icon, group, permissions, role default order |
| Launcher | `/apps` — favorites, recent, search, grouped all-apps grid |
| Preferences API | `user_ui_preferences` + `/api/v1/me/ui-preferences*` |
| Shell | Global header (launcher, search, quick-create, notifications, language, theme, user), context sidebar, mobile drawer, bottom nav |
| Theme | Light / dark / system via `ThemeProvider` → `html.dark` + CSS variables |
| i18n | `apps.*`, `launcher.*`, `shell.*` in `en.json` / `fa.json`; `npm run i18n:check` |
| Error UX | `/403` forbidden page, improved empty/error workspace patterns |
| Tests | `Stage91UiPreferencesTest`, `e2e/stage91-launcher.spec.ts`, `e2e/stage91-matrix.spec.ts` |

## Hard rules

1. No Stage 10 Services domain or Radius provisioning on this branch.
2. No business-logic changes to ledger / payments / cash / Zoho posting.
3. Backend policies remain authoritative — hiding UI is not security.
4. Prefer translation keys for all user-facing copy; keep technical values LTR in RTL.

## Related docs

- [APP_LAUNCHER_ARCHITECTURE.md](APP_LAUNCHER_ARCHITECTURE.md)
- [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)
- [RESPONSIVE_UI_STANDARD.md](RESPONSIVE_UI_STANDARD.md)
- [EN_FA_LOCALIZATION.md](EN_FA_LOCALIZATION.md)
- [RTL_IMPLEMENTATION.md](RTL_IMPLEMENTATION.md)
- [UI_ERROR_HANDLING.md](UI_ERROR_HANDLING.md)
- [UI_TEST_MATRIX.md](UI_TEST_MATRIX.md)
- Roadmap: [STAGE_ROADMAP.md](STAGE_ROADMAP.md)

## Verification

```bash
cd frontend && npm run lint && npm run build && npm run i18n:check
npx playwright test stage91
cd ../backend && php artisan test --filter=Stage91
```
