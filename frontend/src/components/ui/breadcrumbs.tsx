"use client";

import { ChevronRight, ChevronLeft } from "lucide-react";
import { useLocale } from "next-intl";
import { Link } from "@/i18n/navigation";

export type BreadcrumbItem = {
  label: string;
  href?: string;
};

/** Breadcrumb trail. Chevron direction follows locale direction (RTL-aware). */
export function Breadcrumbs({ items }: { items: BreadcrumbItem[] }) {
  const locale = useLocale();
  const Chevron = locale === "fa" ? ChevronLeft : ChevronRight;

  if (items.length === 0) return null;

  return (
    <nav aria-label="Breadcrumb" className="mb-2 flex flex-wrap items-center gap-1 text-sm text-muted">
      {items.map((item, index) => {
        const isLast = index === items.length - 1;
        return (
          <span key={`${item.label}-${index}`} className="flex items-center gap-1">
            {item.href && !isLast ? (
              <Link href={item.href} className="hover:text-foreground hover:underline">
                {item.label}
              </Link>
            ) : (
              <span
                className={isLast ? "font-medium text-foreground" : undefined}
                aria-current={isLast ? "page" : undefined}
              >
                {item.label}
              </span>
            )}
            {!isLast ? <Chevron className="h-3.5 w-3.5 shrink-0" aria-hidden /> : null}
          </span>
        );
      })}
    </nav>
  );
}
