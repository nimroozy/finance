"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { listBranches } from "@/lib/auth";
import { listPayments } from "@/lib/payments";
import type { Branch, Payment } from "@/lib/types";
import { formatDateTime, formatMoney } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/form";
import { CustomerPicker } from "@/components/ui/pickers";
import type { SearchableOption } from "@/components/ui/searchable-select";
import { ErrorState } from "@/components/ui/error-state";
import {
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

const STATUSES = [
  "",
  "draft",
  "confirmed_local",
  "pending_zoho_sync",
  "settled_pending_handover",
  "synced",
  "sync_failed",
  "reversed",
  "expired",
];

export default function PaymentsPage() {
  const t = useTranslations("paymentsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [rows, setRows] = useState<Payment[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [status, setStatus] = useState("");
  const [branchId, setBranchId] = useState("");
  const [customer, setCustomer] = useState<SearchableOption | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);

  useEffect(() => {
    void listBranches(1)
      .then((res) => setBranches(res.data))
      .catch(() => setBranches([]));
  }, []);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listPayments({
          page,
          status: status || undefined,
          branch_id: branchId || undefined,
          customer_id: customer ? String(customer.id) : undefined,
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
    [status, branchId, customer],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {error ? (
        <div className="mb-4">
          <ErrorState error={error} onRetry={() => load(meta.current_page)} />
        </div>
      ) : null}

      <Panel className="mb-4 grid gap-3 p-4 sm:grid-cols-4">
        <Select value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">{tCommon("all")}</option>
          {STATUSES.filter(Boolean).map((s) => (
            <option key={s} value={s}>
              {s.replace(/_/g, " ")}
            </option>
          ))}
        </Select>
        <Select value={branchId} onChange={(e) => setBranchId(e.target.value)}>
          <option value="">{tCommon("branch")}</option>
          {branches.map((b) => (
            <option key={b.id} value={b.id}>
              {locale === "fa" ? b.name_fa || b.name_en : b.name_en}
            </option>
          ))}
        </Select>
        <CustomerPicker value={customer} onChange={setCustomer} branchId={branchId || undefined} />
        <Button variant="secondary" onClick={() => void load(1)}>
          {tCommon("apply")}
        </Button>
      </Panel>

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : rows.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("customer")}</th>
                  <th className="px-4 py-3 font-medium">{t("amount")}</th>
                  <th className="px-4 py-3 font-medium">{t("method")}</th>
                  <th className="px-4 py-3 font-medium">{t("status")}</th>
                  <th className="px-4 py-3 font-medium">{t("syncStatus")}</th>
                  <th className="px-4 py-3 font-medium">{t("confirmedAt")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.uuid} className="border-b border-border last:border-0">
                    <td className="px-4 py-3">
                      <Link
                        href={`/payments/${row.uuid}`}
                        className="font-medium text-primary hover:underline"
                      >
                        {row.customer?.contact_name || `#${row.customer_id}`}
                      </Link>
                      <p className="text-xs text-muted">
                        {row.payment_reference || row.uuid.slice(0, 8)}
                      </p>
                    </td>
                    <td className="px-4 py-3">
                      {formatMoney(row.amount, row.currency, locale)}
                    </td>
                    <td className="px-4 py-3">{row.method?.name_en || "—"}</td>
                    <td className="px-4 py-3">
                      <StatusBadge status={row.status} />
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge status={row.zoho_sync_status} />
                    </td>
                    <td className="px-4 py-3">
                      {formatDateTime(row.confirmed_at || row.created_at, locale)}
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
