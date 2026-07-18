"use client";

import { cn } from "@/lib/utils";

export type RecordTab = {
  id: string;
  label: string;
  count?: number;
};

/** Horizontal, scrollable tab bar for record-detail sub-views. Controlled. */
export function RecordTabs({
  tabs,
  active,
  onChange,
  className,
}: {
  tabs: RecordTab[];
  active: string;
  onChange: (id: string) => void;
  className?: string;
}) {
  return (
    <div
      role="tablist"
      className={cn(
        "-mx-4 mb-4 flex gap-1 overflow-x-auto border-b border-border px-4 sm:mx-0 sm:px-0",
        className,
      )}
    >
      {tabs.map((tab) => {
        const isActive = tab.id === active;
        return (
          <button
            key={tab.id}
            type="button"
            role="tab"
            aria-selected={isActive}
            onClick={() => onChange(tab.id)}
            className={cn(
              "shrink-0 whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition-colors",
              isActive
                ? "border-primary text-primary"
                : "border-transparent text-muted hover:text-foreground",
            )}
          >
            {tab.label}
            {typeof tab.count === "number" ? (
              <span
                className={cn(
                  "ms-1.5 rounded-full px-1.5 py-0.5 text-xs",
                  isActive ? "bg-primary/10 text-primary" : "bg-surface-muted text-muted",
                )}
              >
                {tab.count}
              </span>
            ) : null}
          </button>
        );
      })}
    </div>
  );
}
