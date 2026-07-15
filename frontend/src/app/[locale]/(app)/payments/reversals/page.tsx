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

export default function PaymentReversalsPage() {
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
        // No dedicated pending-reversals index; show recent confirmed/reversed for review.
        const res = await listPayments({ page, per_page: 25 });
        setRows(
          res.data.filter(
            (p) =>
              p.status !== "draft" &&
              p.status !== "expired",
          ),
        );
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
    <div>
      <PageHeader
        title={t("reversalsTitle")}
        subtitle={t("reversalsSubtitle")}
      />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : rows.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[800px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("customer")}</th>
                  <th className="px-4 py-3 font-medium">{t("amount")}</th>
                  <th className="px-4 py-3 font-medium">{t("status")}</th>
                  <th className="px-4 py-3 font-medium">{t("confirmedAt")}</th>
                  <th className="px-4 py-3 font-medium">{tCommon("actions")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.uuid} className="border-b border-border last:border-0">
                    <td className="px-4 py-3">
                      {row.customer?.contact_name || `#${row.customer_id}`}
                    </td>
                    <td className="px-4 py-3">
                      {formatMoney(row.amount, row.currency, locale)}
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge status={row.status} />
                    </td>
                    <td className="px-4 py-3">
                      {formatDateTime(row.confirmed_at, locale)}
                    </td>
                    <td className="px-4 py-3">
                      <Link href={`/payments/${row.uuid}`}>
                        <Button size="sm" variant="secondary">
                          {t("openPayment")}
                        </Button>
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      {meta.last_page > 1 ? (
        <div className="mt-4 flex items-center justify-between gap-3">
          <Button
            variant="secondary"
            disabled={meta.current_page <= 1 || loading}
            onClick={() => void load(meta.current_page - 1)}
          >
            {tCommon("previous")}
          </Button>
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
