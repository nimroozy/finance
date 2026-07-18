"use client";

import { useTranslations } from "next-intl";
import { X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

/** Wraps filter controls (selects, pickers) with a reset action when any are active. */
export function FilterBar({
  children,
  active = false,
  onReset,
  className,
}: {
  children: React.ReactNode;
  active?: boolean;
  onReset?: () => void;
  className?: string;
}) {
  const t = useTranslations("common");
  return (
    <div className={cn("flex flex-1 flex-wrap items-center gap-2", className)}>
      {children}
      {active && onReset ? (
        <Button variant="ghost" size="sm" onClick={onReset}>
          <X className="h-3.5 w-3.5" aria-hidden />
          {t("reset")}
        </Button>
      ) : null}
    </div>
  );
}
