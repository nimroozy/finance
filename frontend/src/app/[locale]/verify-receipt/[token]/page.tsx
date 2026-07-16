"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { verifyReceipt } from "@/lib/receipts";
import type { ReceiptVerification } from "@/lib/types";
import { formatDateTime, formatMoney } from "@/lib/utils";
import { LocaleSwitcher } from "@/components/locale-switcher";
import { StatusBadge } from "@/components/status-badge";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

export default function VerifyReceiptPage() {
  const t = useTranslations("verifyReceipt");
  const tApp = useTranslations("app");
  const locale = useLocale();
  const params = useParams();
  const token = String(params.token || "");

  const [data, setData] = useState<ReceiptVerification | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) return;
    void (async () => {
      setLoading(true);
      setError(null);
      try {
        const res = await verifyReceipt(token);
        setData(res);
      } catch (err) {
        setError(err instanceof Error ? err.message : t("notFound"));
        setData(null);
      } finally {
        setLoading(false);
      }
    })();
  }, [token, t]);

  return (
    <div className="mx-auto flex min-h-screen max-w-lg flex-col px-4 py-8">
      <header className="mb-8 flex items-start justify-between gap-3">
        <div>
          <p className="text-lg font-semibold text-primary">{tApp("name")}</p>
          <p className="text-sm text-muted">{t("title")}</p>
        </div>
        <LocaleSwitcher />
      </header>

      {loading ? <LoadingState label={t("loading")} /> : null}
      {error ? <Alert>{error}</Alert> : null}

      {data ? (
        <Panel className="space-y-3 p-5">
          <div className="flex items-center justify-between gap-2">
            <p className="font-semibold">{data.receipt_number}</p>
            <StatusBadge status={data.status} />
          </div>
          <dl className="space-y-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-muted">{t("amount")}</dt>
              <dd className="font-medium">
                {formatMoney(data.amount, data.currency, locale)}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-muted">{t("customer")}</dt>
              <dd>
                {data.customer?.contact_name ||
                  data.customer?.customer_number ||
                  "—"}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-muted">{t("branch")}</dt>
              <dd>
                {locale === "fa"
                  ? data.branch?.name_fa || data.branch?.name_en || "—"
                  : data.branch?.name_en || data.branch?.code || "—"}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-muted">{t("issuedAt")}</dt>
              <dd>{formatDateTime(data.issued_at, locale)}</dd>
            </div>
            {data.payment_reference ? (
              <div className="flex justify-between gap-3">
                <dt className="text-muted">{t("reference")}</dt>
                <dd className="font-mono text-xs">{data.payment_reference}</dd>
              </div>
            ) : null}
          </dl>
        </Panel>
      ) : null}
    </div>
  );
}
