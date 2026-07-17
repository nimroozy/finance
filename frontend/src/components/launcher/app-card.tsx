"use client";

import { Star } from "lucide-react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import type { CatalogApp } from "@/config/app-catalog";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";

export function AppCard({
  app,
  favorite,
  onToggleFavorite,
  onOpen,
  count,
}: {
  app: CatalogApp;
  favorite?: boolean;
  onToggleFavorite?: (appId: string) => void;
  onOpen?: (appId: string) => void;
  count?: number;
}) {
  const t = useTranslations("apps");
  const Icon = app.icon;
  const showCount = typeof count === "number" && count > 0;

  return (
    <div
      className={cn(
        "group relative flex flex-col rounded-xl border border-border bg-surface-elevated p-4",
        "shadow-[var(--shadow-sm)] transition-[transform,box-shadow,border-color] duration-200",
        "hover:-translate-y-0.5 hover:border-border-strong hover:shadow-[var(--shadow)]",
      )}
      data-app-id={app.id}
      data-testid={`app-card-${app.id}`}
    >
      {showCount ? (
        <span
          className="absolute start-2 top-2 z-10 inline-flex min-w-5 items-center justify-center rounded-md bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-white"
          data-testid={`app-count-${app.id}`}
        >
          {count > 99 ? "99+" : count}
        </span>
      ) : null}

      {onToggleFavorite ? (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="absolute end-2 top-2 z-10 h-8 w-8 p-0"
          aria-label={favorite ? t("unfavorite") : t("favorite")}
          aria-pressed={Boolean(favorite)}
          data-testid={`app-favorite-${app.id}`}
          onClick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            onToggleFavorite(app.id);
          }}
        >
          <Star
            className={cn("h-4 w-4", favorite ? "fill-sand text-sand" : "text-muted")}
            aria-hidden
          />
        </Button>
      ) : null}

      <Link
        href={app.href}
        className="flex flex-1 flex-col gap-3 pe-6"
        onClick={() => onOpen?.(app.id)}
      >
        <span
          className={cn(
            "inline-flex h-12 w-12 items-center justify-center rounded-xl",
            "bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white",
          )}
        >
          <Icon className="h-6 w-6" aria-hidden />
        </span>
        <div>
          <p className="text-base font-semibold text-foreground">{t(app.nameKey)}</p>
          <p className="mt-1 line-clamp-2 text-xs text-muted">{t(app.descriptionKey)}</p>
        </div>
      </Link>
    </div>
  );
}
