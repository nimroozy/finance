"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { createSalesTarget, listSalesTargets, type SalesTarget } from "@/lib/crm";
import { useAuthStore } from "@/store/auth-store";
import {
  BranchPicker,
  Drawer,
  EmptyWorkspace,
  ErrorWorkspace,
  FilterBar,
  WorkspaceHeader,
} from "@/components/ops";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/form";
import { Alert, Panel } from "@/components/ui/layout";

export default function CrmTargetsPage() {
  const t = useTranslations("crm");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const canManage = useAuthStore((s) => s.hasPermission("crm.targets.manage"));
  const defaultBranch = useAuthStore((s) => s.user?.branches?.[0]);

  const branchId = searchParams.get("branch_id") || "";
  const page = Number(searchParams.get("page") || 1);

  const [rows, setRows] = useState<SalesTarget[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [createOpen, setCreateOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);
  const [branchLabel, setBranchLabel] = useState<string | null>(null);
  const [form, setForm] = useState({
    branch_id: defaultBranch ? String(defaultBranch.id) : "",
    period_year: String(new Date().getFullYear()),
    period_month: String(new Date().getMonth() + 1),
    leads_target: "",
    qualified_target: "",
    won_target: "",
    revenue_target: "",
    mrr_target: "",
  });
  const [formBranchLabel, setFormBranchLabel] = useState<string | null>(
    defaultBranch?.name_en ?? null,
  );

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
      const res = await listSalesTargets({
        page,
        per_page: 15,
        branch_id: branchId || undefined,
      });
      setRows(res.data);
      setMeta({
        current_page: res.meta?.current_page ?? page,
        last_page: res.meta?.last_page ?? 1,
      });
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [branchId, page, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onCreate(e: React.FormEvent) {
    e.preventDefault();
    if (!form.branch_id) {
      setCreateError(t("requiredFields"));
      return;
    }
    setBusy(true);
    setCreateError(null);
    try {
      await createSalesTarget({
        branch_id: Number(form.branch_id),
        period_year: Number(form.period_year),
        period_month: Number(form.period_month),
        leads_target: form.leads_target ? Number(form.leads_target) : undefined,
        qualified_target: form.qualified_target ? Number(form.qualified_target) : undefined,
        won_target: form.won_target ? Number(form.won_target) : undefined,
        revenue_target: form.revenue_target ? Number(form.revenue_target) : undefined,
        mrr_target: form.mrr_target ? Number(form.mrr_target) : undefined,
      });
      setCreateOpen(false);
      await load();
    } catch (err) {
      setCreateError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  const columns: DataTableColumn<SalesTarget>[] = [
    {
      key: "period",
      label: t("columns.period"),
      render: (row) => `${row.period_year}-${String(row.period_month).padStart(2, "0")}`,
    },
    {
      key: "branch",
      label: tCommon("branch"),
      render: (row) => row.branch_id,
    },
    {
      key: "leads",
      label: t("fields.leadsTarget"),
      render: (row) => row.leads_target ?? "—",
    },
    {
      key: "qualified",
      label: t("fields.qualifiedTarget"),
      render: (row) => row.qualified_target ?? "—",
    },
    {
      key: "won",
      label: t("fields.wonTarget"),
      render: (row) => row.won_target ?? "—",
    },
    {
      key: "revenue",
      label: t("fields.revenueTarget"),
      render: (row) => String(row.revenue_target ?? "—"),
    },
    {
      key: "mrr",
      label: t("fields.mrrTarget"),
      render: (row) => String(row.mrr_target ?? "—"),
    },
  ];

  return (
    <div className="space-y-4">
      <WorkspaceHeader
        title={t("targetsTitle")}
        subtitle={t("targetsSubtitle")}
        actions={
          canManage ? (
            <Button onClick={() => setCreateOpen(true)}>{t("newTarget")}</Button>
          ) : null
        }
      />
      <FilterBar
        onClear={() => {
          setBranchLabel(null);
          setParams({ branch_id: "" });
        }}
      >
        <BranchPicker
          value={branchId || null}
          selectedLabel={branchLabel}
          onChange={(opt) => {
            setBranchLabel(opt?.label ?? null);
            setParams({ branch_id: opt ? String(opt.id) : "" });
          }}
        />
      </FilterBar>
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && rows.length === 0 ? <EmptyWorkspace label={t("emptyTargets")} /> : null}
      <Panel>
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(r) => r.id}
          loading={loading}
          page={meta.current_page}
          lastPage={meta.last_page}
          onPageChange={(p) => setParams({ page: String(p) })}
        />
      </Panel>
      <Drawer open={createOpen} title={t("newTarget")} onClose={() => setCreateOpen(false)}>
        <form className="space-y-3" onSubmit={(e) => void onCreate(e)}>
          {createError ? <Alert tone="danger">{createError}</Alert> : null}
          <BranchPicker
            value={form.branch_id || null}
            selectedLabel={formBranchLabel}
            onChange={(opt) => {
              setFormBranchLabel(opt?.label ?? null);
              setForm((f) => ({ ...f, branch_id: opt ? String(opt.id) : "" }));
            }}
          />
          <Input
            type="number"
            placeholder={t("fields.periodYear")}
            value={form.period_year}
            onChange={(e) => setForm((f) => ({ ...f, period_year: e.target.value }))}
            required
          />
          <Input
            type="number"
            placeholder={t("fields.periodMonth")}
            value={form.period_month}
            onChange={(e) => setForm((f) => ({ ...f, period_month: e.target.value }))}
            min={1}
            max={12}
            required
          />
          <Input
            type="number"
            placeholder={t("fields.leadsTarget")}
            value={form.leads_target}
            onChange={(e) => setForm((f) => ({ ...f, leads_target: e.target.value }))}
          />
          <Input
            type="number"
            placeholder={t("fields.qualifiedTarget")}
            value={form.qualified_target}
            onChange={(e) => setForm((f) => ({ ...f, qualified_target: e.target.value }))}
          />
          <Input
            type="number"
            placeholder={t("fields.wonTarget")}
            value={form.won_target}
            onChange={(e) => setForm((f) => ({ ...f, won_target: e.target.value }))}
          />
          <Input
            type="number"
            placeholder={t("fields.revenueTarget")}
            value={form.revenue_target}
            onChange={(e) => setForm((f) => ({ ...f, revenue_target: e.target.value }))}
          />
          <Input
            type="number"
            placeholder={t("fields.mrrTarget")}
            value={form.mrr_target}
            onChange={(e) => setForm((f) => ({ ...f, mrr_target: e.target.value }))}
          />
          <Button type="submit" disabled={busy}>
            {busy ? tCommon("loading") : tCommon("save")}
          </Button>
        </form>
      </Drawer>
    </div>
  );
}
