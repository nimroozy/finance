"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { getCollectorsWorkload } from "@/lib/assignments";
import { reportAssignmentsByCollector } from "@/lib/reports";
import type { AssignmentsByCollectorRow, CollectorWorkload } from "@/lib/types";
import { formatMoney } from "@/lib/utils";
import { PageHeader } from "@/components/ui/layout";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ErrorState } from "@/components/ui/error-state";
import { KpiCard } from "@/components/ui/kpi-card";
import { BranchPicker } from "@/components/ui/pickers";
import type { SearchableOption } from "@/components/ui/searchable-select";

const CLOSED_STATUSES = new Set(["closed", "cancelled", "fully_resolved"]);

interface CollectorPerformanceRow {
  collector_id: number;
  name: string | null;
  employee_code: string | null;
  active_assignments: number;
  max_active_assignments: number | null;
  open_count: number;
  closed_count: number;
  outstanding: number;
}

export default function CollectorPerformancePage() {
  const t = useTranslations("collectorPerformancePage");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [workload, setWorkload] = useState<CollectorWorkload[]>([]);
  const [byStatus, setByStatus] = useState<AssignmentsByCollectorRow[]>([]);
  const [branch, setBranch] = useState<SearchableOption | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const branchId = branch ? String(branch.id) : undefined;
      const [w, s] = await Promise.all([
        getCollectorsWorkload(branchId),
        reportAssignmentsByCollector(branchId),
      ]);
      setWorkload(w.data);
      setByStatus(s.data);
    } catch (err) {
      setError(err);
    } finally {
      setLoading(false);
    }
  }, [branch]);

  useEffect(() => {
    void load();
  }, [load]);

  const rows: CollectorPerformanceRow[] = useMemo(() => {
    return workload.map((c) => {
      const statusRows = byStatus.filter((r) => r.collector_id === c.collector_id);
      const openCount = statusRows
        .filter((r) => !CLOSED_STATUSES.has(r.status))
        .reduce((sum, r) => sum + Number(r.total), 0);
      const closedCount = statusRows
        .filter((r) => CLOSED_STATUSES.has(r.status))
        .reduce((sum, r) => sum + Number(r.total), 0);
      const outstanding = statusRows
        .filter((r) => !CLOSED_STATUSES.has(r.status))
        .reduce((sum, r) => sum + Number(r.total_outstanding ?? 0), 0);
      return {
        collector_id: c.collector_id,
        name: c.name,
        employee_code: c.employee_code,
        active_assignments: c.active_assignments,
        max_active_assignments: c.max_active_assignments,
        open_count: openCount,
        closed_count: closedCount,
        outstanding,
      };
    });
  }, [workload, byStatus]);

  const totals = useMemo(
    () => ({
      collectors: rows.length,
      activeAssignments: rows.reduce((sum, r) => sum + r.active_assignments, 0),
      outstanding: rows.reduce((sum, r) => sum + r.outstanding, 0),
    }),
    [rows],
  );

  const columns: DataTableColumn<CollectorPerformanceRow>[] = [
    {
      key: "name",
      label: t("collector"),
      render: (row) => row.name || `#${row.collector_id}`,
    },
    { key: "code", label: t("employeeCode"), render: (row) => row.employee_code || "—" },
    { key: "active", label: t("activeAssignments"), render: (row) => row.active_assignments },
    {
      key: "capacity",
      label: t("capacity"),
      render: (row) => row.max_active_assignments ?? "—",
    },
    { key: "open", label: t("openAssignments"), render: (row) => row.open_count },
    { key: "closed", label: t("closedAssignments"), render: (row) => row.closed_count },
    {
      key: "outstanding",
      label: t("outstandingDebt"),
      render: (row) => formatMoney(row.outstanding, null, locale),
    },
  ];

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {error ? (
        <ErrorState error={error} onRetry={() => void load()} />
      ) : (
        <>
          <div className="mb-4 grid gap-4 sm:grid-cols-3">
            <KpiCard label={t("totalCollectors")} value={totals.collectors} />
            <KpiCard label={t("totalActiveAssignments")} value={totals.activeAssignments} tone="primary" />
            <KpiCard
              label={t("totalOutstanding")}
              value={formatMoney(totals.outstanding, null, locale)}
              tone="warning"
            />
          </div>

          <DataTable
            rows={rows}
            columns={columns}
            rowKey={(row) => row.collector_id}
            loading={loading}
            emptyLabel={tCommon("empty")}
            filters={<BranchPicker value={branch} onChange={setBranch} className="sm:max-w-xs" />}
          />
        </>
      )}
    </div>
  );
}
