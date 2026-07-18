"use client";

import { ChevronRight, ChevronLeft } from "lucide-react";
import { useLocale } from "next-intl";
import { Link } from "@/i18n/navigation";
import { cn } from "@/lib/utils";
import { CardSkeleton } from "@/components/ui/skeleton";
import { EmptyState } from "@/components/ui/empty-state";

/** Single tappable record card for mobile lists (assignments, visits, debtors...). */
export function MobileRecordCard({
  title,
  subtitle,
  status,
  fields,
  onClick,
  href,
  className,
}: {
  title: React.ReactNode;
  subtitle?: React.ReactNode;
  status?: React.ReactNode;
  fields?: { label: string; value: React.ReactNode }[];
  onClick?: () => void;
  href?: string;
  className?: string;
}) {
  const locale = useLocale();
  const Chevron = locale === "fa" ? ChevronLeft : ChevronRight;
  const interactive = Boolean(onClick || href);

  const content = (
    <>
      <div className="flex min-w-0 items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="truncate text-sm font-medium text-foreground">{title}</p>
          {subtitle ? <p className="truncate text-xs text-muted">{subtitle}</p> : null}
        </div>
        <div className="flex shrink-0 items-center gap-1">
          {status}
          {interactive ? <Chevron className="h-4 w-4 text-muted/60" aria-hidden /> : null}
        </div>
      </div>
      {fields && fields.length > 0 ? (
        <dl className="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
          {fields.map((field) => (
            <div key={field.label} className="min-w-0">
              <dt className="text-muted">{field.label}</dt>
              <dd className="truncate text-foreground">{field.value}</dd>
            </div>
          ))}
        </dl>
      ) : null}
    </>
  );

  const baseClass = cn(
    "block w-full rounded-lg border border-border bg-surface-elevated p-3.5 text-start",
    interactive && "transition-colors hover:border-border-strong active:bg-surface-muted",
    className,
  );

  if (href) {
    return (
      <Link href={href} className={baseClass}>
        {content}
      </Link>
    );
  }

  if (onClick) {
    return (
      <button type="button" onClick={onClick} className={baseClass}>
        {content}
      </button>
    );
  }

  return <div className={baseClass}>{content}</div>;
}

/**
 * Card-based list for record types that don't fit a dense table (mobile-first
 * field workflows: assignments, visits, promises). Always renders cards, at
 * a tighter density on larger screens.
 */
export function ResponsiveRecordList<T>({
  rows,
  rowKey,
  renderCard,
  loading = false,
  emptyTitle,
  emptyDescription,
  className,
}: {
  rows: T[];
  rowKey: (row: T) => React.Key;
  renderCard: (row: T) => React.ReactNode;
  loading?: boolean;
  emptyTitle: string;
  emptyDescription?: string;
  className?: string;
}) {
  if (loading) return <CardSkeleton />;
  if (rows.length === 0) {
    return <EmptyState title={emptyTitle} description={emptyDescription} />;
  }

  return (
    <div className={cn("grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3", className)}>
      {rows.map((row) => (
        <div key={rowKey(row)}>{renderCard(row)}</div>
      ))}
    </div>
  );
}
