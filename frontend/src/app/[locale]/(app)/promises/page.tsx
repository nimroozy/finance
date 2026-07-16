"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
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
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

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
  const [error, setError] = useState<string | null>(null);
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
        setError(err instanceof ApiError ? err.message : tCommon("error"));
      } finally {
        setLoading(false);
      }
    },
    [status, tCommon],
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
      setError(err instanceof ApiError ? err.message : tCommon("error"));
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
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSaving(false);
    }
  }

  const openStatuses = ["active", "due_soon", "due_today", "overdue"];

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      {success ? (
        <div className="mb-4">
          <Alert tone="success">{success}</Alert>
        </div>
      ) : null}

      <Panel className="mb-4 p-4">
        <Select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="sm:max-w-xs"
        >
          <option value="">{tCommon("all")}</option>
          {[
            "active",
            "due_soon",
            "due_today",
            "overdue",
            "fulfilled",
            "cancelled",
            "superseded",
          ].map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </Select>
      </Panel>

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : rows.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[800px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("customer")}</th>
                  <th className="px-4 py-3 font-medium">{t("amount")}</th>
                  <th className="px-4 py-3 font-medium">{t("date")}</th>
                  <th className="px-4 py-3 font-medium">{t("status")}</th>
                  <th className="px-4 py-3 font-medium">{t("collector")}</th>
                  <th className="px-4 py-3 font-medium">{tCommon("actions")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr
                    key={row.id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-4 py-3">
                      {row.customer?.contact_name || `#${row.customer_id}`}
                    </td>
                    <td className="px-4 py-3">
                      {formatMoney(row.amount, row.currency, locale)}
                    </td>
                    <td className="px-4 py-3">
                      {formatDate(row.promised_date, locale)}
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge status={row.status} />
                    </td>
                    <td className="px-4 py-3">
                      {row.collector?.user?.name || "—"}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-2">
                        {canManage && openStatuses.includes(row.status) ? (
                          <Button
                            size="sm"
                            onClick={() => void onFulfill(row.id)}
                          >
                            {t("fulfill")}
                          </Button>
                        ) : null}
                        {canCancel && openStatuses.includes(row.status) ? (
                          <Button
                            size="sm"
                            variant="secondary"
                            onClick={() => setCancelTarget(row)}
                          >
                            {t("cancel")}
                          </Button>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {meta.last_page > 1 ? (
          <div className="flex items-center justify-between gap-3 border-t border-border px-4 py-3">
            <p className="text-sm text-muted">
              {tCommon("page", {
                page: meta.current_page,
                total: meta.last_page,
              })}
            </p>
            <div className="flex gap-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page <= 1 || loading}
                onClick={() => void load(meta.current_page - 1)}
              >
                {tCommon("previous")}
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page >= meta.last_page || loading}
                onClick={() => void load(meta.current_page + 1)}
              >
                {tCommon("next")}
              </Button>
            </div>
          </div>
        ) : null}
      </Panel>

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
