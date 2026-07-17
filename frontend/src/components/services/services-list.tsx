"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  BILLING_STATUSES,
  COMMERCIAL_STATUSES,
  OPERATIONAL_STATUSES,
  listServices,
  type IspService,
} from "@/lib/services";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  FilterBar,
  MobileRecordCard,
  WorkspaceHeader,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { Input, Select } from "@/components/ui/form";
import { LoadingState } from "@/components/ui/layout";

export type ServicesListFilters = {
  commercial_status?: string;
  operational_status?: string;
  billing_status?: string;
};

export function ServicesListWorkspace({
  title,
  subtitle,
  fixedFilters = {},
  showStatusFilters = true,
  createHref,
  testId = "services-list",
}: {
  title: string;
  subtitle?: string;
  fixedFilters?: ServicesListFilters;
  showStatusFilters?: boolean;
  createHref?: string;
  testId?: string;
}) {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const search = searchParams.get("q") || "";
  const commercial =
    fixedFilters.commercial_status || searchParams.get("commercial_status") || "";
  const operational =
    fixedFilters.operational_status || searchParams.get("operational_status") || "";
  const billing =
    fixedFilters.billing_status || searchParams.get("billing_status") || "";
  const page = Number(searchParams.get("page") || 1);

  const [rows, setRows] = useState<IspService[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [draftSearch, setDraftSearch] = useState(search);

  const setParams = useCallback(
    (patch: Record<string, string>) => {
      const params = new URLSearchParams(searchParams.toString());
      for (const [k, v] of Object.entries(patch)) {
        if (!v) params.delete(k);
        else params.set(k, v);
      }
      if (!("page" in patch)) params.delete("page");
      const qs = params.toString();
      router.replace(qs ? `${pathname}?${qs}` : pathname);
    },
    [pathname, router, searchParams],
  );

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listServices({
        page,
        per_page: 15,
        q: search || undefined,
        commercial_status: commercial || undefined,
        operational_status: operational || undefined,
        billing_status: billing || undefined,
      });
      setRows(res.data);
      setMeta({
        current_page: res.meta?.current_page ?? page,
        last_page: res.meta?.last_page ?? 1,
        total: res.meta?.total ?? res.data.length,
      });
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [billing, commercial, operational, page, search, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  const columns: DataTableColumn<IspService>[] = [
    {
      key: "number",
      label: t("columns.serviceNumber"),
      render: (row) => (
        <Link href={`/services/${row.id}`} className="font-medium text-primary hover:underline">
          {row.service_number}
        </Link>
      ),
    },
    {
      key: "customer",
      label: t("columns.customer"),
      render: (row) =>
        row.customer?.contact_name ||
        row.customer?.customer_number ||
        String(row.customer_id),
    },
    {
      key: "package",
      label: t("columns.package"),
      render: (row) => row.package?.name || row.package?.code || "—",
    },
    {
      key: "commercial",
      label: t("columns.commercial"),
      render: (row) => <StatusBadge status={row.commercial_status} />,
    },
    {
      key: "operational",
      label: t("columns.operational"),
      render: (row) => <StatusBadge status={row.operational_status} />,
    },
    {
      key: "billing",
      label: t("columns.billing"),
      render: (row) => <StatusBadge status={row.billing_status} />,
    },
    {
      key: "mrr",
      label: t("columns.mrr"),
      render: (row) => String(row.mrr ?? "—"),
    },
  ];

  return (
    <div className="space-y-4" data-testid={testId}>
      <WorkspaceHeader
        title={title}
        subtitle={subtitle}
        actions={
          createHref ? (
            <Link href={createHref}>
              <Button data-testid="new-service">{t("newService")}</Button>
            </Link>
          ) : null
        }
      />
      <FilterBar
        onApply={() => setParams({ q: draftSearch })}
        onClear={() => {
          setDraftSearch("");
          setParams({
            q: "",
            ...(fixedFilters.commercial_status ? {} : { commercial_status: "" }),
            ...(fixedFilters.operational_status ? {} : { operational_status: "" }),
            ...(fixedFilters.billing_status ? {} : { billing_status: "" }),
          });
        }}
      >
        <Input
          value={draftSearch}
          onChange={(e) => setDraftSearch(e.target.value)}
          placeholder={tCommon("search")}
          aria-label={tCommon("search")}
          data-testid="services-search"
        />
        {showStatusFilters && !fixedFilters.commercial_status ? (
          <Select
            value={commercial}
            onChange={(e) => setParams({ commercial_status: e.target.value })}
            aria-label={t("columns.commercial")}
          >
            <option value="">{t("filters.allCommercial")}</option>
            {COMMERCIAL_STATUSES.map((s) => (
              <option key={s} value={s}>
                {t(`commercial.${s}` as "commercial.active")}
              </option>
            ))}
          </Select>
        ) : null}
        {showStatusFilters && !fixedFilters.operational_status ? (
          <Select
            value={operational}
            onChange={(e) => setParams({ operational_status: e.target.value })}
            aria-label={t("columns.operational")}
          >
            <option value="">{t("filters.allOperational")}</option>
            {OPERATIONAL_STATUSES.map((s) => (
              <option key={s} value={s}>
                {t(`operational.${s}` as "operational.online")}
              </option>
            ))}
          </Select>
        ) : null}
        {showStatusFilters && !fixedFilters.billing_status ? (
          <Select
            value={billing}
            onChange={(e) => setParams({ billing_status: e.target.value })}
            aria-label={t("columns.billing")}
          >
            <option value="">{t("filters.allBilling")}</option>
            {BILLING_STATUSES.map((s) => (
              <option key={s} value={s}>
                {t(`billing.${s}` as "billing.current")}
              </option>
            ))}
          </Select>
        ) : null}
      </FilterBar>
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyWorkspace label={t("emptyServices")} /> : null}
      {!loading && !error && rows.length > 0 ? (
        <>
          <div className="hidden md:block">
            <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} />
          </div>
          <div className="space-y-3 md:hidden">
            {rows.map((row) => (
              <MobileRecordCard
                key={row.id}
                href={`/services/${row.id}`}
                title={row.service_number}
                subtitle={row.customer?.contact_name || String(row.customer_id)}
                badges={
                  <>
                    <StatusBadge status={row.commercial_status} />
                    <StatusBadge status={row.operational_status} />
                  </>
                }
                meta={row.package?.name || undefined}
              />
            ))}
          </div>
          {meta.last_page > 1 ? (
            <div className="flex items-center justify-between gap-2">
              <Button
                variant="secondary"
                disabled={meta.current_page <= 1}
                onClick={() => setParams({ page: String(meta.current_page - 1) })}
              >
                {tCommon("previous")}
              </Button>
              <span className="text-sm text-muted">
                {meta.current_page} / {meta.last_page}
              </span>
              <Button
                variant="secondary"
                disabled={meta.current_page >= meta.last_page}
                onClick={() => setParams({ page: String(meta.current_page + 1) })}
              >
                {tCommon("next")}
              </Button>
            </div>
          ) : null}
        </>
      ) : null}
    </div>
  );
}
