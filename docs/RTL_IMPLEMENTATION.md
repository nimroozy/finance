# RTL Implementation

## Mechanism

Locale layout sets:

```html
<html lang="fa" dir="rtl">
```

or `lang="en" dir="ltr"`.

Body font switches to IBM Plex Sans Arabic under `html[dir="rtl"]`.

Logical CSS (`border-e`, `ps`/`pe`, flex order) is preferred over physical `left`/`right` so LTR and RTL share one layout.

## What must flip

- Navigation / drawers / bottom nav
- Breadcrumbs and chevrons (use locale-aware icons in the shell)
- Form label alignment
- Tables, pagination, dropdowns, modals, toasts
- Search results and mobile drawers

## What must NOT reverse

Keep technical values LTR inside RTL pages:

- IP / MAC addresses
- Serial numbers, product codes, service / transaction numbers
- URLs, emails

Use `LtrValue` (`frontend/src/components/ltr-value.tsx`) — `dir="ltr"` + unicode-bidi isolation — for those spans.

## Currency & mixed text

- Numbers and currency may stay visually LTR when they are technical identifiers; prose remains RTL.
- Mixed Persian + English labels are expected; isolate English technical tokens.

## Verification

- Playwright: visit `/fa/apps` and assert `html[dir=rtl]`
- Manual: tables, forms, header, bottom nav, command menu
- See [UI_TEST_MATRIX.md](UI_TEST_MATRIX.md)
