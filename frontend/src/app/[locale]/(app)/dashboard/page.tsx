"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { getDashboardSummary } from "@/lib/dashboard";
import type { DashboardSummary } from "@/lib/types";
import { useAuthStore } from "@/store/auth-store";
import { KpiCard } from "@/components/ui/kpi-card";
import { PageSection } from "@/components/ui/page-section";
import { Alert, PageHeader, LoadingState } from "@/components/ui/layout";

export default function DashboardPage() {
  const t = useTranslations("dashboard");
  const tCommon = useTranslations("common");
  const hasAnyPermission = useAuthStore((s) => s.hasAnyPermission);
  const user = useAuthStore((s) => s.user);

  const [loading, setLoading] = useState(true);
  const [summary, setSummary] = useState<DashboardSummary>({});
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      try {
        const response = await getDashboardSummary();
        if (!cancelled) setSummary(response.data);
      } catch {
        if (!cancelled) {
          setSummary({});
          setError(t("summaryUnavailable"));
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, [t]);

  const value = (input: string | number | undefined) => input ?? t("unavailable");
  const isCollector = Boolean(user?.roles.includes("Collector"));
  const isAdmin = hasAnyPermission(["users.manage", "settings.manage", "audit.view"]);
  const collectorCards = [
    { label: t("activeAssignments"), value: value(summary.assignments?.active), href: "/collector/assignments" },
    { label: t("visitsToday"), value: value(summary.visits?.today), href: "/collector/visits" },
    { label: t("openPromises"), value: value(summary.promises?.open), href: "/promises" },
    { label: t("paymentsToday"), value: value(summary.payments?.confirmed), href: "/collector/payments" },
  ];
  const managerCards = [
    { label: t("outstandingDebt"), value: value(summary.invoices?.outstanding_amount), href: "/debtors", tone: "warning" as const },
    { label: t("overdueCustomers"), value: value(summary.invoices?.overdue), href: "/debtors?status=overdue", tone: "danger" as const },
    { label: t("paymentsToday"), value: value(summary.payments?.confirmed), href: "/payments", tone: "success" as const },
    { label: t("pendingHandovers"), value: value(summary.cash_custody?.pending_handovers), href: "/handovers", tone: "warning" as const },
    { label: t("syncFailures"), value: value(summary.payments?.sync_failed), href: "/payments/sync-failures", tone: "danger" as const },
    { label: t("unmappedCustomers"), value: value(summary.customers?.unmapped), href: "/zoho/unmapped", tone: "warning" as const },
  ];
  const adminCards = [
    { label: t("customers"), value: value(summary.customers?.total), href: "/customers" },
    { label: t("invoices"), value: value(summary.invoices?.total), href: "/invoices" },
  ];

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />
      {error ? <div className="mb-4"><Alert tone="warning">{error}</Alert></div> : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : (
        <div className="space-y-7">
          <PageSection title={isCollector ? t("myWork") : t("operations")} description={isCollector ? t("collectorDescription") : t("managerDescription")}>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {(isCollector ? collectorCards : managerCards).map((card) => <KpiCard key={card.label} {...card} />)}
            </div>
          </PageSection>
          {isAdmin && !isCollector ? <PageSection title={t("administration")}>
            <div className="grid gap-4 sm:grid-cols-2">{adminCards.map((card) => <KpiCard key={card.label} {...card} />)}</div>
          </PageSection> : null}
        </div>
      )}
    </div>
  );
}
