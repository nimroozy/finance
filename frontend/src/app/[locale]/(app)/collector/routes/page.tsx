"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listRoutes } from "@/lib/routes";
import type { CollectionRoute } from "@/lib/types";
import { formatDate } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CollectorRoutesPage() {
  const t = useTranslations("routesPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [rows, setRows] = useState<CollectionRoute[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const today = new Date().toISOString().slice(0, 10);
      const res = await listRoutes({ per_page: 50 });
      const filtered = res.data.filter(
        (r) =>
          String(r.route_date).slice(0, 10) === today ||
          ["published", "in_progress"].includes(r.status),
      );
      setRows(filtered.length ? filtered : res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-4">
      <PageHeader title={t("title")} subtitle={t("subtitle")} />
      {error ? <Alert>{error}</Alert> : null}

      {loading ? (
        <LoadingState label={tCommon("loading")} />
      ) : rows.length === 0 ? (
        <EmptyState label={tCommon("empty")} />
      ) : (
        <div className="space-y-3">
          {rows.map((row) => (
            <Panel key={row.id}>
              <Link
                href={`/collector/routes/${row.id}`}
                className="flex items-center justify-between gap-3 p-4"
              >
                <div>
                  <p className="text-lg font-semibold text-primary">
                    {row.name}
                  </p>
                  <p className="text-sm text-muted">
                    {formatDate(row.route_date, locale)} ·{" "}
                    {row.stops?.length ?? 0} {t("stops")}
                  </p>
                </div>
                <StatusBadge status={row.status} />
              </Link>
            </Panel>
          ))}
        </div>
      )}
    </div>
  );
}
