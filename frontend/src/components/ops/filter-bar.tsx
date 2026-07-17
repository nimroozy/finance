"use client";

import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export type FilterBarProps = {
  children: React.ReactNode;
  onApply?: () => void;
  onClear?: () => void;
  applyLabel?: string;
  clearLabel?: string;
  className?: string;
  loading?: boolean;
};

export function FilterBar({
  children,
  onApply,
  onClear,
  applyLabel,
  clearLabel,
  className,
  loading,
}: FilterBarProps) {
  const t = useTranslations("common");
  return (
    <div
      className={cn(
        "rounded-lg border border-border bg-surface-elevated p-4 shadow-[var(--shadow)]",
        className,
      )}
    >
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">{children}</div>
      {(onApply || onClear) ? (
        <div className="mt-4 flex flex-wrap justify-end gap-2">
          {onClear ? (
            <Button type="button" variant="secondary" size="sm" onClick={onClear} disabled={loading}>
              {clearLabel ?? t("reset")}
            </Button>
          ) : null}
          {onApply ? (
            <Button type="button" size="sm" onClick={onApply} disabled={loading}>
              {applyLabel ?? t("apply")}
            </Button>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
