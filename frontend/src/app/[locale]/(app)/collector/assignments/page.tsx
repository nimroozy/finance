"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { acceptAssignment, listAssignments } from "@/lib/assignments";
import { mapsExternalUrl, telUrl, waMeUrl } from "@/lib/geolocation";
import type { CustomerAssignment } from "@/lib/types";
import { formatDate, formatMoney } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CollectorAssignmentsPage() {
  const t = useTranslations("collectorAssignments");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [rows, setRows] = useState<CustomerAssignment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

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
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onAccept(id: number) {
    try {
      await acceptAssignment(id);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    }
  }

  return (
    <div className="space-y-4">
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {error ? <Alert>{error}</Alert> : null}

      {loading ? (
        <LoadingState label={tCommon("loading")} />
      ) : rows.length === 0 ? (
        <EmptyState label={tCommon("empty")} />
      ) : (
        <div className="space-y-3">
          {rows.map((row) => {
            const phone =
              row.customer?.mobile ||
              row.customer?.phone ||
              row.customer?.whatsapp_number;
            const whatsapp =
              row.customer?.whatsapp_number ||
              row.customer?.mobile ||
              row.customer?.phone;
            const lat = Number(row.customer?.latitude);
            const lng = Number(row.customer?.longitude);
            const hasLoc = Number.isFinite(lat) && Number.isFinite(lng);

            return (
              <Panel key={row.id} className="p-4">
                <Link href={`/collector/assignments/${row.id}`}>
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <p className="text-lg font-semibold text-primary">
                        {row.customer?.contact_name ||
                          `#${row.customer_id}`}
                      </p>
                      <p className="text-sm text-muted">
                        {row.customer?.company_name || "—"}
                      </p>
                    </div>
                    <StatusBadge status={row.status} />
                  </div>
                </Link>
                <div className="mt-3 space-y-1 text-sm">
                  <p>
                    {t("outstanding")}:{" "}
                    {formatMoney(
                      row.debt_snapshot_outstanding ??
                        row.customer?.outstanding_receivable,
                      row.debt_snapshot_currency ?? row.customer?.currency,
                      locale,
                    )}
                  </p>
                  <p>
                    {t("due")}: {formatDate(row.due_date, locale)}
                  </p>
                  <p>
                    {t("priority")}: {row.priority}
                  </p>
                </div>

                <div className="mt-4 grid grid-cols-2 gap-2">
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
                    <a
                      href={waMeUrl(whatsapp)}
                      target="_blank"
                      rel="noopener noreferrer"
                    >
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
                    <a
                      href={mapsExternalUrl(lat, lng)}
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <Button className="h-11 w-full" variant="secondary">
                        {t("map")}
                      </Button>
                    </a>
                  ) : (
                    <Button className="h-11 w-full" variant="secondary" disabled>
                      {t("map")}
                    </Button>
                  )}
                  <Link
                    href={`/collector/visits/new?assignment_id=${row.id}&customer_id=${row.customer_id}`}
                  >
                    <Button className="h-11 w-full">{t("startVisit")}</Button>
                  </Link>
                </div>

                {row.status === "assigned" ? (
                  <Button
                    className="mt-3 h-11 w-full"
                    variant="ghost"
                    onClick={() => void onAccept(row.id)}
                  >
                    {t("accept")}
                  </Button>
                ) : null}
              </Panel>
            );
          })}
        </div>
      )}
    </div>
  );
}
