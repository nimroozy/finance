"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { completeSiteSurvey, listSiteSurveys, type SiteSurvey } from "@/lib/crm";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import {
  BranchPicker,
  EmptyWorkspace,
  ErrorWorkspace,
  FilterBar,
  MobileRecordCard,
  WorkspaceHeader,
} from "@/components/ops";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { Alert, Panel } from "@/components/ui/layout";

export default function CrmSurveysPage() {
  const t = useTranslations("crm");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const canManage = useAuthStore((s) => s.hasPermission("crm.surveys.manage"));

  const branchId = searchParams.get("branch_id") || "";
  const page = Number(searchParams.get("page") || 1);

  const [rows, setRows] = useState<SiteSurvey[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
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
      const res = await listSiteSurveys({
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

  async function onComplete(id: number) {
    setBusy(true);
    setError(null);
    try {
      await completeSiteSurvey(id, { customer_approved: true });
      setSuccess(t("surveyCompleted"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  const columns: DataTableColumn<SiteSurvey>[] = [
    {
      key: "lead",
      label: t("columns.leadNumber"),
      render: (row) => (
        <Link href={`/crm/leads/${row.lead_id}`} className="font-medium text-primary hover:underline">
          #{row.lead_id}
        </Link>
      ),
    },
    {
      key: "date",
      label: t("columns.surveyDate"),
      render: (row) =>
        row.survey_date
          ? formatDateTime(row.survey_date, locale)
          : row.created_at
            ? formatDateTime(row.created_at, locale)
            : "—",
    },
    {
      key: "los",
      label: t("fields.losStatus"),
      render: (row) => row.los_status || "—",
    },
    {
      key: "signal",
      label: t("fields.signal"),
      render: (row) => row.signal_estimate || "—",
    },
    {
      key: "approved",
      label: t("fields.customerApproved"),
      render: (row) => (row.customer_approved ? tCommon("yes") : tCommon("no")),
    },
    {
      key: "actions",
      label: tCommon("actions"),
      render: (row) =>
        canManage && !row.customer_approved ? (
          <Button size="sm" disabled={busy} onClick={() => void onComplete(row.id)}>
            {t("completeSurvey")}
          </Button>
        ) : (
          "—"
        ),
    },
  ];

  return (
    <div className="space-y-4">
      <WorkspaceHeader title={t("surveysTitle")} subtitle={t("surveysSubtitle")} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}
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
      {!loading && rows.length === 0 ? <EmptyWorkspace label={t("emptySurveys")} /> : null}
      {error && rows.length === 0 ? (
        <ErrorWorkspace message={error} onRetry={() => void load()} />
      ) : null}
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
              title={`#${row.lead_id}`}
              subtitle={row.los_status || undefined}
              meta={row.signal_estimate || undefined}
            />
          )}
        />
      </Panel>
    </div>
  );
}
