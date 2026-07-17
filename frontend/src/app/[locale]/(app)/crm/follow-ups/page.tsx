"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  FOLLOW_UP_STATUSES,
  cancelFollowUp,
  completeFollowUp,
  listFollowUps,
  type FollowUp,
} from "@/lib/crm";
import { formatDateTime } from "@/lib/utils";
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
import { Select } from "@/components/ui/form";
import { Alert, Panel } from "@/components/ui/layout";

export default function CrmFollowUpsPage() {
  const t = useTranslations("crm");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const canManage = useAuthStore((s) => s.hasPermission("crm.follow_ups.manage"));

  const status = searchParams.get("status") || "";
  const page = Number(searchParams.get("page") || 1);

  const [rows, setRows] = useState<FollowUp[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

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
      const res = await listFollowUps({
        page,
        per_page: 15,
        status: status || undefined,
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
  }, [page, status, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onComplete(id: number) {
    setBusy(true);
    setError(null);
    try {
      await completeFollowUp(id);
      setSuccess(t("followUpCompleted"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onCancel(id: number) {
    setBusy(true);
    setError(null);
    try {
      await cancelFollowUp(id);
      setSuccess(t("followUpCancelled"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  const columns: DataTableColumn<FollowUp>[] = [
    {
      key: "lead",
      label: t("columns.leadNumber"),
      render: (row) =>
        row.lead ? (
          <Link href={`/crm/leads/${row.lead_id}`} className="font-medium text-primary hover:underline">
            {row.lead.lead_number}
          </Link>
        ) : (
          String(row.lead_id)
        ),
    },
    {
      key: "contact",
      label: t("columns.contact"),
      render: (row) => row.lead?.contact_person || "—",
    },
    {
      key: "due",
      label: t("columns.dueAt"),
      render: (row) => formatDateTime(row.due_at, locale),
    },
    {
      key: "status",
      label: t("columns.stage"),
      render: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: "actions",
      label: tCommon("actions"),
      render: (row) =>
        canManage && (row.status === "pending" || row.status === "overdue") ? (
          <div className="flex gap-2">
            <Button size="sm" disabled={busy} onClick={() => void onComplete(row.id)}>
              {t("complete")}
            </Button>
            <Button
              size="sm"
              variant="secondary"
              disabled={busy}
              onClick={() => void onCancel(row.id)}
            >
              {tCommon("cancel")}
            </Button>
          </div>
        ) : (
          "—"
        ),
    },
  ];

  return (
    <div className="space-y-4">
      <WorkspaceHeader title={t("followUpsTitle")} subtitle={t("followUpsSubtitle")} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}
      <FilterBar onClear={() => setParams({ status: "" })}>
        <Select value={status} onChange={(e) => setParams({ status: e.target.value })}>
          <option value="">{t("allStatuses")}</option>
          {FOLLOW_UP_STATUSES.map((s) => (
            <option key={s} value={s}>
              {t(`followUpStatuses.${s}` as "followUpStatuses.pending")}
            </option>
          ))}
        </Select>
      </FilterBar>
      {error && rows.length === 0 ? (
        <ErrorWorkspace message={error} onRetry={() => void load()} />
      ) : null}
      {!loading && rows.length === 0 ? <EmptyWorkspace label={t("emptyFollowUps")} /> : null}
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
              href={`/crm/leads/${row.lead_id}`}
              title={row.lead?.lead_number || String(row.lead_id)}
              subtitle={formatDateTime(row.due_at, locale)}
              badges={<StatusBadge status={row.status} />}
            />
          )}
        />
      </Panel>
    </div>
  );
}
