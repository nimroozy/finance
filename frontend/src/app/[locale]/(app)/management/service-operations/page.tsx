"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import {
  getManagerDashboard,
  type ManagerDashboard,
} from "@/lib/operations";
import {
  Alert,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

function Kpi({ label, value }: { label: string; value: number | string }) {
  return (
    <Panel className="p-4">
      <p className="text-sm text-muted">{label}</p>
      <p className="mt-2 text-3xl font-semibold text-primary">{value}</p>
    </Panel>
  );
}

export default function ManagementOpsPage() {
  const t = useTranslations("operationsDashboards");
  const tCommon = useTranslations("common");
  const [data, setData] = useState<ManagerDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getManagerDashboard();
      setData(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <PageHeader title={t("managementTitle")} subtitle={t("managementSubtitle")} />
      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      {loading || !data ? (
        <LoadingState label={tCommon("loading")} />
      ) : (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Kpi label={t("openTickets")} value={data.support.open} />
            <Kpi label={t("breached")} value={data.support.breached} />
            <Kpi label={t("taskBacklog")} value={data.task_backlog} />
            <Kpi label={t("installationsInFlight")} value={data.installations_in_flight} />
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Kpi label={t("openField")} value={data.technical.open_field} />
            <Kpi label={t("blocked")} value={data.technical.blocked} />
            <Kpi label={t("openEscalations")} value={data.noc.open_escalations} />
            <Kpi label={t("financeReview")} value={data.finance.finance_review} />
          </div>
        </div>
      )}
    </div>
  );
}
