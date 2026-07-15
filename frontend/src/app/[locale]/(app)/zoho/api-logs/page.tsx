"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listZohoApiLogs } from "@/lib/zoho";
import type { ZohoApiLog } from "@/lib/types";
import { formatDateTime } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function ZohoApiLogsPage() {
  const t = useTranslations("zohoApiLogs");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [logs, setLogs] = useState<ZohoApiLog[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listZohoApiLogs(page);
        setLogs(res.data);
        setMeta({
          current_page: res.meta?.current_page ?? 1,
          last_page: res.meta?.last_page ?? 1,
        });
      } catch (err) {
        setError(err instanceof ApiError ? err.message : tCommon("error"));
      } finally {
        setLoading(false);
      }
    },
    [tCommon],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          <Link
            href="/zoho"
            className="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm hover:bg-sand-soft"
          >
            {t("backToZoho")}
          </Link>
        }
      />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : logs.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[880px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("when")}</th>
                  <th className="px-4 py-3 font-medium">{t("method")}</th>
                  <th className="px-4 py-3 font-medium">{t("endpoint")}</th>
                  <th className="px-4 py-3 font-medium">{t("entity")}</th>
                  <th className="px-4 py-3 font-medium">{t("httpStatus")}</th>
                  <th className="px-4 py-3 font-medium">{t("duration")}</th>
                  <th className="px-4 py-3 font-medium">{t("success")}</th>
                  <th className="px-4 py-3 font-medium">{t("error")}</th>
                </tr>
              </thead>
              <tbody>
                {logs.map((log) => (
                  <tr
                    key={log.id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-4 py-3 whitespace-nowrap">
                      {formatDateTime(log.created_at, locale)}
                    </td>
                    <td className="px-4 py-3">{log.method || "—"}</td>
                    <td className="px-4 py-3">{log.endpoint_name || "—"}</td>
                    <td className="px-4 py-3">
                      {log.entity_type || "—"}
                      {log.zoho_entity_id ? ` #${log.zoho_entity_id}` : ""}
                    </td>
                    <td className="px-4 py-3">{log.http_status ?? "—"}</td>
                    <td className="px-4 py-3">{log.duration_ms ?? "—"}</td>
                    <td className="px-4 py-3">
                      <Badge tone={log.success ? "success" : "danger"}>
                        {log.success ? tCommon("yes") : tCommon("no")}
                      </Badge>
                    </td>
                    <td className="max-w-xs truncate px-4 py-3 text-danger">
                      {log.error_message || "—"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {meta.last_page > 1 ? (
          <div className="flex items-center justify-between gap-3 border-t border-border px-4 py-3">
            <p className="text-sm text-muted">
              {tCommon("page", {
                page: meta.current_page,
                total: meta.last_page,
              })}
            </p>
            <div className="flex gap-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page <= 1 || loading}
                onClick={() => void load(meta.current_page - 1)}
              >
                {tCommon("previous")}
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page >= meta.last_page || loading}
                onClick={() => void load(meta.current_page + 1)}
              >
                {tCommon("next")}
              </Button>
            </div>
          </div>
        ) : null}
      </Panel>
    </div>
  );
}
