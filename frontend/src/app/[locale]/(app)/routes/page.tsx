"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { listRoutes } from "@/lib/routes";
import type { CollectionRoute } from "@/lib/types";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/form";
import { PageHeader } from "@/components/ui/layout";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ErrorState } from "@/components/ui/error-state";

export default function RoutesPage() {
  const t = useTranslations("routesPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canManage = useAuthStore((s) => s.hasPermission("routes.manage"));

  const [rows, setRows] = useState<CollectionRoute[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [status, setStatus] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listRoutes({
          page,
          status: status || undefined,
        });
        setRows(res.data);
        setMeta({
          current_page: res.meta?.current_page ?? 1,
          last_page: res.meta?.last_page ?? 1,
        });
      } catch (err) {
        setError(err);
      } finally {
        setLoading(false);
      }
    },
    [status],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  const columns: DataTableColumn<CollectionRoute>[] = [
    {
      key: "name",
      label: t("name"),
      render: (row) => (
        <Link href={`/routes/${row.id}`} className="font-medium text-primary hover:underline">
          {row.name}
        </Link>
      ),
    },
    { key: "date", label: t("date"), render: (row) => formatDateTime(row.route_date, locale) },
    { key: "collector", label: t("collector"), render: (row) => row.collector?.user?.name || `#${row.collector_id}` },
    { key: "stops", label: t("stops"), render: (row) => row.stops?.length ?? 0 },
    { key: "status", label: t("status"), render: (row) => <StatusBadge status={row.status} /> },
    { key: "started", label: t("startedAt"), render: (row) => formatDateTime(row.started_at, locale) },
    { key: "completed", label: t("completedAt"), render: (row) => formatDateTime(row.completed_at, locale) },
  ];

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          canManage ? (
            <Link href="/routes/new">
              <Button>{t("create")}</Button>
            </Link>
          ) : null
        }
      />

      {error ? (
        <ErrorState error={error} onRetry={() => load(meta.current_page)} />
      ) : (
        <DataTable
          rows={rows}
          columns={columns}
          rowKey={(row) => row.id}
          loading={loading}
          emptyLabel={tCommon("empty")}
          page={meta.current_page}
          lastPage={meta.last_page}
          onPageChange={(page) => void load(page)}
          filters={
            <Select value={status} onChange={(e) => setStatus(e.target.value)} className="sm:max-w-xs" aria-label={t("status")}>
              <option value="">{tCommon("all")}</option>
              {["draft", "published", "in_progress", "completed", "cancelled"].map((s) => (
                <option key={s} value={s}>
                  {s.replace(/_/g, " ")}
                </option>
              ))}
            </Select>
          }
        />
      )}
    </div>
  );
}
