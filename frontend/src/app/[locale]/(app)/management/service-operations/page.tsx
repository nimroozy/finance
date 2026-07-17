"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import {
  getManagerDashboard,
  getVersion,
  type ManagerDashboard,
  type SystemVersionInfo,
} from "@/lib/operations";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  MetricCard,
  RecordSummary,
  WorkspaceHeader,
} from "@/components/ops";
import { LoadingState, Panel } from "@/components/ui/layout";

export default function ManagementOpsPage() {
  const t = useTranslations("operationsDashboards");
  const tCommon = useTranslations("common");
  const tVersion = useTranslations("systemVersion");
  const [data, setData] = useState<ManagerDashboard | null>(null);
  const [version, setVersion] = useState<SystemVersionInfo | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [dash, ver] = await Promise.all([
        getManagerDashboard(),
        getVersion().catch(() => null),
      ]);
      setData(dash.data);
      setVersion(ver?.data ?? null);
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
      <WorkspaceHeader title={t("managementTitle")} subtitle={t("managementSubtitle")} />
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {!loading && !error && data ? (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <MetricCard label={t("openTickets")} value={data.support.open} href="/tickets" />
            <MetricCard
              label={t("breached")}
              value={data.support.breached}
              tone="danger"
              href="/tickets?view=sla_breached&sla_state=breached"
            />
            <MetricCard label={t("taskBacklog")} value={data.task_backlog} href="/tasks" />
            <MetricCard
              label={t("installationsInFlight")}
              value={data.installations_in_flight}
              href="/installations"
            />
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <MetricCard label={t("openField")} value={data.technical.open_field} href="/tasks?view=field&type=field" />
            <MetricCard label={t("blocked")} value={data.technical.blocked} href="/tasks?status=blocked" />
            <MetricCard label={t("openEscalations")} value={data.noc.open_escalations} href="/tickets?status=escalated" />
            <MetricCard
              label={t("financeReview")}
              value={data.finance.finance_review}
              href="/installations?view=finance_review&status=finance_review"
            />
          </div>
          {version ? (
            <Panel className="p-4">
              <h2 className="mb-3 font-medium">{tVersion("title")}</h2>
              <RecordSummary
                items={[
                  { label: tVersion("appName"), value: version.app_name || "—" },
                  { label: tVersion("backendVersion"), value: version.backend_version || "—" },
                  { label: tVersion("commitSha"), value: version.commit_sha || "—" },
                  { label: tVersion("buildTimestamp"), value: version.build_timestamp || "—" },
                  { label: tVersion("migrationBatch"), value: version.migration_batch ?? "—" },
                  { label: tVersion("latestMigration"), value: version.latest_migration || "—" },
                ]}
              />
            </Panel>
          ) : null}
        </div>
      ) : null}
      {!loading && !error && !data ? <EmptyWorkspace /> : null}
    </div>
  );
}
