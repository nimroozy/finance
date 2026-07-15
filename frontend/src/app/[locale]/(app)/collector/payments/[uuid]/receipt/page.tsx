"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { ApiError } from "@/lib/api";
import { getPayment } from "@/lib/payments";
import {
  downloadReceiptPdf,
  logReceiptPrint,
  receiptVerifyAbsoluteUrl,
} from "@/lib/receipts";
import type { Payment, Receipt } from "@/lib/types";
import { formatDateTime, formatMoney, cn } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { Button } from "@/components/ui/button";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CollectorReceiptPage() {
  const t = useTranslations("receiptsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const params = useParams();
  const uuid = String(params.uuid || "");
  const canPrint = useAuthStore((s) =>
    s.hasAnyPermission(["receipts.print", "receipts.manage"]),
  );

  const [payment, setPayment] = useState<Payment | null>(null);
  const [width, setWidth] = useState<"58" | "80">("58");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

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

  const receipt: Receipt | null = payment?.receipt ?? null;
  const verifyUrl = useMemo(() => {
    if (!receipt?.verification_token) return "";
    return receiptVerifyAbsoluteUrl(receipt.verification_token, locale);
  }, [receipt, locale]);

  async function onPrint(channel: "thermal_58" | "thermal_80") {
    if (!receipt || !canPrint) return;
    setBusy(true);
    setError(null);
    try {
      await logReceiptPrint(receipt.uuid, {
        channel,
        device_info:
          typeof navigator !== "undefined"
            ? navigator.userAgent.slice(0, 500)
            : undefined,
      });
      setWidth(channel === "thermal_58" ? "58" : "80");
      setMessage(t("reprintLogged"));
      window.print();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onPdf() {
    if (!receipt) return;
    setBusy(true);
    setError(null);
    try {
      await downloadReceiptPdf(
        receipt.uuid,
        `${receipt.receipt_number || receipt.uuid}.pdf`,
      );
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function copyLink() {
    if (!verifyUrl) return;
    try {
      await navigator.clipboard.writeText(verifyUrl);
      setMessage(t("copied"));
    } catch {
      setError(tCommon("error"));
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (!payment || !receipt) {
    return error ? (
      <Alert>{error}</Alert>
    ) : (
      <EmptyState label={tCommon("empty")} />
    );
  }

  const customerPhone =
    payment.customer?.whatsapp_number ||
    payment.customer?.mobile ||
    payment.customer?.phone ||
    "";

  const waShare = customerPhone
    ? `https://wa.me/${customerPhone.replace(/\D/g, "")}?text=${encodeURIComponent(verifyUrl)}`
    : `https://wa.me/?text=${encodeURIComponent(verifyUrl)}`;

  return (
    <div className="space-y-4">
      <PageHeader
        title={t("thermalTitle")}
        subtitle={receipt.receipt_number}
        actions={
          <div className="flex flex-wrap gap-2 print:hidden">
            <Button
              variant="secondary"
              size="sm"
              disabled={busy}
              onClick={() => void onPdf()}
            >
              {t("downloadPdf")}
            </Button>
            {canPrint ? (
              <>
                <Button
                  size="sm"
                  disabled={busy}
                  onClick={() => void onPrint("thermal_58")}
                >
                  {t("print58")}
                </Button>
                <Button
                  size="sm"
                  disabled={busy}
                  onClick={() => void onPrint("thermal_80")}
                >
                  {t("print80")}
                </Button>
              </>
            ) : null}
          </div>
        }
      />

      {error ? <Alert>{error}</Alert> : null}
      {message ? <Alert tone="success">{message}</Alert> : null}

      <div className="flex gap-2 print:hidden">
        <Button
          variant={width === "58" ? "primary" : "secondary"}
          size="sm"
          onClick={() => setWidth("58")}
        >
          {t("width58")}
        </Button>
        <Button
          variant={width === "80" ? "primary" : "secondary"}
          size="sm"
          onClick={() => setWidth("80")}
        >
          {t("width80")}
        </Button>
      </div>

      <Panel
        className={cn(
          "mx-auto space-y-2 p-4 font-mono text-xs leading-relaxed",
          width === "58" ? "max-w-[58mm]" : "max-w-[80mm]",
        )}
      >
        <p className="text-center text-sm font-bold">
          {receipt.receipt_number}
        </p>
        <p className="text-center">
          {formatDateTime(receipt.issued_at, locale)}
        </p>
        <hr className="border-dashed border-border" />
        <p>
          {payment.customer?.contact_name ||
            payment.customer?.customer_number ||
            `#${payment.customer_id}`}
        </p>
        <p className="text-base font-semibold">
          {formatMoney(payment.amount, payment.currency, locale)}
        </p>
        <p>
          {locale === "fa" && payment.method?.name_fa
            ? payment.method.name_fa
            : payment.method?.name_en || "—"}
        </p>
        <hr className="border-dashed border-border" />
        {payment.allocations?.map((a) => (
          <div key={`${a.invoice_id}-${a.amount}`} className="flex justify-between">
            <span>{a.invoice?.invoice_number || a.invoice_id}</span>
            <span>{formatMoney(a.amount, payment.currency, locale)}</span>
          </div>
        ))}
        <hr className="border-dashed border-border" />
        <p className="break-all text-[10px] text-muted">{verifyUrl}</p>
      </Panel>

      <div className="flex flex-col gap-2 print:hidden">
        <Button className="h-11 w-full" variant="secondary" onClick={() => void copyLink()}>
          {t("copyVerify")}
        </Button>
        <a href={waShare} target="_blank" rel="noreferrer" className="block">
          <Button className="h-11 w-full" variant="secondary">
            {t("shareWhatsApp")}
          </Button>
        </a>
      </div>
    </div>
  );
}
