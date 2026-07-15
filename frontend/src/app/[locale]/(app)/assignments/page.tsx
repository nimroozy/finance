"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { exportAssignments, listAssignments } from "@/lib/assignments";
import { listCollectors } from "@/lib/collectors";
import type { Collector, CustomerAssignment } from "@/lib/types";
import { formatDate, formatMoney } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function AssignmentsPage() {
  const t = useTranslations("assignments");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canManage = useAuthStore((s) => s.hasPermission("assignments.manage"));
  const canExport = useAuthStore((s) => s.hasPermission("assignments.export"));

  const [rows, setRows] = useState<CustomerAssignment[]>([]);
  const [collectors, setCollectors] = useState<Collector[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [status, setStatus] = useState("");
  const [collectorId, setCollectorId] = useState("");
  const [activeOnly, setActiveOnly] = useState(true);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [exporting, setExporting] = useState(false);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listAssignments({
          page,
          status: status || undefined,
          collector_id: collectorId || undefined,
          is_active: activeOnly ? true : undefined,
        });
        setRows(res.data);
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
    [status, collectorId, activeOnly, tCommon],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  useEffect(() => {
    void listCollectors({ is_active: true })
      .then((res) => setCollectors(res.data))
      .catch(() => setCollectors([]));
  }, []);

  async function onExport() {
    setExporting(true);
    setError(null);
    try {
      await exportAssignments({
        status: status || undefined,
      });
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("exportFailed"));
    } finally {
      setExporting(false);
    }
  }

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          <>
            {canManage ? (
              <>
                <Link href="/assignments/new">
                  <Button>{t("create")}</Button>
                </Link>
                <Link href="/assignments/bulk">
                  <Button variant="secondary">{t("bulk")}</Button>
                </Link>
              </>
            ) : null}
            {canExport ? (
              <Button
                variant="secondary"
                disabled={exporting}
                onClick={() => void onExport()}
              >
                {t("export")}
              </Button>
            ) : null}
          </>
        }
      />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      <Panel className="mb-4 p-4">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
          <Select
            value={status}
            onChange={(e) => setStatus(e.target.value)}
            className="sm:max-w-xs"
          >
            <option value="">{tCommon("all")}</option>
            {[
              "assigned",
              "accepted",
              "in_progress",
              "closed",
              "cancelled",
              "reassigned",
              "fully_resolved",
            ].map((s) => (
              <option key={s} value={s}>
                {s}
              </option>
            ))}
          </Select>
          <Select
            value={collectorId}
            onChange={(e) => setCollectorId(e.target.value)}
            className="sm:max-w-xs"
          >
            <option value="">{t("selectCollector")}</option>
            {collectors.map((c) => (
              <option key={c.id} value={c.id}>
                {c.user?.name ?? `#${c.id}`}
              </option>
            ))}
          </Select>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={activeOnly}
              onChange={(e) => setActiveOnly(e.target.checked)}
            />
            {t("activeOnly")}
          </label>
        </div>
      </Panel>

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : rows.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("customer")}</th>
                  <th className="px-4 py-3 font-medium">{t("collector")}</th>
                  <th className="px-4 py-3 font-medium">{t("status")}</th>
                  <th className="px-4 py-3 font-medium">{t("priority")}</th>
                  <th className="px-4 py-3 font-medium">{t("outstanding")}</th>
                  <th className="px-4 py-3 font-medium">{t("dueDate")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-border last:border-0">
                    <td className="px-4 py-3">
                      <Link
                        href={`/assignments/${row.id}`}
                        className="font-medium text-primary hover:underline"
                      >
                        {row.customer?.contact_name || `#${row.customer_id}`}
                      </Link>
                    </td>
                    <td className="px-4 py-3">
                      {row.collector?.user?.name || `#${row.collector_id}`}
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge status={row.status} />
                    </td>
                    <td className="px-4 py-3">{row.priority}</td>
                    <td className="px-4 py-3">
                      {formatMoney(
                        row.debt_snapshot_outstanding ??
                          row.customer?.outstanding_receivable,
                        row.debt_snapshot_currency ?? row.customer?.currency,
                        locale,
                      )}
                    </td>
                    <td className="px-4 py-3">
                      {formatDate(row.due_date, locale)}
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
