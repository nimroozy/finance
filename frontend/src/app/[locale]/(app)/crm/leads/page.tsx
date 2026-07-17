"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { PIPELINE_STAGES, listLeads, type Lead } from "@/lib/crm";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import {
  BranchPicker,
  EmptyWorkspace,
  ErrorWorkspace,
  FilterBar,
  MobileRecordCard,
  SavedViewSelector,
  WorkspaceHeader,
  type SavedView,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { Input, Select } from "@/components/ui/form";
import { Panel } from "@/components/ui/layout";

const STAGE_VIEWS: Array<{ id: string; stage?: string }> = [
  { id: "all" },
  { id: "lead", stage: "lead" },
  { id: "contacted", stage: "contacted" },
  { id: "qualified", stage: "qualified" },
  { id: "quotation", stage: "quotation" },
  { id: "approved", stage: "approved" },
  { id: "lost", stage: "lost" },
];

export default function CrmLeadsPage() {
  const t = useTranslations("crm");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const canCreate = useAuthStore((s) => s.hasPermission("crm.leads.create"));

  const view = searchParams.get("view") || "all";
  const stage = searchParams.get("pipeline_stage") || "";
  const search = searchParams.get("search") || "";
  const branchId = searchParams.get("branch_id") || "";
  const page = Number(searchParams.get("page") || 1);

  const [rows, setRows] = useState<Lead[]>([]);
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
      const res = await listLeads({
        page,
        per_page: 15,
        search: search || undefined,
        branch_id: branchId || undefined,
        pipeline_stage: stage || undefined,
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
  }, [branchId, page, search, stage, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    setDraftSearch(search);
  }, [search]);

  const views: SavedView[] = STAGE_VIEWS.map((v) => ({
    id: v.id,
    label: t(`views.${v.id}` as "views.all"),
  }));

  const columns: DataTableColumn<Lead>[] = [
    {
      key: "lead_number",
      label: t("columns.leadNumber"),
      render: (row) => (
        <Link href={`/crm/leads/${row.id}`} className="font-medium text-primary hover:underline">
          {row.lead_number}
        </Link>
      ),
    },
    {
      key: "contact",
      label: t("columns.contact"),
      render: (row) => (
        <div>
          <div>{row.contact_person || row.company || "—"}</div>
          <div className="text-xs text-muted">{row.phone || ""}</div>
        </div>
      ),
    },
    {
      key: "stage",
      label: t("columns.stage"),
      render: (row) => <StatusBadge status={row.pipeline_stage} />,
    },
    {
      key: "owner",
      label: t("columns.owner"),
      render: (row) => row.owner?.name || row.salesperson?.name || "—",
    },
    {
      key: "value",
      label: t("columns.value"),
      render: (row) => String(row.opportunity_value ?? row.lead_value ?? "—"),
    },
    {
      key: "updated",
      label: t("columns.updated"),
      render: (row) => (row.updated_at ? formatDateTime(row.updated_at, locale) : "—"),
    },
  ];

  return (
    <div className="space-y-4">
      <WorkspaceHeader
        title={t("leadsTitle")}
        subtitle={t("leadsSubtitle")}
        actions={
          canCreate ? (
            <Link href="/crm/leads/new">
              <Button>{t("newLead")}</Button>
            </Link>
          ) : null
        }
        meta={<span>{t("totalCount", { count: meta.total })}</span>}
      />

      <SavedViewSelector
        views={views}
        value={view}
        onChange={(id) => {
          const match = STAGE_VIEWS.find((v) => v.id === id);
          setParams({
            view: id,
            pipeline_stage: match?.stage || "",
          });
        }}
      />

      <FilterBar
        onApply={() => setParams({ search: draftSearch })}
        onClear={() => {
          setDraftSearch("");
          setBranchLabel(null);
          setParams({ search: "", branch_id: "", pipeline_stage: "", view: "all" });
        }}
      >
        <Input
          value={draftSearch}
          onChange={(e) => setDraftSearch(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter") setParams({ search: draftSearch });
          }}
          placeholder={t("searchPlaceholder")}
        />
        <BranchPicker
          value={branchId || null}
          selectedLabel={branchLabel}
          onChange={(opt) => {
            setBranchLabel(opt?.label ?? null);
            setParams({ branch_id: opt ? String(opt.id) : "" });
          }}
        />
        <Select
          value={stage}
          onChange={(e) =>
            setParams({
              pipeline_stage: e.target.value,
              view: e.target.value || "all",
            })
          }
        >
          <option value="">{t("allStages")}</option>
          {PIPELINE_STAGES.map((s) => (
            <option key={s} value={s}>
              {t(`stages.${s}` as "stages.lead")}
            </option>
          ))}
        </Select>
      </FilterBar>

      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? (
        <EmptyWorkspace
          label={t("emptyLeads")}
          action={
            canCreate ? (
              <Link href="/crm/leads/new">
                <Button>{t("newLead")}</Button>
              </Link>
            ) : undefined
          }
        />
      ) : (
        <Panel>
          <DataTable
            columns={columns}
            rows={rows}
            rowKey={(r) => r.id}
            loading={loading}
            page={meta.current_page}
            lastPage={meta.last_page}
            onPageChange={(p) => setParams({ page: String(p) })}
            mobileCard={(row) => (
              <MobileRecordCard
                href={`/crm/leads/${row.id}`}
                title={row.lead_number}
                subtitle={row.contact_person || row.company || undefined}
                badges={<StatusBadge status={row.pipeline_stage} />}
                meta={
                  <>
                    {row.phone || "—"} · {row.owner?.name || "—"}
                  </>
                }
              />
            )}
          />
        </Panel>
      )}
    </div>
  );
}
