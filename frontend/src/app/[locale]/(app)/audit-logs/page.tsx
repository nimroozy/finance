"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { listAuditLogs } from "@/lib/auth";
import { ApiError } from "@/lib/api";
import type { AuditLog } from "@/lib/types";
import { Button } from "@/components/ui/button";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function AuditLogsPage() {
  const t = useTranslations("audit");
  const tCommon = useTranslations("common");
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listAuditLogs(page);
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
      <PageHeader title={t("title")} subtitle={t("subtitle")} />
      {error ? <div className="mb-4"><Alert>{error}</Alert></div> : null}

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : logs.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[800px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("when")}</th>
                  <th className="px-4 py-3 font-medium">{t("action")}</th>
                  <th className="px-4 py-3 font-medium">{t("user")}</th>
                  <th className="px-4 py-3 font-medium">{t("branch")}</th>
                  <th className="px-4 py-3 font-medium">{t("entity")}</th>
                  <th className="px-4 py-3 font-medium">{t("ip")}</th>
                </tr>
              </thead>
              <tbody>
                {logs.map((log) => (
                  <tr
                    key={log.id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-4 py-3 whitespace-nowrap">
                      {new Date(log.created_at).toLocaleString()}
                    </td>
                    <td className="px-4 py-3 font-medium">{log.action}</td>
                    <td className="px-4 py-3">
                      {log.user?.name ?? "—"}
                    </td>
                    <td className="px-4 py-3">
                      {log.branch?.code ?? "—"}
                    </td>
                    <td className="px-4 py-3">
                      {log.entity_type
                        ? `${log.entity_type}${
                            log.entity_id ? ` #${log.entity_id}` : ""
                          }`
                        : "—"}
                    </td>
                    <td className="px-4 py-3">{log.ip_address ?? "—"}</td>
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
