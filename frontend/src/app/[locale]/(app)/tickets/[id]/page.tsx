"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { uploadAttachment } from "@/lib/operations";
import { listTasks, type Task } from "@/lib/tasks";
import {
  TICKET_STATUSES,
  assignTicket,
  closeTicket,
  createWorkLog,
  getTicket,
  listWorkLogs,
  reopenTicket,
  resolveTicket,
  transitionTicket,
  type Ticket,
  type WorkLog,
} from "@/lib/tickets";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Input, Select, TextArea } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function TicketDetailPage() {
  const t = useTranslations("tickets");
  const tCommon = useTranslations("common");
  const params = useParams();
  const id = String(params.id || "");

  const canUpdate = useAuthStore((s) => s.hasPermission("tickets.update"));
  const canAssign = useAuthStore((s) => s.hasPermission("tickets.assign"));
  const canResolve = useAuthStore((s) => s.hasPermission("tickets.resolve"));
  const canClose = useAuthStore((s) => s.hasPermission("tickets.close"));
  const canReopen = useAuthStore((s) => s.hasPermission("tickets.reopen"));
  const canUpload = useAuthStore((s) => s.hasPermission("attachments.upload"));

  const [ticket, setTicket] = useState<Ticket | null>(null);
  const [tasks, setTasks] = useState<Task[]>([]);
  const [logs, setLogs] = useState<WorkLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [nextStatus, setNextStatus] = useState("");
  const [assigneeId, setAssigneeId] = useState("");
  const [resolution, setResolution] = useState("");
  const [note, setNote] = useState("");
  const [uploads, setUploads] = useState<string[]>([]);

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const [ticketRes, tasksRes, logsRes] = await Promise.all([
        getTicket(id),
        listTasks({ ticket_id: id, per_page: 50 }),
        listWorkLogs({ ticket_id: id, per_page: 50 }),
      ]);
      setTicket(ticketRes.data);
      setTasks(tasksRes.data);
      setLogs(logsRes.data);
      setResolution(ticketRes.data.resolution_summary || "");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function run(action: () => Promise<unknown>, okMessage: string) {
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      await action();
      setSuccess(okMessage);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (!ticket) {
    return (
      <div>
        {error ? <Alert>{error}</Alert> : <EmptyState label={tCommon("empty")} />}
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={ticket.ticket_number}
        subtitle={ticket.subject}
        actions={<StatusBadge status={ticket.status} />}
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

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel className="space-y-3 p-4 text-sm">
          <p>
            <span className="text-muted">{t("priority")}: </span>
            {ticket.priority}
          </p>
          <p>
            <span className="text-muted">{t("type")}: </span>
            {ticket.type_code}
          </p>
          <p>
            <span className="text-muted">{t("source")}: </span>
            {ticket.source}
          </p>
          <p>
            <span className="text-muted">{t("assignee")}: </span>
            {ticket.primary_assignee?.name || "—"}
          </p>
          <p className="whitespace-pre-wrap">{ticket.description || "—"}</p>
        </Panel>

        <Panel className="space-y-3 p-4">
          <h2 className="font-medium">{t("sla")}</h2>
          <p className="text-sm text-muted">
            {t("responseDue")}: {" "}
            {formatDateTime(ticket.response_due_at || ticket.sla_state?.response_due_at) || "—"}
          </p>
          <p className="text-sm text-muted">
            {t("resolutionDue")}: {" "}
            {formatDateTime(ticket.resolution_due_at || ticket.sla_state?.resolution_due_at) || "—"}
          </p>
          {ticket.sla_state?.breached_at ? (
            <Alert tone="warning">{t("slaBreached")}</Alert>
          ) : null}

          {canUpdate ? (
            <div className="flex flex-wrap gap-2 pt-2">
              <Select value={nextStatus} onChange={(e) => setNextStatus(e.target.value)}>
                <option value="">{t("changeStatus")}</option>
                {TICKET_STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {t(`status_${s}` as "status_new")}
                  </option>
                ))}
              </Select>
              <Button
                disabled={busy || !nextStatus}
                onClick={() =>
                  void run(
                    () => transitionTicket(id, nextStatus),
                    t("statusUpdated"),
                  )
                }
              >
                {t("applyStatus")}
              </Button>
            </div>
          ) : null}

          {canAssign ? (
            <div className="flex flex-wrap gap-2">
              <Input
                type="number"
                placeholder={t("assigneeUserId")}
                value={assigneeId}
                onChange={(e) => setAssigneeId(e.target.value)}
              />
              <Button
                variant="secondary"
                disabled={busy || !assigneeId}
                onClick={() =>
                  void run(
                    () => assignTicket(id, Number(assigneeId)),
                    t("assigned"),
                  )
                }
              >
                {t("assign")}
              </Button>
            </div>
          ) : null}
        </Panel>
      </div>

      <Panel className="mt-4 p-4">
        <h2 className="mb-3 font-medium">{t("tasks")}</h2>
        {tasks.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <ul className="space-y-2 text-sm">
            {tasks.map((task) => (
              <li key={task.id} className="flex items-center justify-between gap-2">
                <Link href={`/tasks/${task.id}`} className="text-primary hover:underline">
                  {task.task_number} — {task.title}
                </Link>
                <StatusBadge status={task.status} />
              </li>
            ))}
          </ul>
        )}
      </Panel>

      <Panel className="mt-4 space-y-3 p-4">
        <h2 className="font-medium">{t("workLogs")}</h2>
        {logs.length === 0 ? <EmptyState label={tCommon("empty")} /> : null}
        <ul className="space-y-2 text-sm">
          {logs.map((log) => (
            <li key={log.id} className="rounded border border-border p-2">
              <p>{log.internal_note || log.body || log.customer_visible_note || "—"}</p>
              <p className="text-xs text-muted">{formatDateTime(log.created_at)}</p>
            </li>
          ))}
        </ul>
        {canUpdate ? (
          <div className="flex flex-col gap-2 sm:flex-row">
            <TextArea
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder={t("addNote")}
              rows={2}
              className="flex-1"
            />
            <Button
              variant="secondary"
              disabled={busy || !note.trim()}
              onClick={() =>
                void run(async () => {
                  await createWorkLog({
                    ticket_id: Number(id),
                    internal_note: note.trim(),
                  });
                  setNote("");
                }, t("noteAdded"))
              }
            >
              {t("addNote")}
            </Button>
          </div>
        ) : null}
      </Panel>

      <Panel className="mt-4 space-y-3 p-4">
        <h2 className="font-medium">{t("attachments")}</h2>
        {uploads.length > 0 ? (
          <ul className="text-sm text-muted">
            {uploads.map((name) => (
              <li key={name}>{name}</li>
            ))}
          </ul>
        ) : (
          <p className="text-sm text-muted">{t("noAttachments")}</p>
        )}
        {canUpload ? (
          <Input
            type="file"
            onChange={(e) => {
              const file = e.target.files?.[0];
              if (!file) return;
              void run(async () => {
                await uploadAttachment({
                  file,
                  attachable_type: "ticket",
                  attachable_id: id,
                });
                setUploads((prev) => [...prev, file.name]);
              }, t("uploaded"));
            }}
          />
        ) : null}
      </Panel>

      <Panel className="mt-4 space-y-3 p-4">
        <h2 className="font-medium">{t("resolution")}</h2>
        <TextArea
          value={resolution}
          onChange={(e) => setResolution(e.target.value)}
          rows={3}
          disabled={!canResolve}
        />
        <div className="flex flex-wrap gap-2">
          {canResolve ? (
            <Button
              disabled={busy}
              onClick={() =>
                void run(
                  () =>
                    resolveTicket(id, {
                      resolution_summary: resolution,
                      customer_confirmation: false,
                    }),
                  t("resolved"),
                )
              }
            >
              {t("resolve")}
            </Button>
          ) : null}
          {canClose ? (
            <Button
              variant="secondary"
              disabled={busy}
              onClick={() => void run(() => closeTicket(id), t("closed"))}
            >
              {t("close")}
            </Button>
          ) : null}
          {canReopen ? (
            <Button
              variant="ghost"
              disabled={busy}
              onClick={() => void run(() => reopenTicket(id), t("reopened"))}
            >
              {t("reopen")}
            </Button>
          ) : null}
        </div>
      </Panel>
    </div>
  );
}
