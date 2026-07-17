# UI Error Handling

## Principles

Users must never see blank white pages, raw stack traces, untranslated framework errors, silent failures, or infinite spinners without recovery.

Every async screen should support:

1. Loading (skeleton or labeled loading state)
2. Empty (explained + suggested action)
3. Error (explained + retry / back)
4. Permission denial (friendly 403)

## Mapped cases

| Situation | UI response |
|-----------|-------------|
| Validation | Inline field errors + form summary |
| Permission denied | `/403` + `ForbiddenPage` (`shell.forbidden*`) |
| Not found | Workspace empty/error with return link |
| Network / timeout | Error workspace + Retry |
| Zoho / WhatsApp unavailable | Clear integration message (no fake success) |
| Session expired | Auth provider clears token → login redirect |
| Duplicate / stale conflict | Surfaced message; do not double-submit |
| Upload failure | Explicit failure + retry |

## Shared pieces

- `components/ops/empty-error-workspace.tsx` — empty + error + retry patterns
- `components/forbidden-page.tsx` + route `/(app)/403`
- `shell.errorTitle` / common retry strings in i18n

## Actions

- Only show status actions that are valid for the current record + permissions.
- Destructive actions require confirmation (and reason when required by the domain).
- After success/failure, refresh the record and keep audit history intact (backend).

## What not to ship

- Buttons that only `console.log`
- Links to missing routes
- Placeholder “coming soon” in primary staff workflows (roadmap area for admins only)
