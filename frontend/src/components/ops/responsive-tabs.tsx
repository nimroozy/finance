"use client";

import { cn } from "@/lib/utils";

export type ResponsiveTab = {
  id: string;
  label: string;
  badge?: string | number;
};

export function ResponsiveTabs({
  tabs,
  value,
  onChange,
  className,
  label,
}: {
  tabs: ResponsiveTab[];
  value: string;
  onChange: (id: string) => void;
  className?: string;
  label?: string;
}) {
  return (
    <div className={cn("w-full", className)}>
      <div className="sm:hidden">
        <label className="sr-only" htmlFor="responsive-tabs-select">
          {label}
        </label>
        <select
          id="responsive-tabs-select"
          className="h-10 w-full rounded-md border border-border bg-surface-elevated px-3 text-sm"
          value={value}
          onChange={(e) => onChange(e.target.value)}
        >
          {tabs.map((tab) => (
            <option key={tab.id} value={tab.id}>
              {tab.label}
              {tab.badge != null && tab.badge !== "" ? ` (${tab.badge})` : ""}
            </option>
          ))}
        </select>
      </div>
      <div
        className="hidden gap-1 overflow-x-auto border-b border-border sm:flex"
        role="tablist"
        aria-label={label}
      >
        {tabs.map((tab) => {
          const active = tab.id === value;
          return (
            <button
              key={tab.id}
              type="button"
              role="tab"
              aria-selected={active}
              className={cn(
                "shrink-0 border-b-2 px-3 py-2 text-sm font-medium transition-colors",
                active
                  ? "border-primary text-primary"
                  : "border-transparent text-muted hover:text-foreground",
              )}
              onClick={() => onChange(tab.id)}
            >
              {tab.label}
              {tab.badge != null && tab.badge !== "" ? (
                <span className="ms-1.5 text-xs text-muted">{tab.badge}</span>
              ) : null}
            </button>
          );
        })}
      </div>
    </div>
  );
}
