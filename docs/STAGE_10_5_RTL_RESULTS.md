# Stage 10.5 — RTL (Persian/Dari) Results

Persian/Dari is a first-class, right-to-left locale across the whole
application, not a bolt-on. Verified by the accessibility suite
(`acceptance.a11y.spec.ts`), the visual-review capture
(`acceptance.screenshots.spec.ts`, 30 Persian screenshots), and the
UI-driven acceptance suite running its full flows on the `desktop-fa`,
`mobile-fa`, and `small-mobile-fa` projects.

## Direction & typography

- `html[dir="rtl"]` is set for `fa` (asserted per-project by the a11y suite:
  `html dir attribute — rtl (expected rtl)` on `desktop-fa` and `mobile-fa`).
- The Arabic/Dari system font stack (`--font-system-arabic`) applies
  automatically under `html[dir="rtl"] body` — no font download.

## Logical layout (not hard-coded left/right)

Components use CSS logical properties throughout so mirroring is automatic:
`ps-*`/`pe-*` (padding), `ms-*`/`me-*` (margin), `text-start`/`text-end`,
`border-s`/`border-e`, and `start-*`/`end-*` positioning. Directional icons
(nav chevrons, "navigate") flip via a locale check
(`locale === "fa" ? ChevronLeft : ChevronRight`).

## Verified in RTL

| Area | Evidence |
|---|---|
| App launcher grid + groups mirror correctly | `docs/stage-10-5-screenshots/desktop-fa/launcher__fa__1440x900__{light,dark}.png` |
| Customer list table + filters mirror | `.../desktop-fa/customer-list__fa__*.png` |
| Guided payment workflow (customer → invoices → method → confirm) | UI-driven `payments` test green on `desktop-fa`/`mobile-fa`/`small-mobile-fa` |
| Collections assignment create via pickers | UI-driven `collections` test green on all `*-fa` projects |
| Customer search + open detail | UI-driven `customer search` test green on all `*-fa` projects |
| Routes / promises / visits / handovers / ownership conflicts / collector performance | `.../desktop-fa/*.png`, `.../mobile-fa/*.png` |
| No horizontal overflow in RTL | every UI-driven test calls `expectNoHorizontalOverflow` on both locales |
| Accessibility (axe WCAG A/AA) in RTL | `STAGE_10_5_ACCESSIBILITY_RESULTS.md` — `desktop-fa` and `mobile-fa` sections, 0 serious/critical |

## Copy discipline

No `locale === "fa" ? "…" : "…"` inline string conditionals remain on the
reworked screens — all user-facing copy is `next-intl` keys with enforced
EN/FA parity: `npm run i18n:check` → `OK — 2056 keys match between en.json
and fa.json` (exit 0).
