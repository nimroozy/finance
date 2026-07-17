"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listPurchaseOrders, type PurchaseOrder } from "@/lib/inventory";
import {
  BranchPicker,
  EmptyWorkspace,
  ErrorWorkspace,
  FilterBar,
  MobileRecordCard,
  WorkspaceHeader,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { LoadingState } from "@/components/ui/layout";

export default function PurchaseOrdersPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const search = searchParams.get("search") || "";
  const branchId = searchParams.get("branch_id") || "";
  const pageNum = Number(searchParams.get("page") || 1);

  const [rows, setRows] = useState<PurchaseOrder[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [draftSearch, setDraftSearch] = useState(search);
  const [branchLabel, setBranchLabel] = useState<string | null>(null);

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
      const res = await listPurchaseOrders({
        page: pageNum,
        per_page: 15,
        search: search || undefined,
        branch_id: branchId || undefined,
        }
      );
      setRows(res.data);
      setMeta({
        current_page: res.meta?.current_page ?? pageNum,
        last_page: res.meta?.last_page ?? 1,
        total: res.meta?.total ?? res.data.length,
      });
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [branchId, pageNum, search, tCommon]);

  useEffect(() => { void load(); }, [load]);

  const columns: DataTableColumn<PurchaseOrder>[] = [
    { key: "number", label: t("columns.poNumber"), render: (row) => row.po_number || `#${row.id}` },
    { key: "supplier", label: t("columns.supplier"), render: (row) => row.supplier?.name || String(row.supplier_id) },
    { key: "status", label: t("columns.status"), render: (row) => <StatusBadge status={row.status} /> },
  ];

  return (
    <div className="space-y-4" data-testid="purchase-orders">
      <WorkspaceHeader title={t("purchaseOrdersTitle")} subtitle={t("purchaseOrdersSubtitle")} />
      <FilterBar
        onApply={() => setParams({ search: draftSearch })}
        onClear={() => { setDraftSearch(""); setParams({ search: "", branch_id: "", status: "" }); setBranchLabel(null); }}
      >
        <BranchPicker
          value={branchId || null}
          selectedLabel={branchLabel}
          onChange={(opt) => {
            setParams({ branch_id: opt ? String(opt.id) : "" });
            setBranchLabel(opt?.label ?? null);
          }}
        />
      </FilterBar>
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyWorkspace label={t("emptyPurchaseOrders")} /> : null}
      {!loading && !error && rows.length > 0 ? (
        <>
          <div className="hidden md:block">
            <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} />
          </div>
          <div className="space-y-3 md:hidden">
            {rows.map((row) => (
              <MobileRecordCard key={row.id}
                title={row.po_number || `#${row.id}`}
                subtitle={row.supplier?.name}
                badges={<StatusBadge status={row.status} />}
              />
            ))}
          </div>
          <div className="flex items-center justify-between gap-2">
            <p className="text-sm text-muted">{tCommon("page", { page: meta.current_page, total: meta.last_page })}</p>
            <div className="flex gap-2">
              <Button variant="secondary" disabled={meta.current_page <= 1} onClick={() => setParams({ page: String(meta.current_page - 1) })}>{tCommon("previous")}</Button>
              <Button variant="secondary" disabled={meta.current_page >= meta.last_page} onClick={() => setParams({ page: String(meta.current_page + 1) })}>{tCommon("next")}</Button>
            </div>
          </div>
        </>
      ) : null}
    </div>
  );
}
