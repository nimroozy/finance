"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { getCollectorDashboard } from "@/lib/collectors";
import type { CollectorDashboard } from "@/lib/types";
import { formatDate } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import {
  Alert,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CollectorDashboardPage() {
  const t = useTranslations("collectorDash");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [data, setData] = useState<CollectorDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getCollectorDashboard();
      setData(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("notCollector"));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) return <LoadingState label={tCommon("loading")} />;

  return (
    <div className="space-y-4">
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {error ? <Alert>{error}</Alert> : null}

      {data ? (
        <>
          <div className="grid grid-cols-2 gap-3">
            {[
              {
                label: t("activeAssignments"),
                value: data.active_assignments,
              },
              { label: t("visitsToday"), value: data.visits_today },
              { label: t("openPromises"), value: data.open_promises },
              {
                label: t("routesToday"),
                value: data.routes_today?.length ?? 0,
              },
            ].map((card) => (
              <Panel key={card.label} className="p-4">
                <p className="text-xs text-muted">{card.label}</p>
                <p className="mt-1 text-2xl font-semibold text-primary">
                  {card.value}
                </p>
              </Panel>
            ))}
          </div>

          <div className="grid gap-3">
            <Link href="/collector/assignments">
              <Button className="h-12 w-full text-base">
                {t("goAssignments")}
              </Button>
            </Link>
            <Link href="/collector/routes">
              <Button className="h-12 w-full text-base" variant="secondary">
                {t("goRoutes")}
              </Button>
            </Link>
            <div className="grid grid-cols-2 gap-3">
              <Link href="/collector/visits/new">
                <Button className="h-12 w-full" variant="secondary">
                  {t("newVisit")}
                </Button>
              </Link>
              <Link href="/collector/payments/new">
                <Button className="h-12 w-full" variant="secondary">
                  {t("newPayment")}
                </Button>
              </Link>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <Link href="/collector/promises/new">
                <Button className="h-12 w-full" variant="secondary">
                  {t("newPromise")}
                </Button>
              </Link>
              <Link href="/collector/wallet">
                <Button className="h-12 w-full" variant="secondary">
                  {t("wallet")}
                </Button>
              </Link>
            </div>
            <Link href="/collector/payments">
              <Button className="h-12 w-full" variant="ghost">
                {t("goPayments")}
              </Button>
            </Link>
            <Link href="/collector/visits">
              <Button className="h-12 w-full" variant="ghost">
                {t("goVisits")}
              </Button>
            </Link>
            <Link href="/collector/notifications">
              <Button className="h-12 w-full" variant="ghost">
                {t("notifications")}
              </Button>
            </Link>
          </div>

          {data.routes_today?.length ? (
            <Panel>
              <div className="border-b border-border px-4 py-3 text-sm font-medium">
                {t("routesToday")}
              </div>
              <ul className="divide-y divide-border">
                {data.routes_today.map((route) => (
                  <li key={route.id}>
                    <Link
                      href={`/collector/routes/${route.id}`}
                      className="flex items-center justify-between px-4 py-4 hover:bg-sand-soft/40"
                    >
                      <div>
                        <p className="font-medium">{route.name}</p>
                        <p className="text-xs text-muted">
                          {formatDate(route.route_date, locale)}
                        </p>
                      </div>
                      <StatusBadge status={route.status} />
                    </Link>
                  </li>
                ))}
              </ul>
            </Panel>
          ) : null}
        </>
      ) : null}
    </div>
  );
}
