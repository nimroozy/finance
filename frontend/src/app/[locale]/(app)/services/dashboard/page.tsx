"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { getServicesDashboard, type ServiceDashboard } from "@/lib/services";
import {
  BranchPicker,
  ErrorWorkspace,
  MetricCard,
  WorkspaceHeader,
} from "@/components/ops";
import { Button } from "@/components/ui/button";
import { LoadingState } from "@/components/ui/layout";

export default function ServicesDashboardPage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const [data, setData] = useState<ServiceDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [branchId, setBranchId] = useState("");
  const [branchLabel, setBranchLabel] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getServicesDashboard(branchId || undefined);
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
    <div className="space-y-4" data-testid="services-dashboard">
      <WorkspaceHeader
        title={t("dashboardTitle")}
        subtitle={t("dashboardSubtitle")}
        actions={
          <>
            <Link href="/services">
              <Button variant="secondary">{t("allServices")}</Button>
            </Link>
            <Link href="/services/new">
              <Button data-testid="dashboard-new-service">{t("newService")}</Button>
            </Link>
          </>
        }
      />
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
      {data ? (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <MetricCard label={t("metrics.total")} value={data.total} href="/services" tone="primary" />
            <MetricCard
              label={t("metrics.pendingActivation")}
              value={data.pending_activation}
              href="/services/pending-activation"
              tone="warning"
            />
            <MetricCard
              label={t("metrics.suspended")}
              value={data.suspended}
              href="/services/suspended"
              tone="danger"
            />
            <MetricCard
              label={t("metrics.activeMrr")}
              value={data.active_mrr}
              href="/services?commercial_status=active"
              tone="success"
            />
            <MetricCard
              label={t("metrics.expiring")}
              value={data.expiring_30_days}
              href="/services"
              tone="warning"
            />
            <MetricCard
              label={t("metrics.active")}
              value={data.by_commercial_status?.active ?? 0}
              href="/services?commercial_status=active"
            />
          </div>
        </>
      ) : null}
    </div>
  );
}
