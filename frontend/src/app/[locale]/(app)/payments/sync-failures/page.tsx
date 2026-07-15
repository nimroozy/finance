"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listBranches } from "@/lib/auth";
import {
  reportPaymentsSyncFailures,
  retryPaymentSync,
} from "@/lib/payments";
import type { Branch, Payment } from "@/lib/types";
import { formatMoney } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function PaymentSyncFailuresPage() {
  const t = useTranslations("paymentsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canRetry = useAuthStore((s) => s.hasPermission("payments.retry_sync"));

  const [rows, setRows] = useState<Payment[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [branchId, setBranchId] = useState("");
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => {
    void listBranches(1)
      .then((res) => setBranches(res.data))
      .catch(() => setBranches([]));
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await reportPaymentsSyncFailures(branchId || undefined);
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [branchId, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onRetry(uuid: string) {
    setBusyId(uuid);
    setError(null);
    try {
      await retryPaymentSync(uuid);
      setSuccess(t("retrySuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div>
      <PageHeader
        title={t("syncFailuresTitle")}
        subtitle={t("syncFailuresSubtitle")}
      />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      {success ? (
        <div className="mb-4">
          <Alert tone="success">{success}</Alert>
        </div>
      ) : null}

      <Panel className="mb-4 p-4 sm:max-w-xs">
        <Select value={branchId} onChange={(e) => setBranchId(e.target.value)}>
          <option value="">{tCommon("all")}</option>
          {branches.map((b) => (
            <option key={b.id} value={b.id}>
              {locale === "fa" ? b.name_fa || b.name_en : b.name_en}
            </option>
          ))}
        </Select>
      </Panel>

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
                  <th className="px-4 py-3 font-medium">{t("syncStatus")}</th>
                  <th className="px-4 py-3 font-medium">{t("lastError")}</th>
                  <th className="px-4 py-3 font-medium">{tCommon("actions")}</th>
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
                    </td>
                    <td className="px-4 py-3">
                      {formatMoney(row.amount, row.currency, locale)}
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge status={row.zoho_sync_status} />
                    </td>
                    <td className="max-w-xs truncate px-4 py-3 text-xs text-danger">
                      {row.last_sync_error || "—"}
                    </td>
                    <td className="px-4 py-3">
                      {canRetry ? (
                        <Button
                          size="sm"
                          disabled={busyId === row.uuid}
                          onClick={() => void onRetry(row.uuid)}
                        >
                          {t("retrySync")}
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}
