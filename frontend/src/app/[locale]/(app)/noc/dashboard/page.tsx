"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { getNocDashboard, type NocDashboard } from "@/lib/operations";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  MetricCard,
  WorkspaceHeader,
} from "@/components/ops";
import { LoadingState } from "@/components/ui/layout";

export default function NocDashboardPage() {
  const t = useTranslations("operationsDashboards");
  const tCommon = useTranslations("common");
  const [data, setData] = useState<NocDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getNocDashboard();
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
      <WorkspaceHeader title={t("nocTitle")} subtitle={t("nocSubtitle")} />
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {!loading && !error && data ? (
        <div className="grid gap-4 sm:grid-cols-3">
          <MetricCard label={t("openEscalations")} value={data.open_escalations} href="/tickets?status=escalated" />
          <MetricCard label={t("escalatedTickets")} value={data.escalated_tickets} href="/tickets?status=escalated" />
          <MetricCard
            label={t("slaBreached")}
            value={data.sla_breached}
            tone="danger"
            href="/tickets?view=sla_breached&sla_state=breached"
          />
        </div>
      ) : null}
      {!loading && !error && !data ? <EmptyWorkspace /> : null}
    </div>
  );
}
