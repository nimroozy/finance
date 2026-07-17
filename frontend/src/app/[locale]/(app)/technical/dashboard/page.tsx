"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import {
  getTechnicalDashboard,
  type TechnicalDashboard,
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
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <PageHeader title={t("technicalTitle")} subtitle={t("technicalSubtitle")} />
      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      {loading || !data ? (
        <LoadingState label={tCommon("loading")} />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <Kpi label={t("openField")} value={data.open_field} />
          <Kpi label={t("travelling")} value={data.travelling} />
          <Kpi label={t("inProgress")} value={data.in_progress} />
          <Kpi label={t("blocked")} value={data.blocked} />
          <Kpi label={t("completedToday")} value={data.completed_today} />
        </div>
      )}
    </div>
  );
}
