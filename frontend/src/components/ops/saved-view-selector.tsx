"use client";

import { cn } from "@/lib/utils";

export type SavedView = {
  id: string;
  label: string;
  badge?: string | number;
};

export function SavedViewSelector({
  views,
  value,
  onChange,
  className,
  label,
}: {
  views: SavedView[];
  value: string;
  onChange: (id: string) => void;
  className?: string;
  label?: string;
}) {
  return (
    <div className={cn("flex flex-wrap gap-2", className)} role="group" aria-label={label}>
      {views.map((view) => {
        const active = view.id === value;
        return (
          <button
            key={view.id}
            type="button"
            aria-pressed={active}
            onClick={() => onChange(view.id)}
            className={cn(
              "inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm font-medium transition-colors",
              active
                ? "border-primary bg-primary text-white"
                : "border-border bg-surface-elevated text-foreground hover:bg-sand-soft",
            )}
          >
            {view.label}
            {view.badge != null && view.badge !== "" ? (
              <span
                className={cn(
                  "rounded px-1.5 text-xs",
                  active ? "bg-white/20" : "bg-sand-soft text-muted",
                )}
              >
                {view.badge}
              </span>
            ) : null}
          </button>
        );
      })}
    </div>
  );
}
