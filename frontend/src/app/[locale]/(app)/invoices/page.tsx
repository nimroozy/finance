"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { listInvoices } from "@/lib/customers";
import type { Invoice } from "@/lib/types";
import { formatDate, formatMoney } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input, Label, Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

const STATUS_KEYS = [
  "all",
  "draft",
  "sent",
  "open",
  "overdue",
  "paid",
  "partially_paid",
  "void",
] as const;

export default function InvoicesPage() {
  const t = useTranslations("invoices");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [applied, setApplied] = useState({ search: "", status: "all" });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listInvoices({
          page,
          search: applied.search || undefined,
          status: applied.status === "all" ? undefined : applied.status,
        });
        setInvoices(res.data);
        setMeta({
          current_page: res.meta?.current_page ?? 1,
          last_page: res.meta?.last_page ?? 1,
        });
      } catch (err) {
        setError(err instanceof ApiError ? err.message : tCommon("error"));
      } finally {
        setLoading(false);
      }
    },
    [applied, tCommon],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  function onFilter(e: React.FormEvent) {
    e.preventDefault();
    setApplied({ search: search.trim(), status });
  }

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      <Panel className="mb-4 p-4">
        <form
          onSubmit={onFilter}
          className="grid gap-3 sm:grid-cols-[1fr_180px_auto]"
        >
          <div>
            <Label htmlFor="search">{tCommon("search")}</Label>
            <Input
              id="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={t("searchPlaceholder")}
            />
          </div>
          <div>
            <Label htmlFor="status">{t("statusFilter")}</Label>
            <Select
              id="status"
              value={status}
              onChange={(e) => setStatus(e.target.value)}
            >
              {STATUS_KEYS.map((key) => (
                <option key={key} value={key}>
                  {t(`statuses.${key}`)}
                </option>
              ))}
            </Select>
          </div>
          <div className="flex items-end">
            <Button type="submit">{tCommon("apply")}</Button>
          </div>
        </form>
      </Panel>

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : invoices.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[880px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("invoiceNumber")}</th>
                  <th className="px-4 py-3 font-medium">{t("customer")}</th>
                  <th className="px-4 py-3 font-medium">{t("branch")}</th>
                  <th className="px-4 py-3 font-medium">{t("date")}</th>
                  <th className="px-4 py-3 font-medium">{t("dueDate")}</th>
                  <th className="px-4 py-3 font-medium">{t("status")}</th>
                  <th className="px-4 py-3 font-medium">{t("total")}</th>
                  <th className="px-4 py-3 font-medium">{t("balance")}</th>
                  <th className="px-4 py-3 font-medium">{t("effectiveBalance")}</th>
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
                      {invoice.customer?.contact_name || "—"}
                    </td>
                    <td className="px-4 py-3">
                      {invoice.branch?.code || "—"}
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
                      {formatMoney(
                        invoice.zoho_synced_balance ?? invoice.balance,
                        invoice.currency,
                        locale,
                      )}
                      {invoice.awaiting_zoho_refresh ? (
                        <span className="mt-1 block text-xs text-amber-700">
                          {t("awaitingZohoRefresh")}
                        </span>
                      ) : null}
                    </td>
                    <td className="px-4 py-3 font-medium">
                      {formatMoney(
                        invoice.effective_balance ?? invoice.balance,
                        invoice.currency,
                        locale,
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {meta.last_page > 1 ? (
          <div className="flex items-center justify-between gap-3 border-t border-border px-4 py-3">
            <p className="text-sm text-muted">
              {tCommon("page", {
                page: meta.current_page,
                total: meta.last_page,
              })}
            </p>
            <div className="flex gap-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page <= 1 || loading}
                onClick={() => void load(meta.current_page - 1)}
              >
                {tCommon("previous")}
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page >= meta.last_page || loading}
                onClick={() => void load(meta.current_page + 1)}
              >
                {tCommon("next")}
              </Button>
            </div>
          </div>
        ) : null}
      </Panel>
    </div>
  );
}
