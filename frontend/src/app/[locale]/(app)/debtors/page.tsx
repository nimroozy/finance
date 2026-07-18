"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { exportDebtors, listDebtors } from "@/lib/customers";
import type { Customer, DebtorListParams } from "@/lib/types";
import { formatMoney } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input, Label, Select } from "@/components/ui/form";
import { Alert, PageHeader, Panel } from "@/components/ui/layout";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ErrorState } from "@/components/ui/error-state";
import { BranchPicker } from "@/components/ui/pickers";
import type { SearchableOption } from "@/components/ui/searchable-select";

type Filters = {
  search: string;
  min_balance: string;
  max_balance: string;
  days_overdue_min: string;
  status: string;
  sort: string;
};

const emptyFilters: Filters = {
  search: "",
  min_balance: "",
  max_balance: "",
  days_overdue_min: "",
  status: "",
  sort: "balance_desc",
};

function toParams(filters: Filters, branchId: string, page = 1): DebtorListParams {
  return {
    page,
    search: filters.search || undefined,
    branch_id: branchId || undefined,
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
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [form, setForm] = useState<Filters>(emptyFilters);
  const [applied, setApplied] = useState<Filters>(emptyFilters);
  const [branch, setBranch] = useState<SearchableOption | null>(null);
  const [appliedBranch, setAppliedBranch] = useState<SearchableOption | null>(null);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState<unknown>(null);
  const [exportError, setExportError] = useState<unknown>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const load = useCallback(
    async (page = 1, filters = applied, branchFilter = appliedBranch) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listDebtors(
          toParams(filters, branchFilter ? String(branchFilter.id) : "", page),
        );
        setDebtors(res.data);
        setMeta({
          current_page: res.meta?.current_page ?? 1,
          last_page: res.meta?.last_page ?? 1,
        });
      } catch (err) {
        setError(err);
      } finally {
        setLoading(false);
      }
    },
    [applied, appliedBranch],
  );

  useEffect(() => {
    void load(1, applied, appliedBranch);
  }, [load, applied, appliedBranch]);

  function onApply(e: React.FormEvent) {
    e.preventDefault();
    setApplied({ ...form });
    setAppliedBranch(branch);
  }

  function onReset() {
    setForm(emptyFilters);
    setApplied(emptyFilters);
    setBranch(null);
    setAppliedBranch(null);
  }

  async function onExport() {
    setExporting(true);
    setExportError(null);
    setSuccess(null);
    try {
      await exportDebtors(toParams(applied, appliedBranch ? String(appliedBranch.id) : ""));
      setSuccess(t("exportSuccess"));
    } catch (err) {
      setExportError(err);
    } finally {
      setExporting(false);
    }
  }

  const columns: DataTableColumn<Customer>[] = [
    {
      key: "contact",
      label: t("contact"),
      render: (row) => (
        <Link href={`/customers/${row.id}`} className="font-medium text-primary hover:underline">
          {row.contact_name || "—"}
        </Link>
      ),
    },
    { key: "company", label: t("company"), render: (row) => row.company_name || "—" },
    { key: "customerNumber", label: t("customerNumber"), render: (row) => row.customer_number || "—" },
    { key: "branch", label: t("branch"), render: (row) => row.branch?.code || "—" },
    {
      key: "balance",
      label: t("balance"),
      render: (row) => (
        <span className="font-medium">
          {formatMoney(row.outstanding_receivable, row.currency, locale)}
        </span>
      ),
    },
    { key: "phone", label: t("phone"), render: (row) => row.phone || row.mobile || "—" },
    { key: "status", label: t("status"), render: (row) => <Badge tone="warning">{row.status}</Badge> },
  ];

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          canExport ? (
            <Button variant="secondary" onClick={() => void onExport()} disabled={exporting}>
              {tCommon("export")}
            </Button>
          ) : undefined
        }
      />

      {exportError ? (
        <div className="mb-4">
          <ErrorState error={exportError} onRetry={() => void onExport()} />
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
            <Label>{t("branch")}</Label>
            <BranchPicker value={branch} onChange={setBranch} />
          </div>
          <div>
            <Label htmlFor="min_balance">{t("minBalance")}</Label>
            <Input
              id="min_balance"
              type="number"
              min={0}
              value={form.min_balance}
              onChange={(e) => setForm({ ...form, min_balance: e.target.value })}
            />
          </div>
          <div>
            <Label htmlFor="max_balance">{t("maxBalance")}</Label>
            <Input
              id="max_balance"
              type="number"
              min={0}
              value={form.max_balance}
              onChange={(e) => setForm({ ...form, max_balance: e.target.value })}
            />
          </div>
          <div>
            <Label htmlFor="days">{t("daysOverdueMin")}</Label>
            <Input
              id="days"
              type="number"
              min={0}
              value={form.days_overdue_min}
              onChange={(e) => setForm({ ...form, days_overdue_min: e.target.value })}
            />
          </div>
          <div>
            <Label htmlFor="sort">{t("sort")}</Label>
            <Select id="sort" value={form.sort} onChange={(e) => setForm({ ...form, sort: e.target.value })}>
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

      {error ? (
        <ErrorState error={error} onRetry={() => load(meta.current_page)} />
      ) : (
        <DataTable
          rows={debtors}
          columns={columns}
          rowKey={(row) => row.id}
          loading={loading}
          emptyLabel={tCommon("empty")}
          page={meta.current_page}
          lastPage={meta.last_page}
          onPageChange={(page) => void load(page)}
        />
      )}
    </div>
  );
}
