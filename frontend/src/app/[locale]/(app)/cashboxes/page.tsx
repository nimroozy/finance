"use client";

import { useEffect, useState } from "react";
import { useLocale } from "next-intl";
import { ApiError } from "@/lib/api";
import { listCashboxes, type BranchCashbox } from "@/lib/handovers";
import { formatMoney } from "@/lib/utils";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CashboxesPage() {
  const locale = useLocale();
  const [rows, setRows] = useState<BranchCashbox[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void listCashboxes()
      .then((res) => setRows(res.data ?? []))
      .catch((err) => setError(err instanceof ApiError ? err.message : "Error"))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <LoadingState label={locale === "fa" ? "در حال بارگذاری…" : "Loading…"} />;

  return (
    <div className="space-y-4">
      <PageHeader
        title={locale === "fa" ? "صندوق‌های نقدی شعبه" : "Branch cashboxes"}
        subtitle={
          locale === "fa"
            ? "موجودی صندوق پس از تأیید تحویل تحصیلدار"
            : "Cashbox balances after approved collector handovers"
        }
      />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      <Panel>
        {rows.length === 0 ? (
          <EmptyState label={locale === "fa" ? "صندوقی نیست" : "No cashboxes"} />
        ) : (
          <ul className="divide-y">
            {rows.map((row) => (
              <li key={row.id} className="flex items-center justify-between py-3">
                <div>
                  <div className="font-medium">{row.name}</div>
                  <div className="text-sm text-muted-foreground">
                    Branch #{row.branch_id} · {row.currency}
                  </div>
                </div>
                <strong>{formatMoney(row.balance, row.currency, locale)}</strong>
              </li>
            ))}
          </ul>
        )}
      </Panel>
    </div>
  );
}
