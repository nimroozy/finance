"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  QUOTATION_STATUSES,
  acceptQuotation,
  listQuotations,
  rejectQuotation,
  sendQuotation,
  type Quotation,
} from "@/lib/crm";
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
import { StatusBadge } from "@/components/status-badge";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/form";
import { Alert, Panel } from "@/components/ui/layout";

export default function CrmQuotationsPage() {
  const t = useTranslations("crm");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const canCreate = useAuthStore((s) => s.hasPermission("crm.quotations.create"));
  const canApprove = useAuthStore((s) => s.hasPermission("crm.quotations.approve"));

  const status = searchParams.get("status") || "";
  const branchId = searchParams.get("branch_id") || "";
  const page = Number(searchParams.get("page") || 1);

  const [rows, setRows] = useState<Quotation[]>([]);
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
      const res = await listQuotations({
        page,
        per_page: 15,
        status: status || undefined,
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
  }, [branchId, page, status, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function act(kind: "send" | "accept" | "reject", id: number) {
    setBusy(true);
    setError(null);
    try {
      if (kind === "send") await sendQuotation(id);
      if (kind === "accept") await acceptQuotation(id);
      if (kind === "reject") await rejectQuotation(id);
      setSuccess(t("quoteActionSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  const columns: DataTableColumn<Quotation>[] = [
    {
      key: "number",
      label: t("columns.quoteNumber"),
      render: (row) => (
        <Link href={`/crm/leads/${row.lead_id}`} className="font-medium text-primary hover:underline">
          {row.quote_number}
        </Link>
      ),
    },
    {
      key: "monthly",
      label: t("fields.monthlyPackage"),
      render: (row) => String(row.monthly_package ?? "—"),
    },
    {
      key: "install",
      label: t("fields.installationFee"),
      render: (row) => String(row.installation_fee ?? "—"),
    },
    {
      key: "status",
      label: tCommon("status"),
      render: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: "updated",
      label: t("columns.updated"),
      render: (row) => (row.updated_at ? formatDateTime(row.updated_at, locale) : "—"),
    },
    {
      key: "actions",
      label: tCommon("actions"),
      render: (row) => (
        <div className="flex flex-wrap gap-2">
          {canCreate && row.status === "draft" ? (
            <Button size="sm" disabled={busy} onClick={() => void act("send", row.id)}>
              {t("sendQuote")}
            </Button>
          ) : null}
          {canApprove && row.status === "sent" ? (
            <>
              <Button size="sm" disabled={busy} onClick={() => void act("accept", row.id)}>
                {t("acceptQuote")}
              </Button>
              <Button
                size="sm"
                variant="secondary"
                disabled={busy}
                onClick={() => void act("reject", row.id)}
              >
                {t("rejectQuote")}
              </Button>
            </>
          ) : null}
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <WorkspaceHeader title={t("quotationsTitle")} subtitle={t("quotationsSubtitle")} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}
      <FilterBar
        onClear={() => {
          setBranchLabel(null);
          setParams({ status: "", branch_id: "" });
        }}
      >
        <Select value={status} onChange={(e) => setParams({ status: e.target.value })}>
          <option value="">{t("allStatuses")}</option>
          {QUOTATION_STATUSES.map((s) => (
            <option key={s} value={s}>
              {t(`quoteStatuses.${s}` as "quoteStatuses.draft")}
            </option>
          ))}
        </Select>
        <BranchPicker
          value={branchId || null}
          selectedLabel={branchLabel}
          onChange={(opt) => {
            setBranchLabel(opt?.label ?? null);
            setParams({ branch_id: opt ? String(opt.id) : "" });
          }}
        />
      </FilterBar>
      {!loading && rows.length === 0 ? <EmptyWorkspace label={t("emptyQuotations")} /> : null}
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
              title={row.quote_number}
              subtitle={String(row.monthly_package ?? "")}
              badges={<StatusBadge status={row.status} />}
            />
          )}
        />
      </Panel>
    </div>
  );
}
