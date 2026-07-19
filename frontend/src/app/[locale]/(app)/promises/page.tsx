"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import {
  cancelPromise,
  fulfillPromise,
  listPromises,
} from "@/lib/promises";
import type { PromiseToPay } from "@/lib/types";
import { formatDate, formatMoney } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Select, TextArea, Label } from "@/components/ui/form";
import { Modal } from "@/components/ui/modal";
import { PageHeader, Alert } from "@/components/ui/layout";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ErrorState } from "@/components/ui/error-state";

export default function PromisesPage() {
  const t = useTranslations("promisesPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canManage = useAuthStore((s) => s.hasPermission("promises.manage"));
  const canCancel = useAuthStore((s) =>
    s.hasAnyPermission(["promises.create", "promises.manage"]),
  );

  const [rows, setRows] = useState<PromiseToPay[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [status, setStatus] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [cancelTarget, setCancelTarget] = useState<PromiseToPay | null>(null);
  const [cancelReason, setCancelReason] = useState("");
  const [saving, setSaving] = useState(false);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listPromises({
          page,
          status: status || undefined,
        });
        setRows(res.data);
        setMeta({
          current_page: res.meta?.current_page ?? 1,
          last_page: res.meta?.last_page ?? 1,
        });
      } catch (err) {
        setError(err);
      } finally {
        setLoading(false);
      }
    },
    [status],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  async function onFulfill(id: number) {
    setError(null);
    try {
      await fulfillPromise(id);
      setSuccess(t("fulfillSuccess"));
      await load(meta.current_page);
    } catch (err) {
      setError(err);
    }
  }

  async function onCancel(e: React.FormEvent) {
    e.preventDefault();
    if (!cancelTarget) return;
    setSaving(true);
    try {
      await cancelPromise(cancelTarget.id, cancelReason.trim() || undefined);
      setCancelTarget(null);
      setCancelReason("");
      setSuccess(t("cancelSuccess"));
      await load(meta.current_page);
    } catch (err) {
      setError(err);
    } finally {
      setSaving(false);
    }
  }

  const openStatuses = ["active", "due_soon", "due_today", "overdue"];

  const columns: DataTableColumn<PromiseToPay>[] = [
    {
      key: "customer",
      label: t("customer"),
      render: (row) => row.customer?.contact_name || row.customer?.company_name || `#${row.customer_id}`,
    },
    { key: "amount", label: t("amount"), render: (row) => formatMoney(row.amount, row.currency, locale) },
    { key: "date", label: t("date"), render: (row) => formatDate(row.promised_date, locale) },
    { key: "branch", label: tCommon("branch"), render: (row) => row.branch?.code || "—" },
    { key: "status", label: t("status"), render: (row) => <StatusBadge status={row.status} /> },
    { key: "collector", label: t("collector"), render: (row) => row.collector?.user?.name || "—" },
    {
      key: "assignment",
      label: t("relatedAssignment"),
      render: (row) =>
        row.assignment_id ? (
          <a href={`/assignments/${row.assignment_id}`} className="text-primary hover:underline">
            #{row.assignment_id}
          </a>
        ) : (
          "—"
        ),
    },
    {
      key: "actions",
      label: tCommon("actions"),
      render: (row) => (
        <div className="flex flex-wrap gap-2">
          {canManage && openStatuses.includes(row.status) ? (
            <Button size="sm" onClick={() => void onFulfill(row.id)}>
              {t("fulfill")}
            </Button>
          ) : null}
          {canCancel && openStatuses.includes(row.status) ? (
            <Button size="sm" variant="secondary" onClick={() => setCancelTarget(row)}>
              {t("cancel")}
            </Button>
          ) : null}
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          <Link href="/collector/promises/new">
            <Button size="sm">{t("create")}</Button>
          </Link>
        }
      />

      {success ? (
        <div className="mb-4">
          <Alert tone="success">{success}</Alert>
        </div>
      ) : null}

      {error ? (
        <ErrorState error={error} onRetry={() => load(meta.current_page)} />
      ) : (
        <DataTable
          rows={rows}
          columns={columns}
          rowKey={(row) => row.id}
          loading={loading}
          emptyLabel={tCommon("empty")}
          page={meta.current_page}
          lastPage={meta.last_page}
          onPageChange={(page) => void load(page)}
          filters={
            <Select value={status} onChange={(e) => setStatus(e.target.value)} className="sm:max-w-xs" aria-label={t("status")}>
              <option value="">{tCommon("all")}</option>
              {["active", "due_soon", "due_today", "overdue", "fulfilled", "cancelled", "superseded"].map((s) => (
                <option key={s} value={s}>
                  {s.replace(/_/g, " ")}
                </option>
              ))}
            </Select>
          }
        />
      )}

      <Modal
        open={Boolean(cancelTarget)}
        onClose={() => setCancelTarget(null)}
        title={t("cancel")}
      >
        <form onSubmit={onCancel} className="space-y-4">
          <div>
            <Label>{t("cancelReason")}</Label>
            <TextArea
              value={cancelReason}
              onChange={(e) => setCancelReason(e.target.value)}
            />
          </div>
          <div className="flex justify-end gap-2">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setCancelTarget(null)}
            >
              {tCommon("cancel")}
            </Button>
            <Button type="submit" variant="danger" disabled={saving}>
              {tCommon("confirm")}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
