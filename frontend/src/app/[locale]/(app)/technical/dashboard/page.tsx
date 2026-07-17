"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { getTechnicalDashboard, type TechnicalDashboard } from "@/lib/operations";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  MetricCard,
  WorkspaceHeader,
} from "@/components/ops";
import { LoadingState } from "@/components/ui/layout";

export default function TechnicalDashboardPage() {
  const t = useTranslations("operationsDashboards");
  const tCommon = useTranslations("common");
  const [data, setData] = useState<TechnicalDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getTechnicalDashboard();
      setData(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-4">
      <WorkspaceHeader title={t("technicalTitle")} subtitle={t("technicalSubtitle")} />
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {!loading && !error && data ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <MetricCard label={t("openField")} value={data.open_field} href="/tasks?view=field&type=field" />
          <MetricCard label={t("travelling")} value={data.travelling} href="/tasks?status=travelling" />
          <MetricCard label={t("inProgress")} value={data.in_progress} href="/tasks?status=in_progress" />
          <MetricCard label={t("blocked")} value={data.blocked} tone="warning" href="/tasks?status=blocked" />
          <MetricCard
            label={t("completedToday")}
            value={data.completed_today}
            tone="success"
            href="/tasks?view=completed&status=completed"
          />
        </div>
      ) : null}
      {!loading && !error && !data ? <EmptyWorkspace /> : null}
    </div>
  );
}
