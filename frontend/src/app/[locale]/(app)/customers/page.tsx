"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listCustomers } from "@/lib/customers";
import type { Customer } from "@/lib/types";
import { formatMoney } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CustomersPage() {
  const t = useTranslations("customers");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [customers, setCustomers] = useState<Customer[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [search, setSearch] = useState("");
  const [appliedSearch, setAppliedSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (page = 1, searchValue = appliedSearch) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listCustomers({
          page,
          search: searchValue || undefined,
        });
        setCustomers(res.data);
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
    [appliedSearch, tCommon],
  );

  useEffect(() => {
    void load(1, appliedSearch);
  }, [load, appliedSearch]);

  function onSearch(e: React.FormEvent) {
    e.preventDefault();
    setAppliedSearch(search.trim());
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
          onSubmit={onSearch}
          className="flex flex-col gap-2 sm:flex-row sm:items-center"
        >
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t("searchPlaceholder")}
            className="sm:max-w-md"
          />
          <Button type="submit">{tCommon("search")}</Button>
        </form>
      </Panel>

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : customers.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[800px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("contact")}</th>
                  <th className="px-4 py-3 font-medium">{t("company")}</th>
                  <th className="px-4 py-3 font-medium">{t("customerNumber")}</th>
                  <th className="px-4 py-3 font-medium">{t("branch")}</th>
                  <th className="px-4 py-3 font-medium">{t("balance")}</th>
                  <th className="px-4 py-3 font-medium">{t("status")}</th>
                </tr>
              </thead>
              <tbody>
                {customers.map((customer) => (
                  <tr
                    key={customer.id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-4 py-3">
                      <Link
                        href={`/customers/${customer.id}`}
                        className="font-medium text-primary hover:underline"
                      >
                        {customer.contact_name || "—"}
                      </Link>
                      {customer.email ? (
                        <div className="text-xs text-muted">{customer.email}</div>
                      ) : null}
                    </td>
                    <td className="px-4 py-3">
                      {customer.company_name || "—"}
                    </td>
                    <td className="px-4 py-3">
                      {customer.customer_number || "—"}
                    </td>
                    <td className="px-4 py-3">
                      {customer.branch?.code || "—"}
                    </td>
                    <td className="px-4 py-3">
                      {formatMoney(
                        customer.outstanding_receivable,
                        customer.currency,
                        locale,
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <Badge
                        tone={
                          customer.status === "active" ? "success" : "neutral"
                        }
                      >
                        {customer.status}
                      </Badge>
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
