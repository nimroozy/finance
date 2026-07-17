"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { getFinanceDashboard, type FinanceDashboard } from "@/lib/operations";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  MetricCard,
  WorkspaceHeader,
} from "@/components/ops";
import { LoadingState } from "@/components/ui/layout";

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
      <WorkspaceHeader title={t("financeTitle")} subtitle={t("financeSubtitle")} />
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {!loading && !error && data ? (
        <div className="grid gap-4 sm:grid-cols-3">
          <MetricCard
            label={t("financeReview")}
            value={data.finance_review}
            href="/installations?view=finance_review&status=finance_review"
          />
          <MetricCard
            label={t("equipmentWaiting")}
            value={data.equipment_waiting}
            href="/installations?view=equipment_waiting&status=equipment_waiting"
          />
          <MetricCard
            label={t("financeTickets")}
            value={data.finance_related_tickets}
            href="/tickets?status=waiting_finance"
          />
        </div>
      ) : null}
      {!loading && !error && !data ? <EmptyWorkspace /> : null}
    </div>
  );
}
