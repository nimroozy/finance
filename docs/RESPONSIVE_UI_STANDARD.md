# Responsive UI Standard

## Breakpoints (practical)

Aligned with Tailwind defaults used in the shell:

| Name | Width | Shell behavior |
|------|-------|----------------|
| Mobile | &lt; 1024px (`lg`) | No fixed desktop sidebar; drawer + bottom nav |
| Desktop | ≥ 1024px | Compact collapsible context sidebar |

Launcher grid:

| Viewport | Apps per row (approx.) |
|----------|-------------------------|
| Mobile | 2–3 |
| Tablet | 3–5 |
| Desktop | 5–7 |

App cards must remain usable at **320px** width.

## Touch targets

- Minimum ~44×44px for primary controls (favorites, bottom nav, header actions).
- No hover-only actions for critical workflows.
- Bottom nav: max **five** items, permission-filtered.

## Layout rules

1. **Launcher** — search on top; Favorites → Recent → All apps; no horizontal overflow.
2. **In-app** — desktop sidebar for context routes only; mobile uses drawer.
3. **Lists / tables** — on small screens, prefer card stacks or horizontal scroll with accessible reach; never strand required actions off-screen.
4. **Safe area** — bottom nav leaves `pb-24` content padding on small screens.
5. **Sticky primary actions** — allowed on mobile forms where appropriate.

## Density

- Generous spacing on launcher; denser but readable tables in workspaces.
- Avoid forcing desktop multi-column dashboards onto phone widths.

## Testing

Playwright projects: `desktop-chromium` and `mobile-chromium` (Pixel 5).
See [UI_TEST_MATRIX.md](UI_TEST_MATRIX.md).
