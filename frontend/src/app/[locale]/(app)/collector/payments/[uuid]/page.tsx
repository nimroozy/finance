"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { getPayment, getPaymentSyncStatus } from "@/lib/payments";
import type { Payment, PaymentSyncStatus } from "@/lib/types";
import { formatDateTime, formatMoney } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CollectorPaymentDetailPage() {
  const t = useTranslations("paymentsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const params = useParams();
  const uuid = String(params.uuid || "");

  const [payment, setPayment] = useState<Payment | null>(null);
  const [sync, setSync] = useState<PaymentSyncStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!uuid) return;
    setLoading(true);
    setError(null);
    try {
      const [p, s] = await Promise.all([
        getPayment(uuid),
        getPaymentSyncStatus(uuid),
      ]);
      setPayment(p.data);
      setSync(s.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [uuid, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (!payment) {
    return error ? <Alert>{error}</Alert> : <EmptyState label={tCommon("empty")} />;
  }

  const receiptUuid = payment.receipt?.uuid;

  return (
    <div className="space-y-4">
      <PageHeader
        title={t("resultTitle")}
        subtitle={payment.payment_reference || payment.uuid}
        actions={
          receiptUuid ? (
            <Link href={`/collector/payments/${uuid}/receipt`}>
              <Button className="h-11">{t("goReceipt")}</Button>
            </Link>
          ) : undefined
        }
      />

      {error ? <Alert>{error}</Alert> : null}

      <Panel className="space-y-3 p-4 text-sm">
        <div className="flex items-center justify-between gap-2">
          <StatusBadge status={payment.status} />
          <StatusBadge status={payment.zoho_sync_status} />
        </div>
        <p>
          {t("customer")}:{" "}
          {payment.customer?.contact_name || `#${payment.customer_id}`}
        </p>
        <p>
          {t("amount")}:{" "}
          {formatMoney(payment.amount, payment.currency, locale)}
        </p>
        <p>
          {t("method")}:{" "}
          {locale === "fa" && payment.method?.name_fa
            ? payment.method.name_fa
            : payment.method?.name_en || "—"}
        </p>
        <p>
          {t("confirmedAt")}:{" "}
          {formatDateTime(payment.confirmed_at, locale)}
        </p>
        {!receiptUuid ? (
          <p className="text-muted">{t("noReceipt")}</p>
        ) : null}
      </Panel>

      <Panel className="space-y-2 p-4 text-sm">
        <div className="flex items-center justify-between">
          <p className="font-medium">{t("syncStatus")}</p>
          <Button variant="secondary" size="sm" onClick={() => void load()}>
            {t("refreshSync")}
          </Button>
        </div>
        {sync ? (
          <>
            <StatusBadge status={sync.zoho_sync_status} />
            {sync.last_sync_error ? (
              <p className="text-danger text-xs">
                {t("lastError")}: {sync.last_sync_error}
              </p>
            ) : null}
          </>
        ) : null}
      </Panel>

      <Panel>
        <div className="border-b border-border px-4 py-3 font-medium">
          {t("allocations")}
        </div>
        {payment.allocations?.length ? (
          <ul className="divide-y divide-border">
            {payment.allocations.map((a) => (
              <li
                key={`${a.invoice_id}-${a.amount}`}
                className="flex justify-between px-4 py-3 text-sm"
              >
                <span>
                  {a.invoice?.invoice_number || `#${a.invoice_id}`}
                </span>
                <span>{formatMoney(a.amount, payment.currency, locale)}</span>
              </li>
            ))}
          </ul>
        ) : (
          <EmptyState label={tCommon("empty")} />
        )}
      </Panel>
    </div>
  );
}
