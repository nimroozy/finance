# Stage 10.5 — Mobile Results

The application is mobile-capable, not just desktop-shrunk. Verified on three
mobile viewport classes across both locales.

## Viewport matrix

| Project | Viewport | Locale | Touch |
|---|---|---|---|
| `mobile-en` | 390×844 | English | yes |
| `mobile-fa` | 390×844 | Persian/Dari (RTL) | yes |
| `small-mobile-en` | 320×700 | English | yes |
| `small-mobile-fa` | 320×700 | Persian/Dari (RTL) | yes |

## UI-driven acceptance on mobile

`acceptance.ui-driven.spec.ts` runs its three real UI flows (customer
search-and-open, collections assignment via pickers, guided payment
collection) on **all** mobile projects — **18/18 tests green across the six
projects, zero required skips**. Each test asserts `expectNoHorizontalOverflow`,
so the body never scrolls sideways at 390px or 320px.

## Mobile patterns

- **Record cards, not shrunk tables:** lists render `MobileRecordCard`
  (`record-list.tsx`) on narrow viewports instead of a horizontally-scrolling
  table (e.g. Customers list mobile cards).
- **Bottom navigation + collapsible chrome:** the app shell exposes a mobile
  bottom nav and an icon-collapsed header; the account-menu button collapses
  to icon-only and carries an `aria-label` for assistive tech
  (`STAGE_10_5_ACCESSIBILITY_RESULTS.md`).
- **Touch targets:** primary field-workflow actions are 44–48px
  (`h-11`/`h-12`); the guided payment step buttons are full-width and 48px.
  A few dense secondary controls (pagination) are ~36px — AA-compliant, see
  `STAGE_10_5_KNOWN_ISSUES.md#5`.
- **Client-side navigation fix:** `MobileRecordCard` now uses the
  locale-aware `Link` rather than a raw `<a>`, so tapping a card routes
  in-app instead of triggering a full reload (a real defect caught by the
  mobile acceptance run — see the `test(ui)` commit).

## Visual evidence

Full-page mobile screenshots (light, plus dark for launcher / customer list /
payment workflow) for both locales are in
`docs/stage-10-5-screenshots/mobile-en/` and `.../mobile-fa/`, catalogued in
`STAGE_10_5_VISUAL_REVIEW.md`.

## Accessibility on mobile

`acceptance.a11y.spec.ts` runs the axe WCAG A/AA audit on `mobile-en` and
`mobile-fa`: **0 serious/critical violations** after fixes
(`STAGE_10_5_ACCESSIBILITY_RESULTS.md`).
