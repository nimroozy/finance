"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { acceptAssignment, listAssignments } from "@/lib/assignments";
import { mapsExternalUrl, telUrl, waMeUrl } from "@/lib/geolocation";
import type { CustomerAssignment } from "@/lib/types";
import { formatDate, formatMoney } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { PageHeader } from "@/components/ui/layout";
import { ErrorState } from "@/components/ui/error-state";
import { EmptyState } from "@/components/ui/empty-state";
import { CardSkeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api";

export default function CollectorAssignmentsPage() {
  const t = useTranslations("collectorAssignments");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [rows, setRows] = useState<CustomerAssignment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const [acceptError, setAcceptError] = useState<unknown>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listAssignments({
        is_active: true,
        per_page: 50,
      });
      setRows(res.data);
    } catch (err) {
      setError(err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function onAccept(id: number) {
    setAcceptError(null);
    try {
      await acceptAssignment(id);
      await load();
    } catch (err) {
      setAcceptError(err);
    }
  }

  return (
    <div className="space-y-4">
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {acceptError ? (
        <div className="rounded-md border border-danger/30 bg-danger-soft px-3 py-2 text-sm text-danger">
          {acceptError instanceof ApiError ? acceptError.message : tCommon("error")}
        </div>
      ) : null}

      {error ? (
        <ErrorState error={error} onRetry={load} />
      ) : loading ? (
        <CardSkeleton />
      ) : rows.length === 0 ? (
        <EmptyState title={tCommon("empty")} />
      ) : (
        <div className="space-y-3">
          {rows.map((row) => {
            const phone = row.customer?.mobile || row.customer?.phone || row.customer?.whatsapp_number;
            const whatsapp = row.customer?.whatsapp_number || row.customer?.mobile || row.customer?.phone;
            const lat = Number(row.customer?.latitude);
            const lng = Number(row.customer?.longitude);
            const hasLoc = Number.isFinite(lat) && Number.isFinite(lng);
            const address = row.customer?.billing_address || row.customer?.shipping_address;

            return (
              <div key={row.id} className="rounded-lg border border-border bg-surface-elevated p-4">
                <Link href={`/collector/assignments/${row.id}`}>
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="truncate text-lg font-semibold text-primary">
                        {row.customer?.contact_name || `#${row.customer_id}`}
                      </p>
                      <p className="truncate text-sm text-muted">{row.customer?.company_name || "—"}</p>
                      {address ? <p className="truncate text-xs text-muted">{address}</p> : null}
                    </div>
                    <StatusBadge status={row.status} />
                  </div>
                </Link>
                <dl className="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                  <div>
                    <dt className="text-xs text-muted">{t("outstanding")}</dt>
                    <dd>
                      {formatMoney(
                        row.debt_snapshot_outstanding ?? row.customer?.outstanding_receivable,
                        row.debt_snapshot_currency ?? row.customer?.currency,
                        locale,
                      )}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-xs text-muted">{t("branch")}</dt>
                    <dd>{row.branch?.code || "—"}</dd>
                  </div>
                  <div>
                    <dt className="text-xs text-muted">{t("due")}</dt>
                    <dd>{formatDate(row.due_date, locale)}</dd>
                  </div>
                  <div>
                    <dt className="text-xs text-muted">{t("priority")}</dt>
                    <dd className="capitalize">{row.priority}</dd>
                  </div>
                </dl>

                <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                  {phone ? (
                    <a href={telUrl(phone)}>
                      <Button className="h-11 w-full" variant="secondary">
                        {t("call")}
                      </Button>
                    </a>
                  ) : (
                    <Button className="h-11 w-full" variant="secondary" disabled>
                      {t("noPhone")}
                    </Button>
                  )}
                  {whatsapp ? (
                    <a href={waMeUrl(whatsapp)} target="_blank" rel="noopener noreferrer">
                      <Button className="h-11 w-full" variant="secondary">
                        {t("whatsapp")}
                      </Button>
                    </a>
                  ) : (
                    <Button className="h-11 w-full" variant="secondary" disabled>
                      {t("whatsapp")}
                    </Button>
                  )}
                  {hasLoc ? (
                    <a href={mapsExternalUrl(lat, lng)} target="_blank" rel="noopener noreferrer">
                      <Button className="h-11 w-full" variant="secondary">
                        {t("map")}
                      </Button>
                    </a>
                  ) : (
                    <Button className="h-11 w-full" variant="secondary" disabled>
                      {t("noLocation")}
                    </Button>
                  )}
                  <Link href={`/customers/${row.customer_id}`}>
                    <Button className="h-11 w-full" variant="secondary">
                      {t("openCustomer")}
                    </Button>
                  </Link>
                  <Link href={`/collector/promises/new?customer_id=${row.customer_id}&assignment_id=${row.id}`}>
                    <Button className="h-11 w-full" variant="secondary">
                      {t("recordPromise")}
                    </Button>
                  </Link>
                  <Link href={`/collector/payments/new?assignment_id=${row.id}&customer_id=${row.customer_id}`}>
                    <Button className="h-11 w-full">{t("startPayment")}</Button>
                  </Link>
                </div>

                <Link href={`/collector/visits/new?assignment_id=${row.id}&customer_id=${row.customer_id}`}>
                  <Button className="mt-2 h-11 w-full">{t("startVisit")}</Button>
                </Link>

                {row.status === "assigned" ? (
                  <Button className="mt-2 h-11 w-full" variant="ghost" onClick={() => void onAccept(row.id)}>
                    {t("accept")}
                  </Button>
                ) : null}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
