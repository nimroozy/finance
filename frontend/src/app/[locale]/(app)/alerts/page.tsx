"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { getDashboardSummary } from "@/lib/dashboard";
import { getZohoHealth } from "@/lib/zoho";
import type { DashboardSummary, ZohoHealth } from "@/lib/types";
import { KpiCard } from "@/components/ui/kpi-card";
import { Alert, LoadingState, PageHeader } from "@/components/ui/layout";

export default function AlertsPage() {
  const t = useTranslations("alerts");
  const tCommon = useTranslations("common");
  const [summary, setSummary] = useState<DashboardSummary>({});
  const [health, setHealth] = useState<ZohoHealth | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void Promise.allSettled([getDashboardSummary(), getZohoHealth()]).then(([dashboard, zoho]) => {
      if (dashboard.status === "fulfilled") setSummary(dashboard.value.data);
      if (zoho.status === "fulfilled") setHealth(zoho.value.data);
      setLoading(false);
    });
  }, []);

  if (loading) return <LoadingState label={tCommon("loading")} />;
  const disconnected = !health?.connection || health.connection.status === "disconnected";
  const openCircuits = health ? Object.values(health.circuit_breakers).filter((item) => item.state !== "closed").length : 0;

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />
      {disconnected ? <div className="mb-4"><Alert>{t("disconnected")}</Alert></div> : null}
      {!disconnected && !health?.failed_jobs && !openCircuits && !summary.customers?.unmapped ? <div className="mb-4"><Alert tone="success">{t("allClear")}</Alert></div> : null}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <KpiCard label={t("failedJobs")} value={health?.failed_jobs ?? "—"} tone={health?.failed_jobs ? "danger" : "success"} href="/zoho/sync-health" />
        <KpiCard label={t("openCircuits")} value={openCircuits} tone={openCircuits ? "warning" : "success"} href="/zoho/sync-health" />
        <KpiCard label={t("unmapped")} value={summary.customers?.unmapped ?? "—"} tone={summary.customers?.unmapped ? "warning" : "neutral"} href="/zoho/unmapped" />
        <KpiCard label={t("paymentFailures")} value={summary.payments?.sync_failed ?? "—"} tone={summary.payments?.sync_failed ? "danger" : "neutral"} href="/payments/sync-failures" />
      </div>
    </div>
  );
}
