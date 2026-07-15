"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  approveReversal,
  getPayment,
  paymentLatestReversal,
  rejectReversal,
  requestPaymentReversal,
  retryPaymentSync,
} from "@/lib/payments";
import type { Payment } from "@/lib/types";
import { formatDateTime, formatMoney } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { TextArea } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function PaymentDetailPage() {
  const t = useTranslations("paymentsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const params = useParams();
  const uuid = String(params.uuid || "");

  const canRetry = useAuthStore((s) => s.hasPermission("payments.retry_sync"));
  const canRequestReversal = useAuthStore((s) =>
    s.hasPermission("reversals.request"),
  );
  const canApprove = useAuthStore((s) => s.hasPermission("reversals.approve"));

  const [payment, setPayment] = useState<Payment | null>(null);
  const [reason, setReason] = useState("");
  const [rejectReason, setRejectReason] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!uuid) return;
    setLoading(true);
    setError(null);
    try {
      const res = await getPayment(uuid);
      setPayment(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [uuid, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onRetry() {
    setBusy(true);
    setError(null);
    try {
      await retryPaymentSync(uuid);
      setSuccess(t("retrySuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onRequestReversal(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await requestPaymentReversal(uuid, reason.trim());
      setReason("");
      setSuccess(t("reversalSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onApprove(id: number) {
    setBusy(true);
    setError(null);
    try {
      await approveReversal(id);
      setSuccess(t("approveSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onReject(id: number) {
    setBusy(true);
    setError(null);
    try {
      await rejectReversal(id, rejectReason.trim());
      setRejectReason("");
      setSuccess(t("rejectSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (!payment) {
    return error ? <Alert>{error}</Alert> : <EmptyState label={tCommon("empty")} />;
  }

  const reversal = paymentLatestReversal(payment);
  const receiptUuid = payment.receipt?.uuid;

  return (
    <div className="space-y-4">
      <PageHeader
        title={t("detailTitle")}
        subtitle={payment.payment_reference || payment.uuid}
        actions={
          <div className="flex flex-wrap gap-2">
            {canRetry &&
            (payment.zoho_sync_status === "failed" ||
              payment.status === "sync_failed") ? (
              <Button disabled={busy} onClick={() => void onRetry()}>
                {t("retrySync")}
              </Button>
            ) : null}
            {receiptUuid ? (
              <Link href={`/collector/payments/${payment.uuid}/receipt`}>
                <Button variant="secondary">{t("viewReceipt")}</Button>
              </Link>
            ) : null}
          </div>
        }
      />

      {error ? <Alert>{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}

      <Panel className="grid gap-3 p-4 text-sm sm:grid-cols-2">
        <div>
          <p className="text-muted">{t("customer")}</p>
          <p className="font-medium">
            {payment.customer?.contact_name || `#${payment.customer_id}`}
          </p>
        </div>
        <div>
          <p className="text-muted">{t("amount")}</p>
          <p className="font-medium">
            {formatMoney(payment.amount, payment.currency, locale)}
          </p>
        </div>
        <div>
          <p className="text-muted">{t("status")}</p>
          <StatusBadge status={payment.status} />
        </div>
        <div>
          <p className="text-muted">{t("syncStatus")}</p>
          <StatusBadge status={payment.zoho_sync_status} />
        </div>
        <div>
          <p className="text-muted">{t("method")}</p>
          <p>{payment.method?.name_en || "—"}</p>
        </div>
        <div>
          <p className="text-muted">{t("confirmedAt")}</p>
          <p>{formatDateTime(payment.confirmed_at, locale)}</p>
        </div>
        {payment.last_sync_error ? (
          <div className="sm:col-span-2">
            <p className="text-muted">{t("lastError")}</p>
            <p className="text-danger">{payment.last_sync_error}</p>
          </div>
        ) : null}
        {payment.notes ? (
          <div className="sm:col-span-2">
            <p className="text-muted">{t("notes")}</p>
            <p className="whitespace-pre-wrap">{payment.notes}</p>
          </div>
        ) : null}
      </Panel>

      <Panel>
        <div className="border-b border-border px-4 py-3 font-medium">
          {t("allocations")}
        </div>
        {payment.allocations?.length ? (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-2 text-start font-medium">
                    {t("invoice")}
                  </th>
                  <th className="px-4 py-2 text-start font-medium">
                    {t("amount")}
                  </th>
                </tr>
              </thead>
              <tbody>
                {payment.allocations.map((a) => (
                  <tr key={`${a.invoice_id}-${a.amount}`} className="border-t border-border">
                    <td className="px-4 py-2">
                      {a.invoice?.invoice_number || `#${a.invoice_id}`}
                    </td>
                    <td className="px-4 py-2">
                      {formatMoney(a.amount, payment.currency, locale)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState label={tCommon("empty")} />
        )}
      </Panel>

      {reversal ? (
        <Panel className="space-y-3 p-4">
          <p className="font-medium">{t("pendingReversal")}</p>
          <StatusBadge status={reversal.status} />
          <p className="text-sm whitespace-pre-wrap">{reversal.reason}</p>
          {reversal.status === "pending" && canApprove ? (
            <div className="space-y-3">
              <Button disabled={busy} onClick={() => void onApprove(reversal.id)}>
                {t("approveReversal")}
              </Button>
              <TextArea
                value={rejectReason}
                onChange={(e) => setRejectReason(e.target.value)}
                placeholder={t("rejectReason")}
              />
              <Button
                variant="danger"
                disabled={busy || rejectReason.trim().length < 3}
                onClick={() => void onReject(reversal.id)}
              >
                {t("rejectReversal")}
              </Button>
            </div>
          ) : null}
        </Panel>
      ) : canRequestReversal && payment.status !== "reversed" && payment.status !== "draft" ? (
        <Panel className="p-4">
          <form onSubmit={onRequestReversal} className="space-y-3">
            <p className="font-medium">{t("requestReversal")}</p>
            <TextArea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder={t("reversalReason")}
              required
              minLength={3}
            />
            <Button type="submit" variant="danger" disabled={busy}>
              {t("requestReversal")}
            </Button>
          </form>
        </Panel>
      ) : null}
    </div>
  );
}
