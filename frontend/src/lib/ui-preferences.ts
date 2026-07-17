import { apiFetch } from "@/lib/api";
import { getAppById } from "@/config/app-catalog";

export type UiTheme = "light" | "dark" | "system";

export type UiPreferences = {
  id?: number;
  user_id?: number;
  locale: "en" | "fa" | null;
  theme: UiTheme;
  default_app_id: string | null;
  favorite_app_ids: string[];
  recent_app_ids: string[];
  date_format: string | null;
  calendar_system: "gregorian" | "solar_hijri" | "both" | null;
  collapsed_nav_groups?: Record<string, boolean> | null;
  bottom_nav_overrides?: string[] | null;
  notification_prefs?: Record<string, unknown> | null;
};

export type UiPreferencesSummary = Pick<
  UiPreferences,
  | "locale"
  | "theme"
  | "default_app_id"
  | "favorite_app_ids"
  | "recent_app_ids"
  | "date_format"
  | "calendar_system"
>;

const CACHE_KEY = "ui-preferences-cache";

const DEFAULTS: UiPreferences = {
  locale: null,
  theme: "system",
  default_app_id: null,
  favorite_app_ids: [],
  recent_app_ids: [],
  date_format: null,
  calendar_system: null,
};

function readCache(): UiPreferences | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = localStorage.getItem(CACHE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as UiPreferences;
    return { ...DEFAULTS, ...parsed };
  } catch {
    return null;
  }
}

function writeCache(prefs: UiPreferences) {
  if (typeof window === "undefined") return;
  try {
    localStorage.setItem(CACHE_KEY, JSON.stringify(prefs));
  } catch {
    // ignore quota / private mode
  }
}

export function getCachedUiPreferences(): UiPreferences {
  return readCache() ?? { ...DEFAULTS };
}

export function clearCachedUiPreferences() {
  if (typeof window === "undefined") return;
  try {
    localStorage.removeItem(CACHE_KEY);
  } catch {
    // ignore
  }
}

export async function fetchUiPreferences(): Promise<UiPreferences> {
  const res = await apiFetch<UiPreferences>("/me/ui-preferences");
  const prefs = { ...DEFAULTS, ...res.data };
  writeCache(prefs);
  return prefs;
}

export async function updateUiPreferences(
  patch: Partial<
    Pick<
      UiPreferences,
      | "locale"
      | "theme"
      | "default_app_id"
      | "favorite_app_ids"
      | "recent_app_ids"
      | "date_format"
      | "calendar_system"
      | "collapsed_nav_groups"
      | "bottom_nav_overrides"
      | "notification_prefs"
    >
  >,
): Promise<UiPreferences> {
  const res = await apiFetch<UiPreferences>("/me/ui-preferences", {
    method: "PUT",
    body: JSON.stringify(patch),
  });
  const prefs = { ...DEFAULTS, ...res.data };
  writeCache(prefs);
  return prefs;
}

export async function addFavoriteApp(appId: string): Promise<UiPreferences> {
  const res = await apiFetch<UiPreferences>(
    `/me/ui-preferences/favorites/${encodeURIComponent(appId)}`,
    { method: "POST" },
  );
  const prefs = { ...DEFAULTS, ...res.data };
  writeCache(prefs);
  return prefs;
}

export async function removeFavoriteApp(appId: string): Promise<UiPreferences> {
  const res = await apiFetch<UiPreferences>(
    `/me/ui-preferences/favorites/${encodeURIComponent(appId)}`,
    { method: "DELETE" },
  );
  const prefs = { ...DEFAULTS, ...res.data };
  writeCache(prefs);
  return prefs;
}

export async function toggleFavoriteApp(
  appId: string,
  currentlyFavorite: boolean,
): Promise<UiPreferences> {
  return currentlyFavorite ? removeFavoriteApp(appId) : addFavoriteApp(appId);
}

export async function recordRecentApp(appId: string): Promise<UiPreferences> {
  const res = await apiFetch<UiPreferences>(
    `/me/ui-preferences/recent/${encodeURIComponent(appId)}`,
    { method: "POST" },
  );
  const prefs = { ...DEFAULTS, ...res.data };
  writeCache(prefs);
  return prefs;
}

export function resolveTheme(theme: UiTheme): "light" | "dark" {
  if (theme === "light" || theme === "dark") return theme;
  if (typeof window === "undefined") return "light";
  return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}

/** Apply theme class on <html> for CSS variable switching. */
export function applyThemeClass(theme: UiTheme) {
  if (typeof document === "undefined") return;
  const resolved = resolveTheme(theme);
  document.documentElement.classList.toggle("dark", resolved === "dark");
  document.documentElement.dataset.theme = theme;
}

/** After login / locale home: default app href, else /apps. */
export function postLoginPath(user: {
  default_app_id?: string | null;
  ui_preferences?: { default_app_id?: string | null } | null;
  force_password_change?: boolean;
}): string {
  if (user.force_password_change) return "/change-password";
  const defaultId = user.ui_preferences?.default_app_id ?? user.default_app_id ?? null;
  if (defaultId) {
    const app = getAppById(defaultId);
    if (app) return app.href;
  }
  return "/apps";
}
