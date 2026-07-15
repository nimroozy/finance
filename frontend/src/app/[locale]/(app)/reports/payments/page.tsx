"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listBranches } from "@/lib/auth";
import {
  reportPaymentsSummary,
  reportPaymentsSyncFailures,
} from "@/lib/payments";
import type { Branch, Payment, PaymentsSummary } from "@/lib/types";
import { formatMoney } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Input, Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function PaymentReportsPage() {
  const t = useTranslations("paymentReports");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [branches, setBranches] = useState<Branch[]>([]);
  const [branchId, setBranchId] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [summary, setSummary] = useState<PaymentsSummary | null>(null);
  const [failures, setFailures] = useState<Payment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void listBranches(1)
      .then((res) => setBranches(res.data))
      .catch(() => setBranches([]));
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [s, f] = await Promise.all([
        reportPaymentsSummary({
          branch_id: branchId || undefined,
          from: from || undefined,
          to: to || undefined,
        }),
        reportPaymentsSyncFailures(branchId || undefined),
      ]);
      setSummary(s.data);
      setFailures(f.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [branchId, from, to, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-4">
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {error ? <Alert>{error}</Alert> : null}

      <Panel className="grid gap-3 p-4 sm:grid-cols-4">
        <Select value={branchId} onChange={(e) => setBranchId(e.target.value)}>
          <option value="">{tCommon("all")}</option>
          {branches.map((b) => (
            <option key={b.id} value={b.id}>
              {locale === "fa" ? b.name_fa || b.name_en : b.name_en}
            </option>
          ))}
        </Select>
        <Input
          type="date"
          value={from}
          onChange={(e) => setFrom(e.target.value)}
          aria-label={t("from")}
        />
        <Input
          type="date"
          value={to}
          onChange={(e) => setTo(e.target.value)}
          aria-label={t("to")}
        />
        <Button variant="secondary" onClick={() => void load()}>
          {tCommon("apply")}
        </Button>
      </Panel>

      {loading ? (
        <LoadingState label={tCommon("loading")} />
      ) : (
        <>
          <Panel className="p-4">
            <p className="mb-3 font-medium">{t("summary")}</p>
            {summary ? (
              <div className="space-y-3">
                <div className="grid grid-cols-2 gap-3 sm:max-w-md">
                  <div>
                    <p className="text-xs text-muted">{t("totalCount")}</p>
                    <p className="text-xl font-semibold">{summary.total_count}</p>
                  </div>
                  <div>
                    <p className="text-xs text-muted">{t("totalAmount")}</p>
                    <p className="text-xl font-semibold">
                      {formatMoney(summary.total_amount, null, locale)}
                    </p>
                  </div>
                </div>
                {summary.groups.length === 0 ? (
                  <EmptyState label={tCommon("empty")} />
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full min-w-[600px] text-sm">
                      <thead className="border-b border-border bg-sand-soft/40 text-muted">
                        <tr>
                          <th className="px-3 py-2 text-start font-medium">
                            {t("status")}
                          </th>
                          <th className="px-3 py-2 text-start font-medium">
                            {t("syncStatus")}
                          </th>
                          <th className="px-3 py-2 text-start font-medium">
                            {t("count")}
                          </th>
                          <th className="px-3 py-2 text-start font-medium">
                            {t("amount")}
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        {summary.groups.map((g) => (
                          <tr
                            key={`${g.status}-${g.zoho_sync_status}`}
                            className="border-b border-border last:border-0"
                          >
                            <td className="px-3 py-2">
                              <StatusBadge status={g.status} />
                            </td>
                            <td className="px-3 py-2">
                              <StatusBadge status={g.zoho_sync_status} />
                            </td>
                            <td className="px-3 py-2">{g.cnt}</td>
                            <td className="px-3 py-2">
                              {formatMoney(g.total_amount, null, locale)}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            ) : null}
          </Panel>

          <Panel>
            <div className="border-b border-border px-4 py-3 font-medium">
              {t("syncFailures")}
            </div>
            {failures.length === 0 ? (
              <EmptyState label={tCommon("empty")} />
            ) : (
              <ul className="divide-y divide-border">
                {failures.slice(0, 20).map((row) => (
                  <li
                    key={row.uuid}
                    className="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                  >
                    <div>
                      <Link
                        href={`/payments/${row.uuid}`}
                        className="font-medium text-primary hover:underline"
                      >
                        {row.customer?.contact_name || `#${row.customer_id}`}
                      </Link>
                      <p className="text-xs text-muted">
                        {formatMoney(row.amount, row.currency, locale)}
                      </p>
                    </div>
                    <StatusBadge status={row.zoho_sync_status} />
                  </li>
                ))}
              </ul>
            )}
          </Panel>
        </>
      )}
    </div>
  );
}
