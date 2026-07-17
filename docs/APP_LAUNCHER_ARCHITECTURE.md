# App Launcher Architecture

## Purpose

Central entry after login: a permission-aware grid of applications rather than a single nested sidebar for every module.

## Primary route

`/{locale}/apps`

Post-login redirect (`postLoginPath`):

1. Force password change → `/change-password`
2. Else if `default_app_id` resolves in the catalog → that app’s `href`
3. Else → `/apps`

## Catalog

Source of truth: `frontend/src/config/app-catalog.ts`.

Each app defines:

| Field | Role |
|-------|------|
| `id` | Stable key (favorites / recent / default) |
| `href` | Workspace entry path |
| `icon` | Lucide icon component |
| `group` | Launcher section grouping |
| `permissions` | Any-of gate for visibility |
| `featureFlag?` | Optional flag from `feature-flags.ts` |
| `nameKey` / `descriptionKey` | i18n under `apps.*` |
| Role default order maps | Collector, support, NOC, sales, inventory, manager, admin |

Stage 10 Services is included on the Stage 10.1 integrated tip (`service_lifecycle` feature flag). Catalog cards remain permission-gated — no dead cards for disabled modules (e.g. Radius).

## Visibility

```
visible = featureFlag enabled (if set)
       AND user has ANY listed permission
```

Unauthorized apps are not rendered. Direct deep links still hit backend policies; UI shows `/403` when the shell detects forbidden access patterns.

## Preferences (server-backed)

Table: `user_ui_preferences` (one row per user).

| Field | Use |
|-------|-----|
| `favorite_app_ids` | Favorites section + star toggle |
| `recent_app_ids` | Recent section (capped, MRU) |
| `default_app_id` | Post-login landing |
| `theme` | light \| dark \| system |
| `locale` | Preferred locale (optional) |
| `calendar_system` / `date_format` | Display prefs |
| `bottom_nav_overrides` | Optional mobile bottom-nav IDs |
| `collapsed_nav_groups` | Sidebar collapse hints |

API (auth):

- `GET/PUT /api/v1/me/ui-preferences`
- `POST/DELETE /api/v1/me/ui-preferences/favorites/{appId}`
- `POST /api/v1/me/ui-preferences/recent/{appId}`
- `PUT /api/v1/me/ui-preferences/favorites/reorder`

Client: `frontend/src/lib/ui-preferences.ts` — localStorage cache for FOUC-safe theme + optimistic UX; server remains authoritative.

## Shell composition

```
ThemeProvider + AuthProvider (locale layout)
└── AppShell
    ├── AppHeader (launcher button → /apps, search, quick-create, theme, lang, user)
    ├── Context sidebar (desktop, per current app from catalog)
    ├── Mobile drawer + BottomNav (≤5 items, role-aware)
    └── Page content
```

Resolving “current app”: `resolveAppFromPath(pathname)` maps the route into a catalog app for title + context nav (`APP_CONTEXT_NAV`).

## Quick create & search

- Quick-create menu lists only actions the user can perform and that route to real pages.
- Global search (Ctrl/Cmd+K) uses existing operations search plus catalog app hits.

## Components

| Component | Path |
|-----------|------|
| AppCard / AppGrid | `components/launcher/` |
| AppHeader | `components/launcher/app-header.tsx` |
| BottomNav | `components/launcher/bottom-nav.tsx` |
| ThemeSwitcher | `components/theme-switcher.tsx` |
| ForbiddenPage | `components/forbidden-page.tsx` |
