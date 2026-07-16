"use client";

import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/form";
import { cn } from "@/lib/utils";

export type DataTableColumn<T> = {
  key: string;
  label: string;
  render: (row: T) => React.ReactNode;
  className?: string;
};

type DataTableProps<T> = {
  rows: T[];
  columns: DataTableColumn<T>[];
  rowKey: (row: T) => React.Key;
  loading?: boolean;
  emptyLabel?: string;
  page?: number;
  lastPage?: number;
  onPageChange?: (page: number) => void;
  search?: string;
  searchPlaceholder?: string;
  onSearchChange?: (value: string) => void;
  filters?: React.ReactNode;
  mobileCard?: (row: T) => React.ReactNode;
  className?: string;
};

export function DataTable<T>({
  rows,
  columns,
  rowKey,
  loading = false,
  emptyLabel,
  page = 1,
  lastPage = 1,
  onPageChange,
  search,
  searchPlaceholder,
  onSearchChange,
  filters,
  mobileCard,
  className,
}: DataTableProps<T>) {
  const t = useTranslations("common");

  return (
    <div className={cn("overflow-hidden rounded-lg border border-border bg-surface-elevated", className)}>
      {onSearchChange || filters ? (
        <div className="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center">
          {onSearchChange ? (
            <Input
              type="search"
              value={search ?? ""}
              placeholder={searchPlaceholder ?? t("search")}
              onChange={(event) => onSearchChange(event.target.value)}
              className="sm:max-w-sm"
            />
          ) : null}
          {filters ? <div className="flex flex-1 flex-wrap gap-2">{filters}</div> : null}
        </div>
      ) : null}

      {loading ? (
        <div className="space-y-3 p-4" aria-label={t("loading")}>
          {Array.from({ length: 5 }).map((_, index) => (
            <div key={index} className="h-12 animate-pulse rounded-md bg-sand-soft" />
          ))}
        </div>
      ) : rows.length === 0 ? (
        <div className="flex min-h-36 items-center justify-center p-4 text-sm text-muted">
          {emptyLabel ?? t("empty")}
        </div>
      ) : (
        <>
          <div className={mobileCard ? "hidden overflow-x-auto sm:block" : "overflow-x-auto"}>
            <table className="w-full min-w-[720px] text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  {columns.map((column) => (
                    <th key={column.key} className={cn("px-4 py-3 text-start font-medium", column.className)}>
                      {column.label}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={rowKey(row)} className="border-b border-border last:border-0">
                    {columns.map((column) => (
                      <td key={column.key} className={cn("px-4 py-3", column.className)}>
                        {column.render(row)}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {mobileCard ? (
            <div className="divide-y divide-border sm:hidden">
              {rows.map((row) => (
                <div key={rowKey(row)} className="p-4">{mobileCard(row)}</div>
              ))}
            </div>
          ) : null}
        </>
      )}

      {onPageChange && lastPage > 1 ? (
        <div className="flex items-center justify-between gap-3 border-t border-border p-3">
          <Button variant="secondary" size="sm" disabled={loading || page <= 1} onClick={() => onPageChange(page - 1)}>
            {t("previous")}
          </Button>
          <span className="text-sm text-muted">{t("page", { page, total: lastPage })}</span>
          <Button variant="secondary" size="sm" disabled={loading || page >= lastPage} onClick={() => onPageChange(page + 1)}>
            {t("next")}
          </Button>
        </div>
      ) : null}
    </div>
  );
}
