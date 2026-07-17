"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  createTransfer,
  listLocations,
  listProducts,
  listTransfers,
  type InventoryLocation,
  type Product,
  type Transfer,
} from "@/lib/inventory";
import { useAuthStore } from "@/store/auth-store";
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
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

export default function TransfersListPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const canManage = useAuthStore((s) =>
    s.hasAnyPermission(["inventory.transfers.manage", "inventory.stock.transfer"]),
  );
  const defaultBranch = useAuthStore((s) => s.user?.branches?.[0]);

  const search = searchParams.get("search") || "";
  const branchId = searchParams.get("branch_id") || "";
  const status = searchParams.get("status") || "";
  const page = Number(searchParams.get("page") || 1);

  const [rows, setRows] = useState<Transfer[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [draftSearch, setDraftSearch] = useState(search);
  const [branchLabel, setBranchLabel] = useState<string | null>(null);
  const [showCreate, setShowCreate] = useState(false);
  const [locations, setLocations] = useState<InventoryLocation[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [createBranch, setCreateBranch] = useState(defaultBranch ? String(defaultBranch.id) : "");
  const [createBranchLabel, setCreateBranchLabel] = useState<string | null>(
    defaultBranch
      ? (locale === "fa" ? defaultBranch.name_fa : defaultBranch.name_en) || defaultBranch.name_en
      : null,
  );
  const [fromLoc, setFromLoc] = useState("");
  const [toLoc, setToLoc] = useState("");
  const [createProduct, setCreateProduct] = useState("");
  const [createQty, setCreateQty] = useState("1");
  const [createBusy, setCreateBusy] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);

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
      const res = await listTransfers({
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

  useEffect(() => {
    if (!showCreate) return;
    void listLocations({ per_page: 100, branch_id: createBranch || undefined })
      .then((res) => setLocations(res.data))
      .catch(() => setLocations([]));
    void listProducts({ per_page: 100 })
      .then((res) => setProducts(res.data))
      .catch(() => setProducts([]));
  }, [showCreate, createBranch]);

  async function onCreate() {
    if (!canManage || createBusy) return;
    if (!createBranch || !fromLoc || !toLoc || !createProduct) {
      setCreateError(t("requiredFields"));
      return;
    }
    setCreateBusy(true);
    setCreateError(null);
    try {
      const res = await createTransfer({
        branch_id: Number(createBranch),
        from_location_id: Number(fromLoc),
        to_location_id: Number(toLoc),
        items: [{ product_id: Number(createProduct), qty: Number(createQty) || 1 }],
      });
      setShowCreate(false);
      router.push(`/inventory/transfers/${res.data.id}`);
    } catch (err) {
      setCreateError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setCreateBusy(false);
    }
  }

  const columns: DataTableColumn<Transfer>[] = [
    {
      key: "number",
      label: t("columns.transferNumber"),
      render: (row) => (
        <Link href={`/inventory/transfers/${row.id}`} className="font-medium text-primary hover:underline">
          {row.transfer_number}
        </Link>
      ),
    },
    { key: "status", label: t("columns.status"), render: (row) => <StatusBadge status={row.status} /> },
    { key: "from", label: t("columns.from"), render: (row) => String(row.from_location_id) },
    { key: "to", label: t("columns.to"), render: (row) => String(row.to_location_id) },
  ];

  return (
    <div className="space-y-4" data-testid="inventory-transfers">
      <WorkspaceHeader
        title={t("transfersTitle")}
        subtitle={t("transfersSubtitle")}
        actions={
          canManage ? (
            <Button data-testid="new-transfer" onClick={() => setShowCreate((v) => !v)}>
              {t("newTransfer")}
            </Button>
          ) : null
        }
      />
      {showCreate ? (
        <div data-testid="create-transfer-form"><Panel className="space-y-3 p-4">
          {createError ? <Alert tone="danger">{createError}</Alert> : null}
          <div className="grid gap-3 sm:grid-cols-2">
            <BranchPicker
              value={createBranch || null}
              selectedLabel={createBranchLabel}
              onChange={(opt) => {
                setCreateBranch(opt ? String(opt.id) : "");
                setCreateBranchLabel(opt?.label ?? null);
              }}
            />
            <Select value={fromLoc} onChange={(e) => setFromLoc(e.target.value)} data-testid="transfer-from">
              <option value="">{t("fields.fromLocation")}</option>
              {locations.map((loc) => (
                <option key={loc.id} value={loc.id}>{loc.name}</option>
              ))}
            </Select>
            <Select value={toLoc} onChange={(e) => setToLoc(e.target.value)} data-testid="transfer-to">
              <option value="">{t("fields.toLocation")}</option>
              {locations.map((loc) => (
                <option key={loc.id} value={loc.id}>{loc.name}</option>
              ))}
            </Select>
            <Select value={createProduct} onChange={(e) => setCreateProduct(e.target.value)} data-testid="transfer-product">
              <option value="">{t("fields.selectProduct")}</option>
              {products.map((p) => (
                <option key={p.id} value={p.id}>{p.name_en}</option>
              ))}
            </Select>
            <Input type="number" value={createQty} onChange={(e) => setCreateQty(e.target.value)} data-testid="transfer-qty" />
          </div>
          <Button data-testid="save-transfer" disabled={createBusy} onClick={() => void onCreate()}>
            {tCommon("create")}
          </Button>
        </Panel></div>
      ) : null}
      <FilterBar
        onApply={() => setParams({ search: draftSearch })}
        onClear={() => {
          setDraftSearch("");
          setParams({ search: "", branch_id: "", status: "" });
          setBranchLabel(null);
        }}
      >
        <Input value={draftSearch} onChange={(e) => setDraftSearch(e.target.value)} placeholder={tCommon("search")} aria-label={tCommon("search")} />
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
      {!loading && !error && rows.length === 0 ? <EmptyWorkspace label={t("emptyTransfers")} /> : null}
      {!loading && !error && rows.length > 0 ? (
        <>
          <div className="hidden md:block">
            <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} />
          </div>
          <div className="space-y-3 md:hidden">
            {rows.map((row) => (
              <MobileRecordCard
                key={row.id}
                title={row.transfer_number}
                subtitle={`${row.from_location_id} → ${row.to_location_id}`}
                href={`/inventory/transfers/${row.id}`}
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
