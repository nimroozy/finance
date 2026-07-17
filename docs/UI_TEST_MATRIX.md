# UI Test Matrix (Stage 9.1)

## Dimensions

| Axis | Values |
|------|--------|
| Languages | English (`/en`), Persian (`/fa`) |
| Viewports | Desktop Chromium, Mobile Chromium (Pixel 5) |
| Auth | Mocked Sanctum token + `/auth/me` in Playwright |

Playwright config: `frontend/playwright.config.ts` — projects `desktop-chromium`, `mobile-chromium`.

## Specs

| File | Coverage |
|------|----------|
| `e2e/stage91-launcher.spec.ts` | Login → `/apps`, role visibility, favorites/recent, EN/FA RTL, dark mode, mobile bottom nav, quick-create + search, default app redirect, 403 |
| `e2e/stage91-matrix.spec.ts` | EN+FA smoke for `/apps`, `/payments`, `/tickets` on both viewport projects |

Backend: `backend/tests/Feature/Stage91UiPreferencesTest.php`.

## Critical checklist (brief §35)

| # | Check | Automated? |
|---|-------|-------------|
| 1 | Login redirects to launcher | Yes (launcher spec) |
| 2 | Correct apps by role | Yes |
| 3 | Unauthorized apps hidden | Yes |
| 4 | Unauthorized route → 403 | Partial (`/403` page) |
| 5 | App search | Yes |
| 6 | Favorites persist | Yes (mocked API) |
| 7 | Recent apps update | Yes |
| 8 | EN/FA switching | Yes |
| 9 | RTL correct | Yes (`dir=rtl`) |
| 10–11 | Mobile launcher / nav | Yes |
| 12 | Dark mode | Yes (`html.dark`) |
| 13–14 | Global search / quick create | Yes |
| 15+ | Deeper module workflows | Covered by stage 7–9 specs; matrix smoke for payments/tickets lists |

## Commands

```bash
cd frontend
npm run build   # required before `npm start` webServer
npm run i18n:check
npx playwright test stage91

cd ../backend
php artisan test --filter=Stage91
```

## Notes

- Matrix and launcher specs mock `/api/v1/**` — no live Zoho/WhatsApp/Radius.
- Visual regression baselines and full role crawlers remain optional follow-ups; document cosmetic issues rather than claiming zero bugs.
