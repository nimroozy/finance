"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { getSupportDashboard, type SupportDashboard } from "@/lib/operations";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  MetricCard,
  WorkspaceHeader,
} from "@/components/ops";
import { LoadingState } from "@/components/ui/layout";

export default function SupportDashboardPage() {
  const t = useTranslations("operationsDashboards");
  const tCommon = useTranslations("common");
  const [data, setData] = useState<SupportDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getSupportDashboard();
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
      <WorkspaceHeader title={t("supportTitle")} subtitle={t("supportSubtitle")} />
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {!loading && !error && data ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard label={t("openTickets")} value={data.open} href="/tickets?view=all" />
          <MetricCard
            label={t("waitingCustomer")}
            value={data.waiting_customer}
            href="/tickets?view=waiting_customer&status=waiting_customer"
          />
          <MetricCard
            label={t("breached")}
            value={data.breached}
            tone="danger"
            href="/tickets?view=sla_breached&sla_state=breached"
          />
          <MetricCard
            label={t("resolvedToday")}
            value={data.resolved_today}
            tone="success"
            href="/tickets?view=resolved_today&status=resolved"
          />
        </div>
      ) : null}
      {!loading && !error && !data ? <EmptyWorkspace /> : null}
    </div>
  );
}
