"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { reportPaymentsSyncFailures, retryPaymentSync } from "@/lib/payments";
import type { Payment } from "@/lib/types";
import { formatMoney } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Alert, PageHeader } from "@/components/ui/layout";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ErrorState } from "@/components/ui/error-state";
import { BranchPicker } from "@/components/ui/pickers";
import type { SearchableOption } from "@/components/ui/searchable-select";

export default function PaymentSyncFailuresPage() {
  const t = useTranslations("paymentsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canRetry = useAuthStore((s) => s.hasPermission("payments.retry_sync"));

  const [rows, setRows] = useState<Payment[]>([]);
  const [branch, setBranch] = useState<SearchableOption | null>(null);
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await reportPaymentsSyncFailures(branch ? String(branch.id) : undefined);
      setRows(res.data);
    } catch (err) {
      setError(err);
    } finally {
      setLoading(false);
    }
  }, [branch]);

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
      setError(err);
    } finally {
      setBusyId(null);
    }
  }

  const columns: DataTableColumn<Payment>[] = [
    {
      key: "customer",
      label: t("customer"),
      render: (row) => (
        <Link href={`/payments/${row.uuid}`} className="font-medium text-primary hover:underline">
          {row.customer?.contact_name || `#${row.customer_id}`}
        </Link>
      ),
    },
    { key: "amount", label: t("amount"), render: (row) => formatMoney(row.amount, row.currency, locale) },
    { key: "syncStatus", label: t("syncStatus"), render: (row) => <StatusBadge status={row.zoho_sync_status} /> },
    {
      key: "lastError",
      label: t("lastError"),
      render: (row) => (
        <span className="block max-w-xs truncate text-xs text-danger">{row.last_sync_error || "—"}</span>
      ),
    },
    {
      key: "actions",
      label: tCommon("actions"),
      render: (row) =>
        canRetry ? (
          <Button size="sm" disabled={busyId === row.uuid} onClick={() => void onRetry(row.uuid)}>
            {t("retrySync")}
          </Button>
        ) : null,
    },
  ];

  return (
    <div>
      <PageHeader title={t("syncFailuresTitle")} subtitle={t("syncFailuresSubtitle")} />

      {success ? (
        <div className="mb-4">
          <Alert tone="success">{success}</Alert>
        </div>
      ) : null}

      {error ? (
        <ErrorState error={error} onRetry={() => void load()} />
      ) : (
        <DataTable
          rows={rows}
          columns={columns}
          rowKey={(row) => row.uuid}
          loading={loading}
          emptyLabel={tCommon("empty")}
          filters={<BranchPicker value={branch} onChange={setBranch} className="sm:max-w-xs" />}
        />
      )}
    </div>
  );
}
