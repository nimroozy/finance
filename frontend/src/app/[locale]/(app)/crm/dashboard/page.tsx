"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { getCrmDashboard, type CrmDashboard } from "@/lib/crm";
import {
  BranchPicker,
  ErrorWorkspace,
  MetricCard,
  WorkspaceHeader,
} from "@/components/ops";
import { Button } from "@/components/ui/button";
import { LoadingState, Panel } from "@/components/ui/layout";

export default function CrmDashboardPage() {
  const t = useTranslations("crm");
  const tCommon = useTranslations("common");
  const [data, setData] = useState<CrmDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [branchId, setBranchId] = useState("");
  const [branchLabel, setBranchLabel] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getCrmDashboard(branchId || undefined);
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
    <div className="space-y-4">
      <WorkspaceHeader
        title={t("dashboardTitle")}
        subtitle={t("dashboardSubtitle")}
        actions={
          <>
            <Link href="/crm/pipeline">
              <Button variant="secondary">{t("pipelineTitle")}</Button>
            </Link>
            <Link href="/crm/leads">
              <Button variant="secondary">{t("leadsTitle")}</Button>
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
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <MetricCard label={t("metrics.leadsTotal")} value={data.leads_total} tone="primary" />
            <MetricCard
              label={t("metrics.openFollowUps")}
              value={data.open_follow_ups}
              tone="warning"
              href="/crm/follow-ups"
            />
            <MetricCard
              label={t("metrics.overdueFollowUps")}
              value={data.overdue_follow_ups}
              tone="danger"
              href="/crm/follow-ups?status=overdue"
            />
            <MetricCard
              label={t("metrics.quotesAccepted")}
              value={data.quotes_accepted}
              tone="success"
              href="/crm/quotations?status=accepted"
            />
            <MetricCard label={t("metrics.converted")} value={data.converted} tone="success" />
            <MetricCard
              label={t("metrics.pipelineValue")}
              value={data.pipeline_value.toLocaleString()}
              tone="primary"
            />
            <MetricCard
              label={t("metrics.estimatedMrr")}
              value={data.estimated_mrr.toLocaleString()}
              tone="neutral"
            />
          </div>
          <Panel className="p-4">
            <h2 className="mb-3 text-sm font-semibold">{t("metrics.byStage")}</h2>
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              {Object.entries(data.by_stage || {}).map(([stage, count]) => (
                <div
                  key={stage}
                  className="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm"
                >
                  <span>{t(`stages.${stage}` as "stages.lead")}</span>
                  <span className="font-semibold">{count}</span>
                </div>
              ))}
            </div>
          </Panel>
        </>
      ) : null}
    </div>
  );
}
