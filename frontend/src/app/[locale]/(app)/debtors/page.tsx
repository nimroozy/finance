"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { listBranches } from "@/lib/auth";
import { ApiError } from "@/lib/api";
import { exportDebtors, listDebtors } from "@/lib/customers";
import type { Branch, Customer, DebtorListParams } from "@/lib/types";
import { formatMoney } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
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

type Filters = {
  search: string;
  branch_id: string;
  min_balance: string;
  max_balance: string;
  days_overdue_min: string;
  status: string;
  sort: string;
};

const emptyFilters: Filters = {
  search: "",
  branch_id: "",
  min_balance: "",
  max_balance: "",
  days_overdue_min: "",
  status: "",
  sort: "balance_desc",
};

function toParams(filters: Filters, page = 1): DebtorListParams {
  return {
    page,
    search: filters.search || undefined,
    branch_id: filters.branch_id || undefined,
    min_balance: filters.min_balance || undefined,
    max_balance: filters.max_balance || undefined,
    days_overdue_min: filters.days_overdue_min || undefined,
    status: filters.status || undefined,
    sort: filters.sort || undefined,
  };
}

export default function DebtorsPage() {
  const t = useTranslations("debtors");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canExport = useAuthStore((s) => s.hasPermission("debtors.export"));

  const [debtors, setDebtors] = useState<Customer[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [form, setForm] = useState<Filters>(emptyFilters);
  const [applied, setApplied] = useState<Filters>(emptyFilters);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const load = useCallback(
    async (page = 1, filters = applied) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listDebtors(toParams(filters, page));
        setDebtors(res.data);
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
    void load(1, applied);
  }, [load, applied]);

  useEffect(() => {
    void listBranches(1)
      .then((res) => setBranches(res.data))
      .catch(() => setBranches([]));
  }, []);

  function onApply(e: React.FormEvent) {
    e.preventDefault();
    setApplied({ ...form });
  }

  function onReset() {
    setForm(emptyFilters);
    setApplied(emptyFilters);
  }

  async function onExport() {
    setExporting(true);
    setError(null);
    setSuccess(null);
    try {
      await exportDebtors(toParams(applied));
      setSuccess(t("exportSuccess"));
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : t("exportFailed"),
      );
    } finally {
      setExporting(false);
    }
  }

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          canExport ? (
            <Button
              variant="secondary"
              onClick={() => void onExport()}
              disabled={exporting}
            >
              {tCommon("export")}
            </Button>
          ) : undefined
        }
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

      <Panel className="mb-4 p-4">
        <form onSubmit={onApply} className="grid gap-3 md:grid-cols-3">
          <div className="md:col-span-2">
            <Label htmlFor="search">{tCommon("search")}</Label>
            <Input
              id="search"
              value={form.search}
              onChange={(e) => setForm({ ...form, search: e.target.value })}
              placeholder={t("searchPlaceholder")}
            />
          </div>
          <div>
            <Label htmlFor="branch">{t("branch")}</Label>
            <Select
              id="branch"
              value={form.branch_id}
              onChange={(e) =>
                setForm({ ...form, branch_id: e.target.value })
              }
            >
              <option value="">{tCommon("all")}</option>
              {branches.map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.code} —{" "}
                  {locale === "fa" ? branch.name_fa : branch.name_en}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="min_balance">{t("minBalance")}</Label>
            <Input
              id="min_balance"
              type="number"
              min={0}
              value={form.min_balance}
              onChange={(e) =>
                setForm({ ...form, min_balance: e.target.value })
              }
            />
          </div>
          <div>
            <Label htmlFor="max_balance">{t("maxBalance")}</Label>
            <Input
              id="max_balance"
              type="number"
              min={0}
              value={form.max_balance}
              onChange={(e) =>
                setForm({ ...form, max_balance: e.target.value })
              }
            />
          </div>
          <div>
            <Label htmlFor="days">{t("daysOverdueMin")}</Label>
            <Input
              id="days"
              type="number"
              min={0}
              value={form.days_overdue_min}
              onChange={(e) =>
                setForm({ ...form, days_overdue_min: e.target.value })
              }
            />
          </div>
          <div>
            <Label htmlFor="sort">{t("sort")}</Label>
            <Select
              id="sort"
              value={form.sort}
              onChange={(e) => setForm({ ...form, sort: e.target.value })}
            >
              <option value="balance_desc">{t("sortBalanceDesc")}</option>
              <option value="balance_asc">{t("sortBalanceAsc")}</option>
              <option value="name">{t("sortName")}</option>
            </Select>
          </div>
          <div className="flex items-end gap-2 md:col-span-3">
            <Button type="submit">{tCommon("apply")}</Button>
            <Button type="button" variant="secondary" onClick={onReset}>
              {tCommon("reset")}
            </Button>
          </div>
        </form>
      </Panel>

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : debtors.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[880px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("contact")}</th>
                  <th className="px-4 py-3 font-medium">{t("company")}</th>
                  <th className="px-4 py-3 font-medium">{t("customerNumber")}</th>
                  <th className="px-4 py-3 font-medium">{t("branch")}</th>
                  <th className="px-4 py-3 font-medium">{t("balance")}</th>
                  <th className="px-4 py-3 font-medium">{t("phone")}</th>
                  <th className="px-4 py-3 font-medium">{t("status")}</th>
                </tr>
              </thead>
              <tbody>
                {debtors.map((debtor) => (
                  <tr
                    key={debtor.id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-4 py-3">
                      <Link
                        href={`/customers/${debtor.id}`}
                        className="font-medium text-primary hover:underline"
                      >
                        {debtor.contact_name || "—"}
                      </Link>
                    </td>
                    <td className="px-4 py-3">{debtor.company_name || "—"}</td>
                    <td className="px-4 py-3">
                      {debtor.customer_number || "—"}
                    </td>
                    <td className="px-4 py-3">{debtor.branch?.code || "—"}</td>
                    <td className="px-4 py-3 font-medium">
                      {formatMoney(
                        debtor.outstanding_receivable,
                        debtor.currency,
                        locale,
                      )}
                    </td>
                    <td className="px-4 py-3">
                      {debtor.phone || debtor.mobile || "—"}
                    </td>
                    <td className="px-4 py-3">
                      <Badge tone="warning">{debtor.status}</Badge>
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
