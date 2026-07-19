# Stage 10.5 — Delivery Report

**Branch:** `claude/stage-10-5-professional-ui`
**HEAD:** `6ba18adc60a30d20f6c47070ade5e61d71a75618`
**Baseline (merge base):** `f2ff842653b59f54ffeff874086ff521565cb488` (Stage 10.4 tip)
**Commits above baseline:** 25 (small, logical, not squashed)
**Date:** 2026-07-18

Stage 10.5 turns the functional operator screens into a professional,
bilingual (EN/FA + RTL), light/dark, mobile-capable application on a single
shared component library. Scope was strictly UI + supporting picker/OpenAPI
work — **no roles/permissions/org-hierarchy/branch reseeding, no Stage 11,
no production deployment.**

## Required completion state

| Gate | Required | Result |
|---|---|---|
| `npm run lint` | exit 0 | ✅ exit 0 |
| `npx tsc --noEmit` | exit 0 | ✅ exit 0 |
| `npm run build` | exit 0 | ✅ exit 0 (`✓ Compiled successfully`) |
| `npm run i18n:check` | exit 0 | ✅ exit 0 (`2056 keys match`) |
| OpenAPI valid | valid | ✅ `js-yaml` exit 0; `@redocly/cli lint` exit 0 (`API description is valid`, 0 errors, warnings only) |
| Offline fonts | no font download | ✅ no `next/font/google` / `fonts.googleapis` anywhere; system stacks only |
| Required real UI skips | 0 | ✅ Stage 10.5 UI-driven suite: 0 skips |
| Critical UI defects | 0 | ✅ all found defects fixed |
| High-severity UI defects | 0 | ✅ all found defects fixed |

## Exact validation output

```
$ npm run lint                 → exit 0
$ npx tsc --noEmit             → exit 0
$ npm run build                → exit 0  (✓ Compiled successfully in 14.6s)
$ npm run i18n:check           → exit 0  (i18n:check OK — 2056 keys match between en.json and fa.json)
$ npm run e2e:mocked           → exit 0  (158 passed, 2 skipped)
$ npx js-yaml docs/openapi.yaml       → exit 0
$ npx @redocly/cli lint docs/openapi.yaml → exit 0  (valid; 0 errors, 355 warnings)
```

### Stage 10.5 UI-driven acceptance (the deliverable suite)

```
$ npx playwright test -c playwright.acceptance.config.ts e2e-real/acceptance.ui-driven.spec.ts
  18 passed (3 flows × 6 projects: desktop-en/fa, mobile-en/fa, small-mobile-en/fa)
  0 required skips
```

### Accessibility (axe WCAG 2.1 A/AA, serious+critical)

```
$ A11Y=1 … playwright test … e2e-real/acceptance.a11y.spec.ts
  4 passed (desktop-en/fa, mobile-en/fa) — 0 serious/critical on every screen
```

### Full strict `npm run e2e:acceptance` (all acceptance specs, 6 projects)

```
90 passed, 36 failed, 6 skipped   (runner exit 1)
```

- **90 passed** = all Stage 10.4 read-path production tests + **all 18 Stage
  10.5 UI-driven tests** + the workflow tests that don't touch money.
- **6 skipped** = the optional external Zoho-write test (1 × 6 projects),
  annotated optional.
- **36 failed** = exactly 6 Stage 10.4 write-path workflow tests × 6 projects.
  **Every one fails at the shared `fixtureIds()` helper's `/api/v1/invoices`
  call with `Call to undefined function App\Support\bcadd()`** — the missing
  `bcmath` extension, not a Stage 10.5 regression. See below.

### Backend suite

```
$ php -m        → bcmath NOT present (calendar, ctype, curl, … no bcmath)
$ php artisan test → 255 passed, 46 failed (1150 assertions), Duration 416s
```

All 46 backend failures are the identical `bcadd()` error (46 FAILED lines,
46 `bcadd` occurrences — 1:1). `git diff baseline..HEAD -- Money.php
CreatesPaymentFixtures.php` is **empty**, proving the money code is unchanged
from Stage 10.4 and the failures are purely the environment.

## bcmath escalation (must not be dismissed)

The PHP `bcmath` extension is absent from this sandbox and **cannot** be
installed here: `apt-get install php8.4-bcmath` → `403 Forbidden` from
`ppa.launchpadcontent.net` (organization egress policy; not retried per the
agent-proxy README). Full detail in `STAGE_10_5_KNOWN_ISSUES.md#1`.

**Action required outside this sandbox:** re-run `php artisan test` and
`npm run e2e:acceptance` on a runtime with `bcmath`
(`docker-php-ext-install bcmath`, or an image where the package fetch is
allowed). The financial tests are expected to pass there.

## What was delivered

- **Design system + shell + launcher** (Stage 10.5A): tokens, light/dark,
  offline system fonts, sidebar/header/breadcrumbs/mobile-nav, redesigned
  launcher. `STAGE_10_5_PROFESSIONAL_UI.md`, `STAGE_10_5_COMPONENT_LIBRARY.md`.
- **Customers** app: list (server-driven multi-field search, branch/status/
  sync filters, mobile cards) + 10-tab detail with honest empty/error states.
- **Payments** app: guided collector workflow (picker + confirmation dialog),
  admin list, reversals, sync-failures, reports.
- **Collections** app: team assignments, routes (list + detail stop
  breakdown), visits (list + detail), debtors, promises, temporary
  assignments, ownership conflicts (resolve action added), handovers, and a
  new collector-performance view — all on the shared library.
- **Pickers**: enriched, typed payloads for Customer/Collector/Branch/
  Service/Product/Equipment/User with dedicated verified endpoints
  (`STAGE_10_5_OPENAPI_RESULTS.md`), no unnecessary sensitive fields.
- **Real UI-driven acceptance** across the 6-project matrix, strict console/
  network guards (`STAGE_10_5_UI_ACCEPTANCE.md`).
- **Visual review**: 60 screenshots, EN/FA × desktop/mobile × light/dark
  where specified (`STAGE_10_5_VISUAL_REVIEW.md`).
- **Accessibility**: axe WCAG A/AA green after real fixes
  (`STAGE_10_5_ACCESSIBILITY_RESULTS.md`).
- **Mobile & RTL** results (`STAGE_10_5_MOBILE_RESULTS.md`,
  `STAGE_10_5_RTL_RESULTS.md`).

## Real defects found and fixed during acceptance

1. `MobileRecordCard` used a raw `<a>` → full reload + reverse-proxy redirect
   loop; switched to locale-aware `Link`.
2. Collector payment page: error state had no retry action; added `onRetry`.
3. Cascade-layer bug: unlayered `a { color: inherit }` overrode Tailwind v4
   `text-white`, making the active nav item 1.39:1 contrast; moved base
   resets into `@layer base`.
4. Unlabeled selects/inputs (`select-name`, `label`) and an unnamed mobile
   account button (`button-name`) — added aria-labels / label associations.

## Not done (out of scope or environment-blocked)

- **Push:** `git push` → 403 (write access unavailable this session). Work
  exported instead (below).
- **Preview deployment:** not performed — production is out of scope, no
  non-production target/credentials were provided to this environment, and
  credentials must be delivered securely outside Git. The reproducible
  preview recipe is the acceptance stack in `STAGE_10_5_UI_ACCEPTANCE.md`.
- **Not touched:** RBAC/roles/permissions/org hierarchy/branch reseeding,
  Stage 11, Tasks/Support/CRM/Installations/Inventory/Services app redesigns,
  any production deployment.

## Export (push unavailable)

The branch is preserved outside the remote environment as:
`stage-10-5-professional-ui.bundle`, a `stage-10-5-patches/` format-patch
series, and `stage-10-5-professional-ui.diff` (full binary diff from
baseline). Delivered as downloadable files.

## Security note (persisted)

An SSH private key for a production host was pasted in chat earlier in this
engagement. It was **not** used for any purpose, and production deployment
was refused. That key must be considered compromised — **rotate/revoke it
immediately.**
