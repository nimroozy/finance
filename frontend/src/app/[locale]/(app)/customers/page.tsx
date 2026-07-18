"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { listCustomers } from "@/lib/customers";
import type { Customer } from "@/lib/types";
import { formatDate, formatMoney } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { LtrValue } from "@/components/ltr-value";
import { PageHeader } from "@/components/ui/layout";
import { PageToolbar } from "@/components/ui/page-toolbar";
import { Input, Select } from "@/components/ui/form";
import { FilterBar } from "@/components/ui/filter-bar";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { MobileRecordCard } from "@/components/ui/record-list";
import { BranchPicker } from "@/components/ui/pickers";
import type { SearchableOption } from "@/components/ui/searchable-select";
import { ErrorState } from "@/components/ui/error-state";

const STATUSES = ["active", "inactive", "suspended", "archived"];
const SYNC_STATUSES = ["synced", "syncing", "sync_failed"];

export default function CustomersPage() {
  const t = useTranslations("customers");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [customers, setCustomers] = useState<Customer[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [search, setSearch] = useState("");
  const [appliedSearch, setAppliedSearch] = useState("");
  const [status, setStatus] = useState("");
  const [syncStatus, setSyncStatus] = useState("");
  const [branch, setBranch] = useState<SearchableOption | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listCustomers({
          page,
          search: appliedSearch || undefined,
          status: status || undefined,
          sync_status: syncStatus || undefined,
          branch_id: branch ? String(branch.id) : undefined,
        });
        setCustomers(res.data);
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
    [appliedSearch, status, syncStatus, branch],
  );

  useEffect(() => {
    void load(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [appliedSearch, status, syncStatus, branch]);

  function onSearch(e: React.FormEvent) {
    e.preventDefault();
    setAppliedSearch(search.trim());
  }

  const filtersActive = Boolean(status || syncStatus || branch);

  function resetFilters() {
    setStatus("");
    setSyncStatus("");
    setBranch(null);
  }

  const columns: DataTableColumn<Customer>[] = [
    {
      key: "contact",
      label: t("contact"),
      render: (row) => (
        <Link href={`/customers/${row.id}`} className="block">
          <p className="font-medium text-primary hover:underline">{row.contact_name || row.company_name || "—"}</p>
          {row.company_name && row.contact_name ? <p className="text-xs text-muted">{row.company_name}</p> : null}
        </Link>
      ),
    },
    {
      key: "number",
      label: t("customerNumber"),
      render: (row) => <LtrValue className="text-sm">{row.customer_number || "—"}</LtrValue>,
    },
    {
      key: "phone",
      label: t("phone"),
      render: (row) => <LtrValue className="text-sm">{row.mobile || row.phone || "—"}</LtrValue>,
    },
    { key: "branch", label: t("branch"), render: (row) => row.branch?.code || "—" },
    {
      key: "balance",
      label: t("balance"),
      render: (row) => formatMoney(row.outstanding_receivable, row.currency, locale),
    },
    {
      key: "sync",
      label: t("syncStatus"),
      render: (row) => <StatusBadge status={row.sync_status ?? undefined} />,
    },
    {
      key: "lastActivity",
      label: t("lastSynced"),
      render: (row) => formatDate(row.last_synced_at, locale),
    },
    { key: "status", label: t("status"), render: (row) => <StatusBadge status={row.status} /> },
  ];

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      <PageToolbar>
        <form onSubmit={onSearch} className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t("searchPlaceholder")}
            className="sm:max-w-sm"
            type="search"
          />
          <FilterBar active={filtersActive} onReset={resetFilters}>
            <BranchPicker value={branch} onChange={setBranch} className="w-40" />
            <Select value={status} onChange={(e) => setStatus(e.target.value)} className="w-36">
              <option value="">{t("status")}</option>
              {STATUSES.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </Select>
            <Select value={syncStatus} onChange={(e) => setSyncStatus(e.target.value)} className="w-40">
              <option value="">{t("syncStatus")}</option>
              {SYNC_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {s.replace(/_/g, " ")}
                </option>
              ))}
            </Select>
          </FilterBar>
        </form>
      </PageToolbar>

      {error ? (
        <ErrorState error={error} onRetry={() => load(meta.current_page)} />
      ) : (
        <DataTable
          rows={customers}
          columns={columns}
          rowKey={(row) => row.id}
          loading={loading}
          emptyLabel={tCommon("empty")}
          page={meta.current_page}
          lastPage={meta.last_page}
          onPageChange={(page) => void load(page)}
          mobileCard={(row) => (
            <MobileRecordCard
              href={`/customers/${row.id}`}
              title={row.contact_name || row.company_name || "—"}
              subtitle={row.customer_number ?? undefined}
              status={<StatusBadge status={row.status} />}
              fields={[
                { label: t("phone"), value: row.mobile || row.phone || "—" },
                { label: t("branch"), value: row.branch?.code || "—" },
                { label: t("balance"), value: formatMoney(row.outstanding_receivable, row.currency, locale) },
                { label: t("syncStatus"), value: <StatusBadge status={row.sync_status ?? undefined} /> },
              ]}
            />
          )}
        />
      )}
    </div>
  );
}
