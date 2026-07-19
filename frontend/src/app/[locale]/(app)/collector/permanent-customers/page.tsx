"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { PageHeader } from "@/components/ui/layout";
import { ResponsiveRecordList, MobileRecordCard } from "@/components/ui/record-list";
import { Badge } from "@/components/ui/badge";
import { ErrorState } from "@/components/ui/error-state";
import { formatDate } from "@/lib/utils";
import { collectorPermanentCustomers, type CustomerOwnership } from "@/lib/ownership";

export default function PermanentCustomersPage() {
  const t = useTranslations("collectorPermanentCustomersPage");
  const locale = useLocale();
  const [rows, setRows] = useState<CustomerOwnership[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);

  async function load() {
    setLoading(true);
    setError(null);
    try {
      const res = await collectorPermanentCustomers();
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
              subtitle={<Badge tone="info">{t("permanent")}</Badge>}
              fields={[{ label: t("sinceLabel"), value: formatDate(row.start_date, locale) }]}
            />
          )}
        />
      )}
    </div>
  );
}
