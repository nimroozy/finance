"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { PageHeader } from "@/components/ui/layout";
import { KpiCard } from "@/components/ui/kpi-card";
import { ErrorState } from "@/components/ui/error-state";
import { EmptyState } from "@/components/ui/empty-state";
import { LoadingSkeleton } from "@/components/ui/skeleton";
import { formatMoney } from "@/lib/utils";
import { branchReceivables, type BranchReceivableRow } from "@/lib/ownership";

export default function BranchReceivablesPage() {
  const t = useTranslations("branchReceivablesPage");
  const locale = useLocale();
  const [rows, setRows] = useState<BranchReceivableRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);

  async function load() {
    setLoading(true);
    setError(null);
    try {
      const res = await branchReceivables();
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
      ) : loading ? (
        <LoadingSkeleton />
      ) : rows.length === 0 ? (
        <EmptyState title={t("empty")} />
      ) : (
        <div className="space-y-6">
          {rows.map((r) => (
            <div key={r.branch.id} className="rounded-lg border border-border bg-surface-elevated p-4">
              <h2 className="mb-3 text-lg font-semibold text-foreground">{r.branch.name_en || r.branch.code}</h2>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <KpiCard label={t("receivable")} value={formatMoney(r.total_receivable, undefined, locale)} />
                <KpiCard label={t("debtors")} value={r.active_debtor_customers} />
                <KpiCard label={t("owned")} value={r.permanently_owned_customers} />
                <KpiCard label={t("unassigned")} value={r.unassigned_customers} />
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
