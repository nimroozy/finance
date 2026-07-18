"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { PageHeader } from "@/components/ui/layout";
import { ResponsiveRecordList, MobileRecordCard } from "@/components/ui/record-list";
import { Badge } from "@/components/ui/badge";
import { ErrorState } from "@/components/ui/error-state";
import { formatMoney } from "@/lib/utils";
import { collectorDebtors, type CustomerWorkQueueEntry } from "@/lib/ownership";

export default function CollectorDebtorsPage() {
  const t = useTranslations("collectorDebtorsPage");
  const locale = useLocale();
  const [rows, setRows] = useState<CustomerWorkQueueEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);

  async function load() {
    setLoading(true);
    setError(null);
    try {
      const res = await collectorDebtors();
      setRows(res.data);
    } catch (err) {
      setError(err);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />
      {error ? (
        <ErrorState error={error} onRetry={load} />
      ) : (
        <ResponsiveRecordList
          rows={rows}
          rowKey={(row) => row.id}
          loading={loading}
          emptyTitle={t("empty")}
          renderCard={(row) => (
            <MobileRecordCard
              title={row.customer?.contact_name || row.customer?.company_name || `#${row.customer_id}`}
              subtitle={<Badge tone="neutral">{row.ownership_source}</Badge>}
              fields={[
                { label: t("balance"), value: formatMoney(row.total_open_balance, undefined, locale) },
                { label: t("openInvoices"), value: row.open_invoice_count },
                { label: t("overdue"), value: t("overdueDays", { days: row.days_overdue ?? 0 }) },
              ]}
            />
          )}
        />
      )}
    </div>
  );
}
