"use client";

import type { CatalogApp } from "@/config/app-catalog";
import { AppCard } from "@/components/launcher/app-card";
import { EmptyState } from "@/components/ui/empty-state";

export function AppGrid({
  apps,
  favorites,
  onToggleFavorite,
  onOpen,
  emptyLabel,
  counts,
}: {
  apps: CatalogApp[];
  favorites?: Set<string> | string[];
  onToggleFavorite?: (appId: string) => void;
  onOpen?: (appId: string) => void;
  emptyLabel?: string;
  counts?: Record<string, number | undefined>;
}) {
  const favSet =
    favorites instanceof Set
      ? favorites
      : new Set(favorites ?? []);

  if (apps.length === 0) {
    return emptyLabel ? <EmptyState title={emptyLabel} /> : null;
  }

  return (
    <div
      className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
      data-testid="app-grid"
    >
      {apps.map((app) => (
        <AppCard
          key={app.id}
          app={app}
          favorite={favSet.has(app.id)}
          onToggleFavorite={onToggleFavorite}
          onOpen={onOpen}
          count={counts?.[app.id]}
        />
      ))}
    </div>
  );
}
