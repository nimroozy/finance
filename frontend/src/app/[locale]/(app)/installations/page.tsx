"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  INSTALLATION_STATUSES,
  createInstallation,
  listInstallations,
  type Installation,
} from "@/lib/installations";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import {
  BranchPicker,
  CustomerPicker,
  Drawer,
  EmptyWorkspace,
  ErrorWorkspace,
  FilterBar,
  MobileRecordCard,
  SavedViewSelector,
  WorkspaceHeader,
  type SavedView,
} from "@/components/ops";
import type { PickerOption } from "@/lib/pickers";
import { StatusBadge } from "@/components/status-badge";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { Input, Select, TextArea } from "@/components/ui/form";
import { Alert, Panel } from "@/components/ui/layout";

const STAGE_VIEWS: Array<{ id: string; status?: string }> = [
  { id: "all" },
  { id: "request_received", status: "request_received" },
  { id: "finance_review", status: "finance_review" },
  { id: "equipment_waiting", status: "equipment_waiting" },
  { id: "scheduled", status: "scheduled" },
  { id: "installing", status: "installing" },
  { id: "noc_activation_pending", status: "noc_activation_pending" },
  { id: "customer_confirmation", status: "customer_confirmation" },
  { id: "completed", status: "completed" },
];

export default function InstallationsPage() {
  const t = useTranslations("installations");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const canCreate = useAuthStore((s) => s.hasPermission("installations.create"));
  const defaultBranch = useAuthStore((s) => s.user?.branches?.[0]);

  const view = searchParams.get("view") || "all";
  const status = searchParams.get("status") || "";
  const search = searchParams.get("search") || "";
  const branchId = searchParams.get("branch_id") || "";
  const page = Number(searchParams.get("page") || 1);

  const [rows, setRows] = useState<Installation[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [draftSearch, setDraftSearch] = useState(search);
  const [createOpen, setCreateOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);
  const [branchLabel, setBranchLabel] = useState<string | null>(null);
  const [form, setForm] = useState({
    branch_id: defaultBranch ? String(defaultBranch.id) : "",
    contact_name: "",
    phone: "",
    address: "",
    requested_package: "",
    notes: "",
    customer: null as PickerOption | null,
  });

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
      const res = await listInstallations({
        page,
        status: status || undefined,
        search: search || undefined,
        branch_id: branchId || undefined,
      });
      setRows(res.data);
      setMeta({
        current_page: res.meta?.current_page ?? page,
        last_page: res.meta?.last_page ?? 1,
      });
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [branchId, page, search, status, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  const views: SavedView[] = STAGE_VIEWS.map((v) => ({
    id: v.id,
    label: v.id === "all" ? t("viewAll") : t(`status_${v.id}` as "status_request_received"),
  }));

  const columns: DataTableColumn<Installation>[] = [
    {
      key: "number",
      label: t("number"),
      render: (row) => (
        <Link href={`/installations/${row.id}`} className="font-medium text-primary hover:underline">
          {row.installation_number}
        </Link>
      ),
    },
    {
      key: "contact",
      label: t("contact"),
      render: (row) => row.contact_name || row.prospect_name || row.phone || "—",
    },
    {
      key: "status",
      label: t("status"),
      render: (row) => <StatusBadge status={row.status} />,
    },
    { key: "package", label: t("package"), render: (row) => row.requested_package || "—" },
    {
      key: "equipment",
      label: t("equipmentConfirm"),
      render: (row) =>
        row.equipment_confirmed || row.equipment_status === "confirmed"
          ? t("confirmed")
          : t("pendingConfirm"),
    },
    {
      key: "created",
      label: t("createdAt"),
      render: (row) => formatDateTime(row.created_at, locale),
    },
  ];

  async function onCreate(e: React.FormEvent) {
    e.preventDefault();
    if (!form.branch_id) {
      setCreateError(t("requiredBranch"));
      return;
    }
    setBusy(true);
    setCreateError(null);
    try {
      const res = await createInstallation({
        branch_id: Number(form.branch_id),
        customer_id: form.customer ? Number(form.customer.id) : undefined,
        contact_name: form.contact_name || undefined,
        phone: form.phone || undefined,
        address: form.address || undefined,
        requested_package: form.requested_package || undefined,
        notes: form.notes || undefined,
      });
      setCreateOpen(false);
      router.push(`/installations/${res.data.id}`);
    } catch (err) {
      setCreateError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-4">
      <WorkspaceHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          canCreate ? (
            <Button onClick={() => setCreateOpen(true)}>{t("create")}</Button>
          ) : null
        }
      />

      <SavedViewSelector
        views={views}
        value={view}
        onChange={(id) => {
          const stage = STAGE_VIEWS.find((v) => v.id === id);
          setParams({ view: id, status: stage?.status || "" });
        }}
      />

      <FilterBar
        loading={loading}
        onApply={() => setParams({ search: draftSearch })}
        onClear={() => router.replace(pathname)}
      >
        <Input
          type="search"
          value={draftSearch}
          onChange={(e) => setDraftSearch(e.target.value)}
          placeholder={t("searchPlaceholder")}
        />
        <Select
          value={status}
          onChange={(e) => setParams({ status: e.target.value, view: "all" })}
        >
          <option value="">{t("status")}</option>
          {INSTALLATION_STATUSES.map((s) => (
            <option key={s} value={s}>
              {t(`status_${s}` as "status_request_received")}
            </option>
          ))}
        </Select>
      </FilterBar>

      {error ? (
        <ErrorWorkspace message={error} onRetry={() => void load()} />
      ) : !loading && rows.length === 0 ? (
        <EmptyWorkspace
          action={
            canCreate ? (
              <Button onClick={() => setCreateOpen(true)}>{t("create")}</Button>
            ) : null
          }
        />
      ) : (
        <Panel className="overflow-hidden p-0">
          <DataTable
            rows={rows}
            columns={columns}
            rowKey={(r) => r.id}
            loading={loading}
            page={meta.current_page}
            lastPage={meta.last_page}
            onPageChange={(p) => setParams({ page: String(p) })}
            mobileCard={(row) => (
              <MobileRecordCard
                href={`/installations/${row.id}`}
                title={row.installation_number}
                subtitle={row.contact_name || row.prospect_name || "—"}
                badges={<StatusBadge status={row.status} />}
              />
            )}
          />
        </Panel>
      )}

      <Drawer open={createOpen} title={t("create")} onClose={() => setCreateOpen(false)}>
        <form className="space-y-3" onSubmit={(e) => void onCreate(e)}>
          {createError ? <Alert>{createError}</Alert> : null}
          <BranchPicker
            value={form.branch_id || null}
            selectedLabel={branchLabel}
            onChange={(opt) => {
              setBranchLabel(opt?.label ?? null);
              setForm((f) => ({ ...f, branch_id: opt ? String(opt.id) : "" }));
            }}
          />
          <CustomerPicker
            branchId={form.branch_id || undefined}
            value={form.customer?.id ?? null}
            selectedLabel={form.customer?.label}
            onChange={(opt) => setForm((f) => ({ ...f, customer: opt }))}
          />
          <Input
            placeholder={t("contact")}
            value={form.contact_name}
            onChange={(e) => setForm((f) => ({ ...f, contact_name: e.target.value }))}
          />
          <Input
            placeholder={t("phone")}
            value={form.phone}
            onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
          />
          <Input
            placeholder={t("package")}
            value={form.requested_package}
            onChange={(e) => setForm((f) => ({ ...f, requested_package: e.target.value }))}
          />
          <TextArea
            placeholder={t("address")}
            value={form.address}
            onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))}
            rows={2}
          />
          <Button type="submit" disabled={busy}>
            {busy ? tCommon("loading") : tCommon("create")}
          </Button>
        </form>
      </Drawer>
    </div>
  );
}
