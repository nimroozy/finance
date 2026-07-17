"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { TRACKING_MODES, listProducts, type Product } from "@/lib/inventory";
import { useAuthStore } from "@/store/auth-store";
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

export default function InventoryProductsPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const canManage = useAuthStore((s) => s.hasPermission("inventory.products.manage"));

  const search = searchParams.get("search") || "";
  const tracking = searchParams.get("tracking_mode") || "";
  const page = Number(searchParams.get("page") || 1);

  const [rows, setRows] = useState<Product[]>([]);
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
      const res = await listProducts({
        page,
        per_page: 15,
        search: search || undefined,
        tracking_mode: tracking || undefined,
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
  }, [page, search, tCommon, tracking]);

  useEffect(() => {
    void load();
  }, [load]);

  const columns: DataTableColumn<Product>[] = [
    {
      key: "code",
      label: t("columns.code"),
      render: (row) => (
        <Link href={`/inventory/products/${row.id}`} className="font-medium text-primary hover:underline">
          {row.code}
        </Link>
      ),
    },
    { key: "name", label: t("columns.name"), render: (row) => row.name_en },
    {
      key: "tracking",
      label: t("columns.tracking"),
      render: (row) => t(`tracking.${row.tracking_mode}` as "tracking.quantity"),
    },
    {
      key: "active",
      label: t("columns.status"),
      render: (row) => <StatusBadge status={row.is_active === false ? "inactive" : "active"} />,
    },
  ];

  return (
    <div className="space-y-4" data-testid="inventory-products">
      <WorkspaceHeader
        title={t("productsTitle")}
        subtitle={t("productsSubtitle")}
        actions={
          canManage ? (
            <Link href="/inventory/products/new">
              <Button data-testid="new-product">{t("newProduct")}</Button>
            </Link>
          ) : null
        }
      />
      <FilterBar
        onApply={() => setParams({ search: draftSearch })}
        onClear={() => {
          setDraftSearch("");
          setParams({ search: "", tracking_mode: "" });
        }}
      >
        <Input
          value={draftSearch}
          onChange={(e) => setDraftSearch(e.target.value)}
          placeholder={tCommon("search")}
          aria-label={tCommon("search")}
        />
        <Select
          value={tracking}
          onChange={(e) => setParams({ tracking_mode: e.target.value })}
          aria-label={t("columns.tracking")}
        >
          <option value="">{t("filters.allTracking")}</option>
          {TRACKING_MODES.map((m) => (
            <option key={m} value={m}>
              {t(`tracking.${m}` as "tracking.quantity")}
            </option>
          ))}
        </Select>
      </FilterBar>
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyWorkspace label={t("emptyProducts")} /> : null}
      {!loading && !error && rows.length > 0 ? (
        <>
          <div className="hidden md:block">
            <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} />
          </div>
          <div className="space-y-3 md:hidden">
            {rows.map((row) => (
              <MobileRecordCard
                key={row.id}
                title={row.name_en}
                subtitle={row.code}
                href={`/inventory/products/${row.id}`}
                meta={t(`tracking.${row.tracking_mode}` as "tracking.quantity")}
              />
            ))}
          </div>
          <div className="flex items-center justify-between gap-2">
            <p className="text-sm text-muted">
              {tCommon("page", { page: meta.current_page, total: meta.last_page })}
            </p>
            <div className="flex gap-2">
              <Button
                variant="secondary"
                disabled={meta.current_page <= 1}
                onClick={() => setParams({ page: String(meta.current_page - 1) })}
              >
                {tCommon("previous")}
              </Button>
              <Button
                variant="secondary"
                disabled={meta.current_page >= meta.last_page}
                onClick={() => setParams({ page: String(meta.current_page + 1) })}
              >
                {tCommon("next")}
              </Button>
            </div>
          </div>
        </>
      ) : null}
    </div>
  );
}
