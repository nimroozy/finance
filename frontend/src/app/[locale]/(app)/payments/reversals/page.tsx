"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { listPayments } from "@/lib/payments";
import type { Payment } from "@/lib/types";
import { formatDateTime, formatMoney } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { PageHeader } from "@/components/ui/layout";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ErrorState } from "@/components/ui/error-state";

export default function PaymentReversalsPage() {
  const t = useTranslations("paymentsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [rows, setRows] = useState<Payment[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        // No dedicated pending-reversals index; show recent confirmed/reversed for review.
        const res = await listPayments({ page, per_page: 25 });
        setRows(res.data.filter((p) => p.status !== "draft" && p.status !== "expired"));
        setMeta({
          current_page: res.meta?.current_page ?? 1,
          last_page: res.meta?.last_page ?? 1,
        });
      } catch (err) {
        setError(err);
      } finally {
        setLoading(false);
      }
    },
    [],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  const columns: DataTableColumn<Payment>[] = [
    {
      key: "customer",
      label: t("customer"),
      render: (row) => row.customer?.contact_name || `#${row.customer_id}`,
    },
    { key: "amount", label: t("amount"), render: (row) => formatMoney(row.amount, row.currency, locale) },
    { key: "status", label: t("status"), render: (row) => <StatusBadge status={row.status} /> },
    { key: "confirmedAt", label: t("confirmedAt"), render: (row) => formatDateTime(row.confirmed_at, locale) },
    {
      key: "actions",
      label: tCommon("actions"),
      render: (row) => (
        <Link href={`/payments/${row.uuid}`}>
          <Button size="sm" variant="secondary">
            {t("openPayment")}
          </Button>
        </Link>
      ),
    },
  ];

  return (
    <div>
      <PageHeader title={t("reversalsTitle")} subtitle={t("reversalsSubtitle")} />

      {error ? (
        <ErrorState error={error} onRetry={() => load(meta.current_page)} />
      ) : (
        <DataTable
          rows={rows}
          columns={columns}
          rowKey={(row) => row.uuid}
          loading={loading}
          emptyLabel={tCommon("empty")}
          page={meta.current_page}
          lastPage={meta.last_page}
          onPageChange={(page) => void load(page)}
        />
      )}
    </div>
  );
}
