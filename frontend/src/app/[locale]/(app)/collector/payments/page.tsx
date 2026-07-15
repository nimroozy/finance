"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listPayments } from "@/lib/payments";
import type { Payment } from "@/lib/types";
import { formatDateTime, formatMoney } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CollectorPaymentsPage() {
  const t = useTranslations("paymentsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [rows, setRows] = useState<Payment[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listPayments({ page, per_page: 20 });
        setRows(res.data);
        setMeta({
          current_page: res.meta?.current_page ?? 1,
          last_page: res.meta?.last_page ?? 1,
        });
      } catch (err) {
        setError(err instanceof ApiError ? err.message : tCommon("error"));
      } finally {
        setLoading(false);
      }
    },
    [tCommon],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  return (
    <div className="space-y-4">
      <PageHeader
        title={t("historyTitle")}
        subtitle={t("historySubtitle")}
        actions={
          <Link href="/collector/payments/new">
            <Button className="h-11">{t("newPayment")}</Button>
          </Link>
        }
      />

      {error ? <Alert>{error}</Alert> : null}

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : rows.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <ul className="divide-y divide-border">
            {rows.map((row) => (
              <li key={row.uuid}>
                <Link
                  href={`/collector/payments/${row.uuid}`}
                  className="flex items-center justify-between gap-3 px-4 py-4 hover:bg-sand-soft/40"
                >
                  <div className="min-w-0">
                    <p className="truncate font-medium">
                      {row.customer?.contact_name || `#${row.customer_id}`}
                    </p>
                    <p className="text-sm text-muted">
                      {formatMoney(row.amount, row.currency, locale)} ·{" "}
                      {row.method?.name_en || "—"}
                    </p>
                    <p className="text-xs text-muted">
                      {formatDateTime(row.confirmed_at || row.created_at, locale)}
                    </p>
                  </div>
                  <StatusBadge status={row.status} />
                </Link>
              </li>
            ))}
          </ul>
        )}
      </Panel>

      {meta.last_page > 1 ? (
        <div className="flex items-center justify-between gap-3">
          <Button
            variant="secondary"
            disabled={meta.current_page <= 1 || loading}
            onClick={() => void load(meta.current_page - 1)}
          >
            {tCommon("previous")}
          </Button>
          <p className="text-sm text-muted">
            {tCommon("page", {
              page: meta.current_page,
              total: meta.last_page,
            })}
          </p>
          <Button
            variant="secondary"
            disabled={meta.current_page >= meta.last_page || loading}
            onClick={() => void load(meta.current_page + 1)}
          >
            {tCommon("next")}
          </Button>
        </div>
      ) : null}
    </div>
  );
}
