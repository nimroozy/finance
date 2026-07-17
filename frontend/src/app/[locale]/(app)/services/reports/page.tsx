"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { getServicesStatusReport, type ServiceStatusReport } from "@/lib/services";
import {
  BranchPicker,
  EmptyWorkspace,
  ErrorWorkspace,
  WorkspaceHeader,
} from "@/components/ops";
import { LoadingState, Panel } from "@/components/ui/layout";

export default function ServiceReportsPage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const [data, setData] = useState<ServiceStatusReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [branchId, setBranchId] = useState("");
  const [branchLabel, setBranchLabel] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getServicesStatusReport(branchId || undefined);
      setData(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [branchId, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-4" data-testid="services-reports">
      <WorkspaceHeader title={t("reportsTitle")} subtitle={t("reportsSubtitle")} />
      <div className="max-w-sm">
        <BranchPicker
          value={branchId || null}
          selectedLabel={branchLabel}
          onChange={(opt) => {
            setBranchId(opt ? String(opt.id) : "");
            setBranchLabel(opt?.label ?? null);
          }}
        />
      </div>
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && !data ? <EmptyWorkspace /> : null}
      {data ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <Panel className="p-4">
            <h2 className="mb-3 text-sm font-semibold">{t("columns.commercial")}</h2>
            <ul className="space-y-2 text-sm">
              {data.commercial.map((row) => (
                <li key={row.commercial_status} className="flex justify-between gap-2">
                  <span>{t(`commercial.${row.commercial_status}` as "commercial.active")}</span>
                  <span className="font-medium">{row.count}</span>
                </li>
              ))}
            </ul>
          </Panel>
          <Panel className="p-4">
            <h2 className="mb-3 text-sm font-semibold">{t("columns.operational")}</h2>
            <ul className="space-y-2 text-sm">
              {data.operational.map((row) => (
                <li key={row.operational_status} className="flex justify-between gap-2">
                  <span>{t(`operational.${row.operational_status}` as "operational.online")}</span>
                  <span className="font-medium">{row.count}</span>
                </li>
              ))}
            </ul>
          </Panel>
          <Panel className="p-4">
            <h2 className="mb-3 text-sm font-semibold">{t("columns.billing")}</h2>
            <ul className="space-y-2 text-sm">
              {data.billing.map((row) => (
                <li key={row.billing_status} className="flex justify-between gap-2">
                  <span>{t(`billing.${row.billing_status}` as "billing.current")}</span>
                  <span className="font-medium">{row.count}</span>
                </li>
              ))}
            </ul>
          </Panel>
          <Panel className="p-4">
            <h2 className="mb-3 text-sm font-semibold">{t("fields.serviceType")}</h2>
            <ul className="space-y-2 text-sm">
              {data.by_type.map((row) => (
                <li key={String(row.service_type_code)} className="flex justify-between gap-2">
                  <span>{row.service_type_code || "—"}</span>
                  <span className="font-medium">{row.count}</span>
                </li>
              ))}
            </ul>
          </Panel>
        </div>
      ) : null}
    </div>
  );
}
