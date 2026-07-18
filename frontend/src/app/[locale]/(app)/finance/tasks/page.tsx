"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import {
  getFinanceDashboard,
  type FinanceDashboard,
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

export default function FinanceTasksPage() {
  const t = useTranslations("operationsDashboards");
  const tCommon = useTranslations("common");
  const [data, setData] = useState<FinanceDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getFinanceDashboard();
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
      <PageHeader title={t("financeTitle")} subtitle={t("financeSubtitle")} />
      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      {loading || !data ? (
        <LoadingState label={tCommon("loading")} />
      ) : (
        <div className="grid gap-4 sm:grid-cols-3">
          <Kpi label={t("financeReview")} value={data.finance_review} />
          <Kpi label={t("equipmentWaiting")} value={data.equipment_waiting} />
          <Kpi label={t("financeTickets")} value={data.finance_related_tickets} />
        </div>
      )}
    </div>
  );
}
