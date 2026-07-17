"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { Search } from "lucide-react";
import {
  APP_CATALOG,
  APP_GROUP_ORDER,
  isAppVisible,
  resolveLauncherRoles,
  sortAppsForRoles,
  type AppGroupId,
  type CatalogApp,
} from "@/config/app-catalog";
import { useAuthStore } from "@/store/auth-store";
import {
  fetchUiPreferences,
  getCachedUiPreferences,
  recordRecentApp,
  toggleFavoriteApp,
  type UiPreferences,
} from "@/lib/ui-preferences";
import { AppGrid } from "@/components/launcher/app-grid";
import { Input } from "@/components/ui/form";
import { LoadingState } from "@/components/ui/layout";

export default function AppsLauncherPage() {
  const t = useTranslations("launcher");
  const tApps = useTranslations("apps");
  const tCommon = useTranslations("common");
  const { user, hasAnyPermission } = useAuthStore();
  const [prefs, setPrefs] = useState<UiPreferences>(() => getCachedUiPreferences());
  const [query, setQuery] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    void fetchUiPreferences()
      .then((p) => {
        if (!cancelled) setPrefs(p);
      })
      .catch(() => {
        /* keep cache */
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const roles = useMemo(() => resolveLauncherRoles(user?.roles), [user?.roles]);

  const visibleApps = useMemo(() => {
    const list = APP_CATALOG.filter((app) => isAppVisible(app, hasAnyPermission));
    return sortAppsForRoles(list, roles);
  }, [hasAnyPermission, roles]);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return visibleApps;
    return visibleApps.filter((app) => {
      const name = tApps(app.nameKey).toLowerCase();
      const desc = tApps(app.descriptionKey).toLowerCase();
      return (
        app.id.includes(q) ||
        name.includes(q) ||
        desc.includes(q) ||
        app.group.includes(q)
      );
    });
  }, [visibleApps, query, tApps]);

  const favorites = useMemo(() => new Set(prefs.favorite_app_ids ?? []), [prefs.favorite_app_ids]);

  const favoriteApps = useMemo(
    () => filtered.filter((a) => favorites.has(a.id)),
    [filtered, favorites],
  );

  const recentApps = useMemo(() => {
    const byId = new Map(filtered.map((a) => [a.id, a]));
    const out: CatalogApp[] = [];
    for (const id of prefs.recent_app_ids ?? []) {
      const app = byId.get(id);
      if (app) out.push(app);
    }
    return out;
  }, [filtered, prefs.recent_app_ids]);

  const grouped = useMemo(() => {
    const map = new Map<AppGroupId, CatalogApp[]>();
    for (const group of APP_GROUP_ORDER) map.set(group, []);
    for (const app of filtered) {
      const list = map.get(app.group) ?? [];
      list.push(app);
      map.set(app.group, list);
    }
    return APP_GROUP_ORDER.map((id) => ({ id, apps: map.get(id) ?? [] })).filter(
      (g) => g.apps.length > 0,
    );
  }, [filtered]);

  const onToggleFavorite = useCallback(async (appId: string) => {
    const currently = favorites.has(appId);
    // Optimistic
    setPrefs((prev) => {
      const ids = new Set(prev.favorite_app_ids);
      if (currently) ids.delete(appId);
      else ids.add(appId);
      return { ...prev, favorite_app_ids: Array.from(ids) };
    });
    try {
      const next = await toggleFavoriteApp(appId, currently);
      setPrefs(next);
    } catch {
      setPrefs((prev) => {
        const ids = new Set(prev.favorite_app_ids);
        if (currently) ids.add(appId);
        else ids.delete(appId);
        return { ...prev, favorite_app_ids: Array.from(ids) };
      });
    }
  }, [favorites]);

  const onOpen = useCallback(async (appId: string) => {
    try {
      const next = await recordRecentApp(appId);
      setPrefs(next);
    } catch {
      /* ignore */
    }
  }, []);

  if (loading && visibleApps.length === 0) {
    return <LoadingState label={tCommon("loading")} />;
  }

  return (
    <div className="space-y-8 pb-8" data-testid="apps-launcher">
      <div className="space-y-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
            {t("title")}
          </h1>
          <p className="mt-1 text-sm text-muted">{t("subtitle")}</p>
        </div>
        <div className="relative max-w-xl">
          <Search
            className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
            aria-hidden
          />
          <Input
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={t("searchPlaceholder")}
            aria-label={t("searchPlaceholder")}
            className="ps-9"
            data-testid="apps-search"
          />
        </div>
      </div>

      {favoriteApps.length > 0 ? (
        <section aria-labelledby="favorites-heading" data-testid="apps-favorites">
          <h2 id="favorites-heading" className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted">
            {t("favorites")}
          </h2>
          <AppGrid
            apps={favoriteApps}
            favorites={favorites}
            onToggleFavorite={onToggleFavorite}
            onOpen={onOpen}
          />
        </section>
      ) : null}

      {recentApps.length > 0 && !query.trim() ? (
        <section aria-labelledby="recent-heading" data-testid="apps-recent">
          <h2 id="recent-heading" className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted">
            {t("recent")}
          </h2>
          <AppGrid
            apps={recentApps}
            favorites={favorites}
            onToggleFavorite={onToggleFavorite}
            onOpen={onOpen}
          />
        </section>
      ) : null}

      <section aria-labelledby="all-apps-heading" data-testid="apps-all">
        <h2 id="all-apps-heading" className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted">
          {t("allApps")}
        </h2>
        {grouped.length === 0 ? (
          <p className="py-6 text-sm text-muted">{t("noResults")}</p>
        ) : (
          <div className="space-y-8">
            {grouped.map((group) => (
              <div key={group.id}>
                <h3 className="mb-3 text-base font-semibold text-foreground">
                  {tApps(`group_${group.id}`)}
                </h3>
                <AppGrid
                  apps={group.apps}
                  favorites={favorites}
                  onToggleFavorite={onToggleFavorite}
                  onOpen={onOpen}
                />
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
