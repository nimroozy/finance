# Stage 10.5 — Professional UI

Branch `claude/stage-10-5-professional-ui`, on top of Stage 10.4
(`f2ff842653b59f54ffeff874086ff521565cb488`).

## Goal

Turn the functional-but-rough operator screens into a consistent,
professional, bilingual (EN/FA), light/dark, mobile-capable application —
comparable in polish to Stripe Dashboard / Linear / Odoo / modern Apple
system apps, without copying any of them.

## Design system foundation (Stage 10.5A)

- **Tokens** (`frontend/src/app/globals.css`): a single source of truth for
  color, surface, border, text, focus, shadow, and motion, defined for both
  light and dark via `:root` / `.dark` and exposed to Tailwind v4 through
  `@theme inline`. Semantic names (`--primary`, `--surface`,
  `--surface-elevated`, `--muted`, `--danger`, `--success`, `--warning`,
  `--focus`) rather than raw palette values, so a component never hard-codes
  a hex.
- **Fonts:** system font stacks only — `--font-system-sans` and
  `--font-system-arabic`. **No Google Font is downloaded at build or run
  time**, so builds are fully offline and there is no FOUC network hop. The
  Arabic/Dari stack is applied automatically under `html[dir="rtl"]`.
- **Light/dark:** a blocking inline script in `layout.tsx` applies the
  cached theme (`ui-preferences-cache`) before first paint to avoid a flash;
  `ThemeProvider` then hydrates and syncs the choice to the server. Base
  element resets live in `@layer base` so Tailwind color utilities win the
  cascade (see the a11y fix in `STAGE_10_5_ACCESSIBILITY_RESULTS.md`).

## Application shell (Stage 10.5A)

- Professional sidebar with per-app context navigation
  (`APP_CONTEXT_NAV` in `src/config/app-catalog.ts`), collapsible, with an
  active-item treatment that meets contrast requirements.
- Header with global search, quick-create, notifications, locale switcher,
  theme switcher, and an account menu (icon-only on mobile, labelled for
  assistive tech).
- Breadcrumbs, mobile bottom navigation, and a redesigned app launcher
  grouped by domain (Operations, Field, Finance, CRM & Sales, Inventory,
  Insights, Administration) with favourites and recents.
- Full EN/FA parity and RTL mirroring throughout (`STAGE_10_5_RTL_RESULTS.md`).

## Screen redesigns (Stage 10.5B)

Every reworked screen uses the shared component library
(`STAGE_10_5_COMPONENT_LIBRARY.md`) — no parallel component system.

- **Customers:** list with server-driven search (name, company, number,
  phone, mobile, WhatsApp, Zoho contact id), branch/status/sync filters,
  desktop table + mobile record cards; detail page with `DetailHeader` and
  ten lazy-loaded tabs, honest empty/error states where backend data does
  not exist.
- **Payments:** guided collector workflow (customer → invoices → method →
  preview → confirm) with a searchable `CustomerPicker` (no raw numeric id)
  and a confirmation dialog before the irreversible submit; admin list,
  reversals, sync-failures, and payment reports rebuilt on `DataTable` +
  `ErrorState`.
- **Collections:** team assignments, routes (list + detail with
  current/completed/remaining stop breakdown), visits (list + detail),
  debtors, promises-to-pay, temporary assignments, ownership conflicts
  (with the previously-missing resolve action), handovers, and a new
  collector-performance view composed from existing report endpoints.

## Principles applied

- No raw numeric IDs in any operator input — every entity is chosen through
  a searchable picker.
- No silently-swallowed failures — every fetch renders a typed `ErrorState`
  with a retry action, or a toast for action outcomes.
- No `locale === "fa" ? … : …` inline conditionals for copy — all strings go
  through `next-intl` with enforced EN/FA key parity (`npm run i18n:check`).
- Mobile-first density: field-workflow primary actions are 44–48px tall.
