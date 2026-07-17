# Design System (Stage 9.1)

## Source of tokens

CSS variables live in `frontend/src/app/globals.css`.

- Light values on `:root`
- Dark overrides on `html.dark`
- Tailwind v4 maps semantic colors via `@theme inline`

Prefer tokens (`bg-surface`, `text-foreground`, `border-border`, `text-primary`, status colors) over one-off hex in components.

## Brand & surfaces

| Token | Role |
|-------|------|
| `--primary` | Brand teal actions / active nav |
| `--sand` / `--sand-soft` | Secondary accent / soft highlights |
| `--surface` | Page background |
| `--surface-elevated` | Header, cards, drawers |
| `--surface-muted` | Subtle panels |
| `--border` / `--border-strong` | Dividers |
| `--text` / `--text-muted` | Body / secondary copy |

## Status

| Token | Use |
|-------|-----|
| `--danger` / `--danger-soft` | Errors, destructive |
| `--success` / `--success-soft` | Completed / healthy |
| `--warning` / `--warning-soft` | Attention / SLA risk |
| `--focus` | Focus rings |

## Radius, elevation, motion

- Radius: `--radius-sm` … `--radius-xl` (moderate corners — not pill-heavy)
- Shadows: `--shadow-sm`, `--shadow`, `--shadow-lg` (minimal)
- Motion: `--motion-fast` (150ms), `--motion-med` (220ms)

Respect `prefers-reduced-motion` where animations are added.

## Typography

- LTR: IBM Plex Sans (`--font-ibm-plex-sans`)
- RTL (fa): IBM Plex Sans Arabic (`--font-ibm-plex-arabic`)

Set on `<html lang dir>` in the locale layout.

## Theme modes

`ThemeProvider` applies:

- `document.documentElement.classList.toggle("dark", …)`
- `dataset.theme = light | dark | system`

A blocking script in the locale layout reads `ui-preferences-cache` before paint to avoid flash.

## Components (patterns)

| Pattern | Guidance |
|---------|----------|
| Buttons | Shared `Button` variants; primary for one main action |
| Inputs | Clear labels, required markers, inline + server errors |
| Tables | Shared ops table patterns; mobile → cards / compact scroll |
| Badges | Status colors from tokens only |
| Empty / error | `empty-error-workspace` — message + retry / back |
| App icons | Lucide only — no emoji as production icons |
| Cards | Prefer flat surfaces; cards when they aid interaction |

## Anti-patterns

- Arbitrary per-page color systems
- Hover-only critical actions on touch devices
- Decorative glass / heavy gradients that hurt readability
- Fake dashboard numbers
