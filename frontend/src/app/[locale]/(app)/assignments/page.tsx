"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { exportAssignments, listAssignments } from "@/lib/assignments";
import type { CustomerAssignment } from "@/lib/types";
import { formatDate, formatMoney } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/form";
import { PageHeader } from "@/components/ui/layout";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ErrorState } from "@/components/ui/error-state";
import { CollectorPicker, BranchPicker } from "@/components/ui/pickers";
import type { SearchableOption } from "@/components/ui/searchable-select";

const STATUSES = [
  "assigned",
  "accepted",
  "in_progress",
  "closed",
  "cancelled",
  "reassigned",
  "fully_resolved",
];

export default function AssignmentsPage() {
  const t = useTranslations("assignments");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canManage = useAuthStore((s) => s.hasPermission("assignments.manage"));
  const canExport = useAuthStore((s) => s.hasPermission("assignments.export"));

  const [rows, setRows] = useState<CustomerAssignment[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [status, setStatus] = useState("");
  const [collector, setCollector] = useState<SearchableOption | null>(null);
  const [branch, setBranch] = useState<SearchableOption | null>(null);
  const [activeOnly, setActiveOnly] = useState(true);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const [exporting, setExporting] = useState(false);
  const [exportError, setExportError] = useState<unknown>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listAssignments({
          page,
          status: status || undefined,
          collector_id: collector ? String(collector.id) : undefined,
          branch_id: branch ? String(branch.id) : undefined,
          is_active: activeOnly ? true : undefined,
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
    [status, collector, branch, activeOnly],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  async function onExport() {
    setExporting(true);
    setExportError(null);
    try {
      await exportAssignments({ status: status || undefined });
    } catch (err) {
      setExportError(err);
    } finally {
      setExporting(false);
    }
  }

  const columns: DataTableColumn<CustomerAssignment>[] = [
    {
      key: "customer",
      label: t("customer"),
      render: (row) => (
        <Link
          href={`/assignments/${row.id}`}
          className="font-medium text-primary hover:underline"
        >
          {row.customer?.contact_name || `#${row.customer_id}`}
        </Link>
      ),
    },
    {
      key: "collector",
      label: t("collector"),
      render: (row) => row.collector?.user?.name || `#${row.collector_id}`,
    },
    { key: "status", label: t("status"), render: (row) => <StatusBadge status={row.status} /> },
    { key: "priority", label: t("priority"), render: (row) => row.priority },
    {
      key: "outstanding",
      label: t("outstanding"),
      render: (row) =>
        formatMoney(
          row.debt_snapshot_outstanding ?? row.customer?.outstanding_receivable,
          row.debt_snapshot_currency ?? row.customer?.currency,
          locale,
        ),
    },
    { key: "dueDate", label: t("dueDate"), render: (row) => formatDate(row.due_date, locale) },
  ];

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
              <Button variant="secondary" disabled={exporting} onClick={() => void onExport()}>
                {t("export")}
              </Button>
            ) : null}
          </>
        }
      />

      {exportError ? (
        <div className="mb-4">
          <ErrorState error={exportError} onRetry={() => void onExport()} />
        </div>
      ) : null}

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
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:flex-wrap">
              <Select value={status} onChange={(e) => setStatus(e.target.value)} className="sm:max-w-xs">
                <option value="">{tCommon("all")}</option>
                {STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {s.replace(/_/g, " ")}
                  </option>
                ))}
              </Select>
              <CollectorPicker value={collector} onChange={setCollector} className="sm:max-w-xs" />
              <BranchPicker value={branch} onChange={setBranch} className="sm:max-w-xs" />
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={activeOnly}
                  onChange={(e) => setActiveOnly(e.target.checked)}
                />
                {t("activeOnly")}
              </label>
            </div>
          }
        />
      )}
    </div>
  );
}
