"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { getNocDashboard, type NocDashboard } from "@/lib/operations";
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
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <PageHeader title={t("nocTitle")} subtitle={t("nocSubtitle")} />
      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      {loading || !data ? (
        <LoadingState label={tCommon("loading")} />
      ) : (
        <div className="grid gap-4 sm:grid-cols-3">
          <Kpi label={t("openEscalations")} value={data.open_escalations} />
          <Kpi label={t("escalatedTickets")} value={data.escalated_tickets} />
          <Kpi label={t("slaBreached")} value={data.sla_breached} />
        </div>
      )}
    </div>
  );
}
