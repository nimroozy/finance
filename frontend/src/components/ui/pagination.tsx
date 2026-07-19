"use client";

import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export function Pagination({
  page,
  lastPage,
  onPageChange,
  disabled = false,
  className,
}: {
  page: number;
  lastPage: number;
  onPageChange: (page: number) => void;
  disabled?: boolean;
  className?: string;
}) {
  const t = useTranslations("common");
  if (lastPage <= 1) return null;

  return (
    <div className={cn("flex items-center justify-between gap-3 border-t border-border p-3", className)}>
      <Button
        variant="secondary"
        size="sm"
        disabled={disabled || page <= 1}
        onClick={() => onPageChange(page - 1)}
      >
        {t("previous")}
      </Button>
      <span className="text-sm text-muted">{t("page", { page, total: lastPage })}</span>
      <Button
        variant="secondary"
        size="sm"
        disabled={disabled || page >= lastPage}
        onClick={() => onPageChange(page + 1)}
      >
        {t("next")}
      </Button>
    </div>
  );
}
