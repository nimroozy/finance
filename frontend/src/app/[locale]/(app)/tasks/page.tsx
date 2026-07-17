"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { TASK_STATUSES, listTasks, type Task } from "@/lib/tasks";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import {
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
import { Input, Select } from "@/components/ui/form";
import { Panel } from "@/components/ui/layout";

const FILTER_KEYS = [
  "view", "search", "status", "type", "page", "per_page",
  "my", "unassigned", "overdue", "today",
] as const;
type FilterKey = (typeof FILTER_KEYS)[number];

export default function TasksPage() {
  const t = useTranslations("tasks");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const userId = useAuthStore((s) => s.user?.id);

  const filters = useMemo(() => {
    const map = {} as Record<FilterKey, string>;
    for (const key of FILTER_KEYS) map[key] = searchParams.get(key) ?? "";
    if (!map.view) map.view = "team";
    if (!map.per_page) map.per_page = "15";
    return map;
  }, [searchParams]);

  const [rows, setRows] = useState<Task[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [draftSearch, setDraftSearch] = useState(filters.search);

  const setParams = useCallback(
    (patch: Partial<Record<FilterKey, string>>, resetPage = true) => {
      const params = new URLSearchParams(searchParams.toString());
      for (const [key, value] of Object.entries(patch)) {
        if (!value) params.delete(key);
        else params.set(key, value);
      }
      if (resetPage && !("page" in patch)) params.delete("page");
      const qs = params.toString();
      router.replace(qs ? `${pathname}?${qs}` : pathname);
    },
    [pathname, router, searchParams],
  );

  const applyView = useCallback(
    (viewId: string) => {
      const base: Partial<Record<FilterKey, string>> = {
        view: viewId,
        status: "",
        type: "",
        my: "",
        unassigned: "",
        overdue: "",
        today: "",
        page: "",
      };
      switch (viewId) {
        case "my":
          base.my = "1";
          if (userId) {
            /* listTasks my=1 uses auth user */
          }
          break;
        case "unassigned":
          base.unassigned = "1";
          break;
        case "today":
          base.today = "1";
          break;
        case "overdue":
          base.overdue = "1";
          break;
        case "field":
          base.type = "field";
          break;
        case "office":
          base.type = "office";
          break;
        case "completed":
          base.status = "completed";
          break;
        default:
          break;
      }
      setParams(base);
    },
    [setParams, userId],
  );

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const page = Number(filters.page || 1);
      const res = await listTasks({
        page,
        per_page: Number(filters.per_page || 15),
        search: filters.search || undefined,
        status: filters.status || undefined,
        type: filters.type || undefined,
        my: filters.my || undefined,
        unassigned: filters.unassigned || undefined,
        overdue: filters.overdue || undefined,
        today: filters.today || undefined,
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
  }, [filters, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  const views: SavedView[] = [
    { id: "my", label: t("viewMy") },
    { id: "team", label: t("viewTeam") },
    { id: "unassigned", label: t("viewUnassigned") },
    { id: "today", label: t("viewToday") },
    { id: "overdue", label: t("viewOverdue") },
    { id: "field", label: t("viewField") },
    { id: "office", label: t("viewOffice") },
    { id: "completed", label: t("viewCompleted") },
  ];

  const columns: DataTableColumn<Task>[] = [
    {
      key: "number",
      label: t("number"),
      render: (row) => (
        <Link href={`/tasks/${row.id}`} className="font-medium text-primary hover:underline">
          {row.task_number}
        </Link>
      ),
    },
    { key: "title", label: t("titleLabel"), render: (row) => row.title },
    {
      key: "status",
      label: t("status"),
      render: (row) => <StatusBadge status={row.status} />,
    },
    { key: "priority", label: t("priority"), render: (row) => row.priority },
    { key: "type", label: t("type"), render: (row) => row.type },
    { key: "assignee", label: t("assignee"), render: (row) => row.assignee?.name || "—" },
    {
      key: "due",
      label: t("dueAt"),
      render: (row) => formatDateTime(row.due_at, locale),
    },
  ];

  return (
    <div className="space-y-4">
      <WorkspaceHeader
        title={t("title")}
        subtitle={t("subtitle")}
        meta={<span>{t("totalCount", { count: meta.total })}</span>}
      />
      <SavedViewSelector views={views} value={filters.view || "team"} onChange={applyView} />
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
        <Select value={filters.status} onChange={(e) => setParams({ status: e.target.value, view: "team" })}>
          <option value="">{t("status")}</option>
          {TASK_STATUSES.map((s) => (
            <option key={s} value={s}>{t(`status_${s}` as "status_pending")}</option>
          ))}
        </Select>
        <Select value={filters.type} onChange={(e) => setParams({ type: e.target.value })}>
          <option value="">{t("type")}</option>
          <option value="field">{t("viewField")}</option>
          <option value="office">{t("viewOffice")}</option>
        </Select>
      </FilterBar>

      {error ? (
        <ErrorWorkspace message={error} onRetry={() => void load()} />
      ) : !loading && rows.length === 0 ? (
        <EmptyWorkspace />
      ) : (
        <Panel className="overflow-hidden p-0">
          <DataTable
            rows={rows}
            columns={columns}
            rowKey={(r) => r.id}
            loading={loading}
            page={meta.current_page}
            lastPage={meta.last_page}
            onPageChange={(page) => setParams({ page: String(page) }, false)}
            mobileCard={(row) => (
              <MobileRecordCard
                href={`/tasks/${row.id}`}
                title={row.task_number}
                subtitle={row.title}
                badges={<StatusBadge status={row.status} />}
                meta={formatDateTime(row.due_at, locale)}
              />
            )}
          />
        </Panel>
      )}
    </div>
  );
}
