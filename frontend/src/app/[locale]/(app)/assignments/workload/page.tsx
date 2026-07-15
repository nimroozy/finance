"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { getCollectorsWorkload } from "@/lib/assignments";
import { listBranches } from "@/lib/auth";
import type { Branch, CollectorWorkload } from "@/lib/types";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function WorkloadPage() {
  const t = useTranslations("assignments");
  const tCommon = useTranslations("common");

  const [rows, setRows] = useState<CollectorWorkload[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [branchId, setBranchId] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getCollectorsWorkload(branchId || undefined);
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [branchId, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    void listBranches(1)
      .then((res) => setBranches(res.data))
      .catch(() => setBranches([]));
  }, []);

  return (
    <div>
      <PageHeader title={t("workload")} subtitle={t("subtitle")} />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      <Panel className="mb-4 flex flex-col gap-2 p-4 sm:flex-row sm:items-center">
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
        <Button variant="secondary" onClick={() => void load()}>
          {tCommon("retry")}
        </Button>
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
                  <th className="px-4 py-3 font-medium">{t("collector")}</th>
                  <th className="px-4 py-3 font-medium">Code</th>
                  <th className="px-4 py-3 font-medium">Active</th>
                  <th className="px-4 py-3 font-medium">Max</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr
                    key={row.collector_id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-4 py-3 font-medium">
                      {row.name || `#${row.collector_id}`}
                    </td>
                    <td className="px-4 py-3">{row.employee_code || "—"}</td>
                    <td className="px-4 py-3">{row.active_assignments}</td>
                    <td className="px-4 py-3">
                      {row.max_active_assignments ?? "—"}
                    </td>
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
