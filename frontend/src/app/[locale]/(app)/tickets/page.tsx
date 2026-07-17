"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { formatAge, slaLabelFromState, todayIsoDate } from "@/lib/ops-filters";
import {
  TICKET_PRIORITIES,
  TICKET_STATUSES,
  listTicketTypes,
  listTickets,
  type Ticket,
  type TicketType,
} from "@/lib/tickets";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import {
  BranchPicker,
  DepartmentPicker,
  EmptyWorkspace,
  ErrorWorkspace,
  FilterBar,
  MobileRecordCard,
  PriorityIndicator,
  SavedViewSelector,
  SlaIndicator,
  UserPicker,
  WorkspaceHeader,
  type SavedView,
} from "@/components/ops";
import type { PickerOption } from "@/lib/pickers";
import { StatusBadge } from "@/components/status-badge";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { Input, Select } from "@/components/ui/form";
import { Panel } from "@/components/ui/layout";

const FILTER_KEYS = [
  "view",
  "search",
  "status",
  "priority",
  "branch_id",
  "type_code",
  "department_id",
  "assignee_id",
  "sla_state",
  "overdue",
  "unassigned",
  "page",
  "per_page",
  "updated_from",
] as const;

type FilterKey = (typeof FILTER_KEYS)[number];

function readParam(sp: URLSearchParams, key: FilterKey) {
  return sp.get(key) ?? "";
}

export default function TicketsPage() {
  const t = useTranslations("tickets");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const canCreate = useAuthStore((s) => s.hasPermission("tickets.create"));
  const userId = useAuthStore((s) => s.user?.id);

  const filters = useMemo(() => {
    const map = {} as Record<FilterKey, string>;
    for (const key of FILTER_KEYS) map[key] = readParam(searchParams, key);
    if (!map.per_page) map.per_page = "15";
    if (!map.view) map.view = "all";
    return map;
  }, [searchParams]);

  const [rows, setRows] = useState<Ticket[]>([]);
  const [types, setTypes] = useState<TicketType[]>([]);
  const [total, setTotal] = useState(0);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [draftSearch, setDraftSearch] = useState(filters.search);
  const [assigneeLabel, setAssigneeLabel] = useState<string | null>(null);
  const [departmentLabel, setDepartmentLabel] = useState<string | null>(null);
  const [branchLabel, setBranchLabel] = useState<string | null>(null);

  useEffect(() => {
    setDraftSearch(filters.search);
  }, [filters.search]);

  useEffect(() => {
    void listTicketTypes()
      .then((res) => setTypes(res.data))
      .catch(() => setTypes([]));
  }, []);

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
        sla_state: "",
        unassigned: "",
        overdue: "",
        assignee_id: "",
        updated_from: "",
        page: "",
      };
      switch (viewId) {
        case "my_open":
          if (userId) base.assignee_id = String(userId);
          break;
        case "unassigned":
          base.unassigned = "1";
          break;
        case "new":
          base.status = "new";
          break;
        case "sla_at_risk":
          base.sla_state = "at_risk";
          break;
        case "sla_breached":
          base.sla_state = "breached";
          break;
        case "waiting_customer":
          base.status = "waiting_customer";
          break;
        case "waiting_equipment":
          base.status = "waiting_equipment";
          break;
        case "resolved_today":
          base.status = "resolved";
          base.updated_from = todayIsoDate();
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
      const perPage = Number(filters.per_page || 15);
      const res = await listTickets({
        page,
        per_page: perPage,
        search: filters.search || undefined,
        status: filters.status || undefined,
        priority: filters.priority || undefined,
        branch_id: filters.branch_id || undefined,
        type_code: filters.type_code || undefined,
        department_id: filters.department_id || undefined,
        assignee_id: filters.assignee_id || undefined,
        sla_state: filters.sla_state || undefined,
        overdue: filters.overdue || undefined,
        unassigned: filters.unassigned || undefined,
        updated_from: filters.updated_from || undefined,
      });
      setRows(res.data);
      setTotal(res.meta?.total ?? res.data.length);
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
  }, [filters, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  const views: SavedView[] = [
    { id: "my_open", label: t("viewMyOpen") },
    { id: "unassigned", label: t("viewUnassigned") },
    { id: "new", label: t("viewNew") },
    { id: "sla_at_risk", label: t("viewSlaAtRisk") },
    { id: "sla_breached", label: t("viewSlaBreached") },
    { id: "waiting_customer", label: t("viewWaitingCustomer") },
    { id: "waiting_equipment", label: t("viewWaitingEquipment") },
    { id: "resolved_today", label: t("viewResolvedToday") },
    { id: "all", label: t("viewAll") },
  ];

  const columns: DataTableColumn<Ticket>[] = [
    {
      key: "number",
      label: t("number"),
      render: (row) => (
        <Link href={`/tickets/${row.id}`} className="font-medium text-primary hover:underline">
          {row.ticket_number}
        </Link>
      ),
    },
    {
      key: "customer",
      label: t("customer"),
      render: (row) => {
        const name =
          row.customer?.contact_name ||
          row.customer?.company_name ||
          row.customer_number ||
          "—";
        const num = row.customer?.customer_number || row.customer_number;
        return (
          <span>
            {name}
            {num ? <span className="ms-1 text-muted">({num})</span> : null}
          </span>
        );
      },
    },
    { key: "subject", label: t("subject"), render: (row) => row.subject },
    { key: "type", label: t("type"), render: (row) => row.type_code },
    {
      key: "branch",
      label: tCommon("branch"),
      render: (row) =>
        locale === "fa"
          ? row.branch?.name_fa || row.branch?.name_en || row.branch?.code || row.branch_id
          : row.branch?.name_en || row.branch?.code || row.branch_id,
    },
    {
      key: "department",
      label: t("department"),
      render: (row) => row.assigned_department?.name_en || row.assigned_department?.code || "—",
    },
    {
      key: "assignee",
      label: t("assignee"),
      render: (row) => row.primary_assignee?.name || "—",
    },
    {
      key: "status",
      label: t("status"),
      render: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: "priority",
      label: t("priority"),
      render: (row) => <PriorityIndicator priority={row.priority} />,
    },
    {
      key: "sla",
      label: t("sla"),
      render: (row) => {
        const label = slaLabelFromState(row.sla_state);
        return (
          <SlaIndicator
            status={label}
            label={label === "—" ? "—" : t(`sla_${label}` as "sla_ok")}
            dueAt={formatDateTime(row.resolution_due_at || row.sla_state?.resolution_due_at, locale)}
          />
        );
      },
    },
    {
      key: "updated",
      label: t("updatedAt"),
      render: (row) => formatDateTime(row.updated_at, locale),
    },
    {
      key: "age",
      label: t("age"),
      render: (row) => formatAge(row.created_at, locale),
    },
  ];

  return (
    <div className="space-y-4">
      <WorkspaceHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          canCreate ? (
            <Link href="/tickets/new">
              <Button>{t("newTicket")}</Button>
            </Link>
          ) : null
        }
        meta={
          <span>
            {t("totalCount", { count: total })}
          </span>
        }
      />

      <SavedViewSelector
        label={t("savedViews")}
        views={views}
        value={filters.view || "all"}
        onChange={applyView}
      />

      <FilterBar
        loading={loading}
        onApply={() => setParams({ search: draftSearch })}
        onClear={() => {
          setAssigneeLabel(null);
          setDepartmentLabel(null);
          setBranchLabel(null);
          router.replace(pathname);
        }}
      >
        <Input
          type="search"
          value={draftSearch}
          placeholder={t("searchPlaceholder")}
          onChange={(e) => setDraftSearch(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter") setParams({ search: draftSearch });
          }}
        />
        <Select
          value={filters.status}
          onChange={(e) => setParams({ status: e.target.value, view: "all" })}
        >
          <option value="">{t("status")}</option>
          {TICKET_STATUSES.map((s) => (
            <option key={s} value={s}>
              {t(`status_${s}` as "status_new")}
            </option>
          ))}
        </Select>
        <Select
          value={filters.priority}
          onChange={(e) => setParams({ priority: e.target.value })}
        >
          <option value="">{t("priority")}</option>
          {TICKET_PRIORITIES.map((p) => (
            <option key={p} value={p}>
              {t(`priority_${p}` as "priority_low")}
            </option>
          ))}
        </Select>
        <BranchPicker
          value={filters.branch_id || null}
          selectedLabel={branchLabel}
          onChange={(opt: PickerOption | null) => {
            setBranchLabel(opt?.label ?? null);
            setParams({ branch_id: opt ? String(opt.id) : "" });
          }}
        />
        <Select
          value={filters.type_code}
          onChange={(e) => setParams({ type_code: e.target.value })}
        >
          <option value="">{t("type")}</option>
          {types.map((ty) => (
            <option key={ty.code} value={ty.code}>
              {locale === "fa" ? ty.name_fa || ty.name_en : ty.name_en}
            </option>
          ))}
        </Select>
        <DepartmentPicker
          branchId={filters.branch_id || undefined}
          value={filters.department_id || null}
          selectedLabel={departmentLabel}
          onChange={(opt) => {
            setDepartmentLabel(opt?.label ?? null);
            setParams({ department_id: opt ? String(opt.id) : "" });
          }}
        />
        <UserPicker
          value={filters.assignee_id || null}
          selectedLabel={assigneeLabel}
          onChange={(opt) => {
            setAssigneeLabel(opt?.label ?? null);
            setParams({ assignee_id: opt ? String(opt.id) : "", view: "all" });
          }}
        />
        <Select
          value={filters.sla_state}
          onChange={(e) => setParams({ sla_state: e.target.value, view: "all" })}
        >
          <option value="">{t("slaState")}</option>
          <option value="ok">{t("sla_ok")}</option>
          <option value="at_risk">{t("sla_at_risk")}</option>
          <option value="breached">{t("sla_breached")}</option>
          <option value="paused">{t("sla_paused")}</option>
        </Select>
        <Select
          value={filters.overdue}
          onChange={(e) => setParams({ overdue: e.target.value })}
        >
          <option value="">{t("overdue")}</option>
          <option value="1">{tCommon("yes")}</option>
        </Select>
        <Select
          value={filters.unassigned}
          onChange={(e) => setParams({ unassigned: e.target.value, view: e.target.value ? "unassigned" : "all" })}
        >
          <option value="">{t("unassigned")}</option>
          <option value="1">{tCommon("yes")}</option>
        </Select>
        <Select
          value={filters.per_page || "15"}
          onChange={(e) => setParams({ per_page: e.target.value })}
        >
          {[15, 25, 50].map((n) => (
            <option key={n} value={n}>
              {t("pageSize", { count: n })}
            </option>
          ))}
        </Select>
      </FilterBar>

      {error ? (
        <ErrorWorkspace message={error} onRetry={() => void load()} />
      ) : !loading && rows.length === 0 ? (
        <EmptyWorkspace label={tCommon("empty")} />
      ) : (
        <Panel className="overflow-hidden p-0">
          <DataTable
            rows={rows}
            columns={columns}
            rowKey={(row) => row.id}
            loading={loading}
            page={meta.current_page}
            lastPage={meta.last_page}
            onPageChange={(page) => setParams({ page: String(page) }, false)}
            mobileCard={(row) => (
              <MobileRecordCard
                href={`/tickets/${row.id}`}
                title={row.ticket_number}
                subtitle={row.subject}
                badges={<StatusBadge status={row.status} />}
                meta={
                  <span>
                    {row.primary_assignee?.name || "—"} · {" "}
                    {formatAge(row.created_at, locale)}
                  </span>
                }
              />
            )}
          />
        </Panel>
      )}
    </div>
  );
}
