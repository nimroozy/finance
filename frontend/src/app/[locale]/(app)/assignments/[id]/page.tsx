"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  cancelAssignment,
  getAssignment,
  getAssignmentHistory,
  reassignAssignment,
} from "@/lib/assignments";
import { listCollectors } from "@/lib/collectors";
import type {
  AssignmentHistoryEntry,
  Collector,
  CustomerAssignment,
} from "@/lib/types";
import { formatDate, formatDateTime, formatMoney } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Label, Select, TextArea } from "@/components/ui/form";
import { Modal } from "@/components/ui/modal";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function AssignmentDetailPage() {
  const t = useTranslations("assignments");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const params = useParams();
  const id = Number(params.id);

  const canReassign = useAuthStore((s) =>
    s.hasPermission("assignments.reassign"),
  );
  const canCancel = useAuthStore((s) => s.hasPermission("assignments.cancel"));

  const [assignment, setAssignment] = useState<CustomerAssignment | null>(null);
  const [history, setHistory] = useState<AssignmentHistoryEntry[]>([]);
  const [collectors, setCollectors] = useState<Collector[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [reassignOpen, setReassignOpen] = useState(false);
  const [cancelOpen, setCancelOpen] = useState(false);
  const [collectorId, setCollectorId] = useState("");
  const [cancelReason, setCancelReason] = useState("");
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const [a, h] = await Promise.all([
        getAssignment(id),
        getAssignmentHistory(id),
      ]);
      setAssignment(a.data);
      setHistory(h.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    void listCollectors({ is_active: true })
      .then((res) => setCollectors(res.data))
      .catch(() => setCollectors([]));
  }, []);

  async function onReassign(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      await reassignAssignment(id, { collector_id: Number(collectorId) });
      setReassignOpen(false);
      setSuccess(t("reassignSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSaving(false);
    }
  }

  async function onCancel(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      await cancelAssignment(id, cancelReason.trim());
      setCancelOpen(false);
      setSuccess(t("cancelSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (!assignment) {
    return (
      <div>
        {error ? <Alert>{error}</Alert> : <EmptyState label={tCommon("empty")} />}
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t("detailTitle")}
        subtitle={
          assignment.customer?.contact_name || `#${assignment.customer_id}`
        }
        actions={
          <>
            {canReassign && assignment.is_active ? (
              <Button variant="secondary" onClick={() => setReassignOpen(true)}>
                {t("reassign")}
              </Button>
            ) : null}
            {canCancel && assignment.is_active ? (
              <Button variant="danger" onClick={() => setCancelOpen(true)}>
                {t("cancel")}
              </Button>
            ) : null}
            <Link href="/assignments">
              <Button variant="secondary">{tCommon("back")}</Button>
            </Link>
          </>
        }
      />

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

      <Panel className="mb-4 grid gap-4 p-4 sm:grid-cols-2">
        <div>
          <p className="text-xs text-muted">{t("status")}</p>
          <StatusBadge status={assignment.status} />
        </div>
        <div>
          <p className="text-xs text-muted">{t("collector")}</p>
          <p className="font-medium">
            {assignment.collector?.user?.name || `#${assignment.collector_id}`}
          </p>
        </div>
        <div>
          <p className="text-xs text-muted">{t("priority")}</p>
          <p>{assignment.priority}</p>
        </div>
        <div>
          <p className="text-xs text-muted">{t("dueDate")}</p>
          <p>{formatDate(assignment.due_date, locale)}</p>
        </div>
        <div>
          <p className="text-xs text-muted">{t("outstanding")}</p>
          <p>
            {formatMoney(
              assignment.debt_snapshot_outstanding ??
                assignment.customer?.outstanding_receivable,
              assignment.debt_snapshot_currency ??
                assignment.customer?.currency,
              locale,
            )}
          </p>
        </div>
        <div>
          <p className="text-xs text-muted">{t("notes")}</p>
          <p className="whitespace-pre-wrap">{assignment.notes || "—"}</p>
        </div>
        {assignment.customer_id ? (
          <div className="sm:col-span-2">
            <Link
              href={`/customers/${assignment.customer_id}`}
              className="text-sm text-primary hover:underline"
            >
              {t("customer")} #{assignment.customer_id}
            </Link>
          </div>
        ) : null}
      </Panel>

      <PageHeader title={t("history")} />
      <Panel>
        {history.length === 0 ? (
          <EmptyState label={t("noHistory")} />
        ) : (
          <ul className="divide-y divide-border">
            {history.map((h) => (
              <li key={h.id} className="px-4 py-3 text-sm">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="font-medium">{h.event}</span>
                  <span className="text-xs text-muted">
                    {formatDateTime(h.created_at, locale)}
                  </span>
                </div>
                <p className="text-muted">
                  {h.from_status || "—"} → {h.to_status || "—"}
                  {h.changedBy?.name ? ` · ${h.changedBy.name}` : ""}
                </p>
                {h.notes ? <p className="mt-1">{h.notes}</p> : null}
              </li>
            ))}
          </ul>
        )}
      </Panel>

      <Modal
        open={reassignOpen}
        onClose={() => setReassignOpen(false)}
        title={t("reassign")}
      >
        <form onSubmit={onReassign} className="space-y-4">
          <div>
            <Label>{t("collector")}</Label>
            <Select
              value={collectorId}
              onChange={(e) => setCollectorId(e.target.value)}
              required
            >
              <option value="">{t("selectCollector")}</option>
              {collectors.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.user?.name ?? `#${c.id}`}
                </option>
              ))}
            </Select>
          </div>
          <div className="flex justify-end gap-2">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setReassignOpen(false)}
            >
              {tCommon("cancel")}
            </Button>
            <Button type="submit" disabled={saving}>
              {tCommon("confirm")}
            </Button>
          </div>
        </form>
      </Modal>

      <Modal
        open={cancelOpen}
        onClose={() => setCancelOpen(false)}
        title={t("cancel")}
      >
        <form onSubmit={onCancel} className="space-y-4">
          <div>
            <Label>{t("cancelReason")}</Label>
            <TextArea
              value={cancelReason}
              onChange={(e) => setCancelReason(e.target.value)}
              required
            />
          </div>
          <div className="flex justify-end gap-2">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setCancelOpen(false)}
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
