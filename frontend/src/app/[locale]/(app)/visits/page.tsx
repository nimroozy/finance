"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { listVisitOutcomes, listVisits } from "@/lib/visits";
import type { CollectionVisit, VisitOutcome } from "@/lib/types";
import { formatDateTime } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Select } from "@/components/ui/form";
import { PageHeader } from "@/components/ui/layout";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ErrorState } from "@/components/ui/error-state";
import { BranchPicker, CollectorPicker, CustomerPicker } from "@/components/ui/pickers";
import type { SearchableOption } from "@/components/ui/searchable-select";

export default function VisitsPage() {
  const t = useTranslations("visitsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [rows, setRows] = useState<CollectionVisit[]>([]);
  const [outcomes, setOutcomes] = useState<VisitOutcome[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [outcome, setOutcome] = useState("");
  const [customer, setCustomer] = useState<SearchableOption | null>(null);
  const [collector, setCollector] = useState<SearchableOption | null>(null);
  const [branch, setBranch] = useState<SearchableOption | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listVisits({
          page,
          outcome: outcome || undefined,
          customer_id: customer ? String(customer.id) : undefined,
          collector_id: collector ? String(collector.id) : undefined,
          branch_id: branch ? String(branch.id) : undefined,
        });
        setRows(res.data);
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
    [outcome, customer, collector, branch],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  useEffect(() => {
    void listVisitOutcomes()
      .then((res) => setOutcomes(res.data))
      .catch(() => setOutcomes([]));
  }, []);

  function outcomeLabel(code: string) {
    const found = outcomes.find((o) => o.code === code);
    if (!found) return code;
    return locale === "fa" ? found.label_fa : found.label_en;
  }

  const columns: DataTableColumn<CollectionVisit>[] = [
    {
      key: "customer",
      label: t("customer"),
      render: (row) => (
        <Link href={`/visits/${row.id}`} className="font-medium text-primary hover:underline">
          {row.customer?.contact_name || `#${row.customer_id}`}
        </Link>
      ),
    },
    { key: "collector", label: t("collector"), render: (row) => row.collector?.user?.name || "—" },
    { key: "outcome", label: t("outcome"), render: (row) => outcomeLabel(row.outcome) },
    { key: "visitedAt", label: t("visitedAt"), render: (row) => formatDateTime(row.visited_at, locale) },
    { key: "gps", label: t("gps"), render: (row) => <StatusBadge status={row.gps_risk_level} /> },
  ];

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {error ? (
        <ErrorState error={error} onRetry={() => load(meta.current_page)} />
      ) : (
        <DataTable
          rows={rows}
          columns={columns}
          rowKey={(row) => row.id}
          loading={loading}
          emptyLabel={tCommon("empty")}
          page={meta.current_page}
          lastPage={meta.last_page}
          onPageChange={(page) => void load(page)}
          filters={
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:flex-wrap">
              <Select value={outcome} onChange={(e) => setOutcome(e.target.value)} className="sm:max-w-xs" aria-label={t("outcome")}>
                <option value="">{tCommon("all")}</option>
                {outcomes.map((o) => (
                  <option key={o.code} value={o.code}>
                    {locale === "fa" ? o.label_fa : o.label_en}
                  </option>
                ))}
              </Select>
              <CustomerPicker value={customer} onChange={setCustomer} className="sm:max-w-xs" />
              <CollectorPicker value={collector} onChange={setCollector} className="sm:max-w-xs" />
              <BranchPicker value={branch} onChange={setBranch} className="sm:max-w-xs" />
            </div>
          }
        />
      )}
    </div>
  );
}
