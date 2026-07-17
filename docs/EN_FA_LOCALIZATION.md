# English / Persian Localization

## Locales

| Code | Language | Direction |
|------|----------|-----------|
| `en` | English | LTR |
| `fa` | Persian (Farsi) | RTL |

Routing: `frontend/src/i18n/routing.ts` + `next-intl`.
Message files:

- `frontend/src/i18n/messages/en.json`
- `frontend/src/i18n/messages/fa.json`

## Namespaces (Stage 9.1 additions)

| Namespace | Purpose |
|-----------|---------|
| `apps` | App names, descriptions, groups, favorite labels |
| `launcher` | Launcher chrome (title, search, sections) |
| `shell` | Header, theme, quick-create, forbidden copy |

Existing domains continue under `common`, `nav`, `payments`, `tickets`, `inventory`, `crm`, etc.

## Rules

1. All user-facing UI strings use translation keys — no hard-coded English on pages, no hard-coded Persian in business logic.
2. Language switch updates `lang` / `dir` via locale route (`/en/...` ↔ `/fa/...`) without logout.
3. Persian copy should be natural and operational (prefer usable phrasing over literal machine translation).
4. Validation, statuses, toasts, and empty states must be keyed.

## Dates & numbers

- Store timestamps in UTC-compatible DB formats — never localized strings in columns.
- Display: English Gregorian; Persian labels + configurable Gregorian / Solar Hijri / both via `calendar_system` preference (UI preference field; display helpers may expand over time).

## Parity check

```bash
cd frontend && npm run i18n:check
```

Script: `frontend/scripts/check-i18n-keys.mjs`

- Flattens nested keys in `en.json` and `fa.json`
- Fails if either side is missing keys
- Highlights critical gaps under `apps.*`, `launcher.*`, `shell.*`, `common.*`, `nav.*`

Wire this into CI alongside lint/build.
