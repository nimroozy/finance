"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { getCustomer, listInvoices } from "@/lib/customers";
import type { Customer, Invoice } from "@/lib/types";
import { formatDate, formatDateTime, formatMoney } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CustomerDetailPage() {
  const t = useTranslations("customers");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const params = useParams<{ id: string }>();
  const id = Number(params.id);

  const [customer, setCustomer] = useState<Customer | null>(null);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!Number.isFinite(id) || id <= 0) {
      setError(tCommon("error"));
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [customerRes, invoicesRes] = await Promise.all([
        getCustomer(id),
        listInvoices({ customer_id: id, per_page: 50 }).catch(() => ({
          data: [] as Invoice[],
        })),
      ]);
      setCustomer(customerRes.data);
      setInvoices(invoicesRes.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) {
    return <LoadingState label={tCommon("loading")} />;
  }

  if (error || !customer) {
    return (
      <div>
        <PageHeader title={t("detailTitle")} />
        <Alert>{error || tCommon("error")}</Alert>
        <div className="mt-4">
          <Link href="/customers" className="text-sm text-primary hover:underline">
            {t("back")}
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={customer.contact_name || t("detailTitle")}
        subtitle={t("detailSubtitle")}
        actions={
          <Link
            href="/customers"
            className="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm hover:bg-sand-soft"
          >
            {t("back")}
          </Link>
        }
      />

      <Panel className="mb-6 p-4">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <p className="text-xs text-muted">{t("company")}</p>
            <p className="mt-1 text-sm font-medium">
              {customer.company_name || "—"}
            </p>
          </div>
          <div>
            <p className="text-xs text-muted">{t("customerNumber")}</p>
            <p className="mt-1 text-sm font-medium">
              {customer.customer_number || "—"}
            </p>
          </div>
          <div>
            <p className="text-xs text-muted">{t("branch")}</p>
            <p className="mt-1 text-sm font-medium">
              {customer.branch
                ? `${customer.branch.code} — ${
                    locale === "fa" && customer.branch.name_fa
                      ? customer.branch.name_fa
                      : customer.branch.name_en
                  }`
                : "—"}
            </p>
          </div>
          <div>
            <p className="text-xs text-muted">{t("email")}</p>
            <p className="mt-1 text-sm font-medium">{customer.email || "—"}</p>
          </div>
          <div>
            <p className="text-xs text-muted">{t("phone")}</p>
            <p className="mt-1 text-sm font-medium">
              {customer.phone || customer.mobile || "—"}
            </p>
          </div>
          <div>
            <p className="text-xs text-muted">{t("balance")}</p>
            <p className="mt-1 text-sm font-medium">
              {formatMoney(
                customer.outstanding_receivable,
                customer.currency,
                locale,
              )}
            </p>
          </div>
          <div>
            <p className="text-xs text-muted">{t("status")}</p>
            <div className="mt-1">
              <Badge
                tone={customer.status === "active" ? "success" : "neutral"}
              >
                {customer.status}
              </Badge>
            </div>
          </div>
          <div>
            <p className="text-xs text-muted">{t("currency")}</p>
            <p className="mt-1 text-sm font-medium">
              {customer.currency || "—"}
            </p>
          </div>
          <div>
            <p className="text-xs text-muted">{t("paymentTerms")}</p>
            <p className="mt-1 text-sm font-medium">
              {customer.payment_terms || "—"}
            </p>
          </div>
          <div>
            <p className="text-xs text-muted">{t("lastSynced")}</p>
            <p className="mt-1 text-sm font-medium">
              {formatDateTime(customer.last_synced_at, locale)}
            </p>
          </div>
        </div>
      </Panel>

      <PageHeader title={t("invoices")} />
      <Panel>
        {invoices.length === 0 ? (
          <EmptyState label={t("noInvoices")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("invoiceNumber")}</th>
                  <th className="px-4 py-3 font-medium">{t("invoiceDate")}</th>
                  <th className="px-4 py-3 font-medium">{t("dueDate")}</th>
                  <th className="px-4 py-3 font-medium">{t("status")}</th>
                  <th className="px-4 py-3 font-medium">{t("total")}</th>
                  <th className="px-4 py-3 font-medium">{t("invoiceBalance")}</th>
                </tr>
              </thead>
              <tbody>
                {invoices.map((invoice) => (
                  <tr
                    key={invoice.id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-4 py-3 font-medium">
                      {invoice.invoice_number || "—"}
                    </td>
                    <td className="px-4 py-3">
                      {formatDate(invoice.invoice_date, locale)}
                    </td>
                    <td className="px-4 py-3">
                      {formatDate(invoice.due_date, locale)}
                    </td>
                    <td className="px-4 py-3">
                      <Badge
                        tone={
                          invoice.status === "paid"
                            ? "success"
                            : invoice.status === "overdue"
                              ? "danger"
                              : "neutral"
                        }
                      >
                        {invoice.status}
                      </Badge>
                    </td>
                    <td className="px-4 py-3">
                      {formatMoney(invoice.total, invoice.currency, locale)}
                    </td>
                    <td className="px-4 py-3">
                      {formatMoney(invoice.balance, invoice.currency, locale)}
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
