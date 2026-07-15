"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { listBranches } from "@/lib/auth";
import {
  exportReport,
  reportAssignmentsByCollector,
  reportGpsMismatch,
  reportOverduePromises,
  reportUnassignedDebtors,
  reportVisitsByOutcome,
} from "@/lib/reports";
import type { Branch } from "@/lib/types";
import { useAuthStore } from "@/store/auth-store";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

type TabKey =
  | "assignments-by-collector"
  | "visits-by-outcome"
  | "overdue-promises"
  | "unassigned-debtors"
  | "gps-mismatch";

export default function CollectionReportsPage() {
  const t = useTranslations("collectionReports");
  const tCommon = useTranslations("common");
  const hasAssignments = useAuthStore((s) =>
    s.hasPermission("reports.assignments"),
  );
  const hasVisits = useAuthStore((s) => s.hasPermission("reports.visits"));
  const hasPromises = useAuthStore((s) => s.hasPermission("reports.promises"));

  const allTabs: { key: TabKey; label: string; allowed: boolean }[] = [
    {
      key: "assignments-by-collector",
      label: t("byCollector"),
      allowed: hasAssignments,
    },
    {
      key: "visits-by-outcome",
      label: t("byOutcome"),
      allowed: hasVisits,
    },
    {
      key: "overdue-promises",
      label: t("overduePromises"),
      allowed: hasPromises,
    },
    {
      key: "unassigned-debtors",
      label: t("unassigned"),
      allowed: hasAssignments,
    },
    {
      key: "gps-mismatch",
      label: t("gpsMismatch"),
      allowed: hasVisits,
    },
  ];
  const tabs = allTabs.filter((x) => x.allowed);

  const [tab, setTab] = useState<TabKey | null>(tabs[0]?.key ?? null);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [branchId, setBranchId] = useState("");
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [exporting, setExporting] = useState(false);

  useEffect(() => {
    void listBranches(1)
      .then((res) => setBranches(res.data))
      .catch(() => setBranches([]));
  }, []);

  useEffect(() => {
    if (!tab && tabs[0]) setTab(tabs[0].key);
  }, [tabs, tab]);

  const load = useCallback(async () => {
    if (!tab) return;
    setLoading(true);
    setError(null);
    try {
      const bid = branchId || undefined;
      let res;
      switch (tab) {
        case "assignments-by-collector":
          res = await reportAssignmentsByCollector(bid);
          break;
        case "visits-by-outcome":
          res = await reportVisitsByOutcome(bid);
          break;
        case "overdue-promises":
          res = await reportOverduePromises(bid);
          break;
        case "unassigned-debtors":
          res = await reportUnassignedDebtors(bid);
          break;
        case "gps-mismatch":
          res = await reportGpsMismatch(bid);
          break;
      }
      setRows((res.data as Record<string, unknown>[]) ?? []);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [tab, branchId, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onExport() {
    if (!tab) return;
    setExporting(true);
    setError(null);
    try {
      await exportReport(tab, branchId || undefined);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("exportFailed"));
    } finally {
      setExporting(false);
    }
  }

  const columns =
    rows.length > 0 ? Object.keys(rows[0]).filter((k) => k !== undefined) : [];

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          tab ? (
            <Button
              variant="secondary"
              disabled={exporting}
              onClick={() => void onExport()}
            >
              {t("export")}
            </Button>
          ) : null
        }
      />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      <div className="mb-4 flex flex-wrap gap-2">
        {tabs.map((item) => (
          <Button
            key={item.key}
            size="sm"
            variant={tab === item.key ? "primary" : "secondary"}
            onClick={() => setTab(item.key)}
          >
            {item.label}
          </Button>
        ))}
      </div>

      <Panel className="mb-4 p-4">
        <Select
          value={branchId}
          onChange={(e) => setBranchId(e.target.value)}
          className="sm:max-w-xs"
        >
          <option value="">{tCommon("all")}</option>
          {branches.map((b) => (
            <option key={b.id} value={b.id}>
              {b.code}
            </option>
          ))}
        </Select>
      </Panel>

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : rows.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[600px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  {columns.map((col) => (
                    <th key={col} className="px-4 py-3 font-medium">
                      {col}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((row, idx) => (
                  <tr
                    key={idx}
                    className="border-b border-border last:border-0"
                  >
                    {columns.map((col) => (
                      <td key={col} className="px-4 py-3">
                        {String(row[col] ?? "—")}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}
