"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listEquipment, type Equipment } from "@/lib/inventory";
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
import { Input, Select } from "@/components/ui/form";
import { LoadingState } from "@/components/ui/layout";

export default function EquipmentListPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const search = searchParams.get("search") || "";
  const branchId = searchParams.get("branch_id") || "";
  const status = searchParams.get("status") || "";
  const page = Number(searchParams.get("page") || 1);

  const [rows, setRows] = useState<Equipment[]>([]);
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
      const res = await listEquipment({
        page,
        per_page: 15,
        search: search || undefined,
        branch_id: branchId || undefined,
        status: status || undefined,
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
  }, [branchId, page, search, status, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  const columns: DataTableColumn<Equipment>[] = [
    {
      key: "number",
      label: t("columns.equipmentNumber"),
      render: (row) => (
        <Link href={`/inventory/equipment/${row.id}`} className="font-medium text-primary hover:underline">
          {row.equipment_number}
        </Link>
      ),
    },
    { key: "serial", label: t("columns.serial"), render: (row) => row.serial_number },
    { key: "product", label: t("columns.product"), render: (row) => row.product?.name_en || "—" },
    { key: "status", label: t("columns.status"), render: (row) => <StatusBadge status={row.status} /> },
  ];

  return (
    <div className="space-y-4" data-testid="inventory-equipment">
      <WorkspaceHeader
        title={t("equipmentTitle")}
        subtitle={t("equipmentSubtitle")}
        actions={null}
      />
      <FilterBar
        onApply={() => setParams({ search: draftSearch })}
        onClear={() => {
          setDraftSearch("");
          setParams({ search: "", branch_id: "", status: "" });
          setBranchLabel(null);
        }}
      >
        <Input
          value={draftSearch}
          onChange={(e) => setDraftSearch(e.target.value)}
          placeholder={tCommon("search")}
          aria-label={tCommon("search")}
        />
        <BranchPicker
          value={branchId || null}
          selectedLabel={branchLabel}
          onChange={(opt) => {
            setParams({ branch_id: opt ? String(opt.id) : "" });
            setBranchLabel(opt?.label ?? null);
          }}
        />
        <Select
          value={status}
          onChange={(e) => setParams({ status: e.target.value })}
          aria-label={t("columns.status")}
        >
          <option value="">{t("filters.allStatuses")}</option>
        </Select>
      </FilterBar>
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyWorkspace label={t("emptyEquipment")} /> : null}
      {!loading && !error && rows.length > 0 ? (
        <>
          <div className="hidden md:block">
            <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} />
          </div>
          <div className="space-y-3 md:hidden">
            {rows.map((row) => (
              <MobileRecordCard
                key={row.id}
                title={row.equipment_number}
                subtitle={row.serial_number}
                href={`/inventory/equipment/${row.id}`}
                badges={<StatusBadge status={row.status} />}
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
