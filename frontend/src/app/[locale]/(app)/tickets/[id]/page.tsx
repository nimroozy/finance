"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { createTask } from "@/lib/tasks";
import {
  assignTicket,
  closeTicket,
  createWorkLog,
  getTicket,
  listAttachments,
  listWorkLogs,
  reopenTicket,
  resolveTicket,
  transitionTicket,
  updateTicket,
  uploadAttachment,
  type AttachmentSummary,
  type Ticket,
  type WorkLog,
} from "@/lib/tickets";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import {
  ActivityComposer,
  AssignmentPicker,
  AttachmentGallery,
  ConfirmActionDialog,
  CustomerSummaryCard,
  Drawer,
  EmptyWorkspace,
  ErrorWorkspace,
  RecordSummary,
  ResponsiveTabs,
  StatusActionMenu,
  Timeline,
  WorkspaceHeader,
  type AssignmentValue,
  type AttachmentItem,
  type ResponsiveTab,
  type StatusTransition,
  type TimelineItem,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Input, TextArea } from "@/components/ui/form";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

const DANGER_STATUSES = new Set(["cancelled", "closed"]);
const CONFIRM_STATUSES = new Set(["closed", "cancelled", "reopened", "escalated"]);

export default function TicketDetailPage() {
  const t = useTranslations("tickets");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const params = useParams();
  const id = String(params.id || "");

  const canUpdate = useAuthStore((s) => s.hasPermission("tickets.update"));
  const canAssign = useAuthStore((s) => s.hasPermission("tickets.assign"));
  const canResolve = useAuthStore((s) => s.hasPermission("tickets.resolve"));
  const canClose = useAuthStore((s) => s.hasPermission("tickets.close"));
  const canReopen = useAuthStore((s) => s.hasPermission("tickets.reopen"));
  const canUpload = useAuthStore((s) => s.hasPermission("attachments.upload"));
  const canCreateTask = useAuthStore((s) => s.hasPermission("tasks.create"));

  const [ticket, setTicket] = useState<Ticket | null>(null);
  const [logs, setLogs] = useState<WorkLog[]>([]);
  const [attachments, setAttachments] = useState<AttachmentSummary[]>([]);
  const [tab, setTab] = useState("overview");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [resolution, setResolution] = useState("");
  const [assignment, setAssignment] = useState<AssignmentValue>(null);
  const [confirmAction, setConfirmAction] = useState<string | null>(null);
  const [confirmReason, setConfirmReason] = useState("");
  const [taskDrawerOpen, setTaskDrawerOpen] = useState(false);
  const [taskTitle, setTaskTitle] = useState("");
  const [taskAssignment, setTaskAssignment] = useState<AssignmentValue>(null);

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const [ticketRes, logsRes, attachmentsRes] = await Promise.all([
        getTicket(id),
        listWorkLogs({ ticket_id: id, per_page: 50 }),
        listAttachments(id),
      ]);
      setTicket(ticketRes.data);
      setLogs(logsRes.data.length ? logsRes.data : ticketRes.data.recent_work_logs ?? []);
      setAttachments(
        attachmentsRes.data.length
          ? attachmentsRes.data
          : ticketRes.data.attachments_summary ?? [],
      );
      setResolution(ticketRes.data.resolution_summary || "");
      if (ticketRes.data.primary_assignee) {
        setAssignment({
          kind: "user",
          id: ticketRes.data.primary_assignee.id,
          label: ticketRes.data.primary_assignee.name,
        });
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setTicket(null);
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

  const menuTransitions: StatusTransition[] = useMemo(() => {
    const allowed = ticket?.allowed_transitions ?? [];
    return allowed
      .filter((tr) => !CONFIRM_STATUSES.has(tr.status))
      .map((tr) => ({
        id: tr.status,
        label: tr.label,
        danger: DANGER_STATUSES.has(tr.status),
        requiresReason: Boolean(tr.requires_reason),
      }));
  }, [ticket?.allowed_transitions]);

  const confirmTransitions = useMemo(() => {
    return (ticket?.allowed_transitions ?? []).filter((tr) =>
      CONFIRM_STATUSES.has(tr.status),
    );
  }, [ticket?.allowed_transitions]);

  const tabs: ResponsiveTab[] = [
    { id: "overview", label: t("tabOverview") },
    { id: "activity", label: t("tabActivity"), badge: logs.length || undefined },
    { id: "tasks", label: t("tabTasks"), badge: ticket?.tasks?.length || undefined },
    { id: "whatsapp", label: t("tabWhatsApp") },
    { id: "attachments", label: t("tabAttachments"), badge: attachments.length || undefined },
    { id: "related", label: t("tabRelated"), badge: ticket?.open_related?.length || undefined },
    { id: "audit", label: t("tabAudit") },
  ];

  const timelineItems: TimelineItem[] = logs.map((log) => ({
    id: String(log.id),
    title: log.internal_note
      ? t("noteInternal")
      : log.customer_visible_note
        ? t("noteCustomer")
        : t("workLogs"),
    description: log.internal_note || log.customer_visible_note || log.body || "—",
    meta: formatDateTime(log.created_at, locale),
    tone: log.customer_visible_note ? "info" : "neutral",
  }));

  const attachmentItems: AttachmentItem[] = attachments.map((a) => ({
    id: String(a.id),
    name: a.original_name,
    downloadUrl: a.download_url,
    meta: formatDateTime(a.created_at, locale),
  }));

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (!ticket) {
    return <ErrorWorkspace message={error ?? tCommon("empty")} onRetry={() => void load()} />;
  }

  return (
    <div className="space-y-4">
      <WorkspaceHeader
        title={ticket.ticket_number}
        subtitle={ticket.subject}
        badges={[{
          label: ticket.status.replace(/_/g, " "),
          tone: ticket.sla_state?.breached_at ? "danger" : "info",
        }]}
        actions={
          <div className="flex flex-col items-stretch gap-2 sm:items-end">
            <StatusBadge status={ticket.status} />
            {canUpdate && menuTransitions.length > 0 ? (
              <StatusActionMenu
                transitions={menuTransitions}
                loading={busy}
                onTransition={(status, reason) =>
                  run(() => transitionTicket(id, status, reason), t("statusUpdated"))
                }
              />
            ) : null}
            {confirmTransitions.length > 0 ? (
              <div className="flex flex-wrap gap-2">
                {confirmTransitions.map((tr) => {
                  const canDo =
                    (tr.status === "closed" && canClose) ||
                    (tr.status === "reopened" && canReopen) ||
                    (tr.status === "cancelled" && canUpdate) ||
                    (tr.status === "escalated" && canUpdate) ||
                    (tr.status !== "closed" && tr.status !== "reopened");
                  if (!canDo) return null;
                  return (
                    <Button
                      key={tr.status}
                      size="sm"
                      variant={DANGER_STATUSES.has(tr.status) ? "danger" : "secondary"}
                      disabled={busy}
                      onClick={() => {
                        setConfirmReason("");
                        setConfirmAction(tr.status);
                      }}
                    >
                      {tr.label}
                    </Button>
                  );
                })}
              </div>
            ) : null}
          </div>
        }
      />

      {error ? <Alert>{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}

      <ResponsiveTabs tabs={tabs} value={tab} onChange={setTab} label={t("tabs")} />

      {tab === "overview" ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <div className="space-y-4">
            {ticket.customer || ticket.customer_id ? (
              <CustomerSummaryCard
                customerNumber={ticket.customer?.customer_number || ticket.customer_number}
                name={ticket.customer?.contact_name || ticket.customer?.company_name}
                phone={ticket.customer?.mobile || ticket.customer?.phone || ticket.customer_phone}
                branch={ticket.branch?.name_en || ticket.branch?.code}
                openTicketsCount={ticket.open_related?.length ?? null}
                href={ticket.customer_id ? `/customers/${ticket.customer_id}` : undefined}
              />
            ) : null}
            <Panel className="p-4">
              <RecordSummary
                items={[
                  { label: t("type"), value: ticket.type_code },
                  { label: t("priority"), value: ticket.priority },
                  { label: t("source"), value: ticket.source },
                  { label: t("assignee"), value: ticket.primary_assignee?.name || "—" },
                  {
                    label: t("department"),
                    value: ticket.assigned_department?.name_en || ticket.assigned_department?.code || "—",
                  },
                  {
                    label: t("responseDue"),
                    value: formatDateTime(
                      ticket.response_due_at || ticket.sla_state?.response_due_at,
                      locale,
                    ),
                  },
                  {
                    label: t("resolutionDue"),
                    value: formatDateTime(
                      ticket.resolution_due_at || ticket.sla_state?.resolution_due_at,
                      locale,
                    ),
                  },
                ]}
              />
              <p className="mt-4 whitespace-pre-wrap text-sm">{ticket.description || "—"}</p>
            </Panel>
          </div>
          <Panel className="space-y-4 p-4">
            <h2 className="font-medium">{t("resolution")}</h2>
            <TextArea
              value={resolution}
              onChange={(e) => setResolution(e.target.value)}
              rows={4}
              disabled={!canResolve}
            />
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

            {canAssign ? (
              <div className="space-y-2 border-t border-border pt-4">
                <p className="text-sm font-medium">{t("assignment")}</p>
                <AssignmentPicker
                  branchId={ticket.branch_id}
                  value={assignment}
                  onChange={setAssignment}
                  allowedKinds={["user", "team", "department"]}
                />
                <Button
                  variant="secondary"
                  disabled={busy || !assignment}
                  onClick={() =>
                    void run(async () => {
                      if (!assignment) return;
                      if (assignment.kind === "user") {
                        await assignTicket(id, Number(assignment.id));
                      } else if (assignment.kind === "department") {
                        await updateTicket(id, {
                          assigned_department_id: Number(assignment.id),
                        });
                      } else {
                        await updateTicket(id, {
                          assigned_team_id: Number(assignment.id),
                        });
                      }
                    }, t("assigned"))
                  }
                >
                  {t("assign")}
                </Button>
              </div>
            ) : null}
          </Panel>
        </div>
      ) : null}

      {tab === "activity" ? (
        <Panel className="space-y-4 p-4">
          <Timeline items={timelineItems} emptyLabel={tCommon("empty")} />
          {canUpdate ? (
            <ActivityComposer
              loading={busy}
              onSubmit={({ type, body }) =>
                run(async () => {
                  await createWorkLog({
                    ticket_id: Number(id),
                    internal_note: type === "internal" ? body : undefined,
                    customer_visible_note: type === "customer_visible" ? body : undefined,
                  });
                }, t("noteAdded"))
              }
            />
          ) : null}
        </Panel>
      ) : null}

      {tab === "tasks" ? (
        <Panel className="space-y-3 p-4">
          <div className="flex justify-between gap-2">
            <h2 className="font-medium">{t("tasks")}</h2>
            {canCreateTask ? (
              <Button size="sm" onClick={() => setTaskDrawerOpen(true)}>
                {t("createTask")}
              </Button>
            ) : null}
          </div>
          {(ticket.tasks ?? []).length === 0 ? (
            <EmptyWorkspace label={tCommon("empty")} />
          ) : (
            <ul className="space-y-2 text-sm">
              {(ticket.tasks ?? []).map((task) => (
                <li key={task.id} className="flex items-center justify-between gap-2">
                  <Link href={`/tasks/${task.id}`} className="text-primary hover:underline">
                    {task.task_number} — {task.title}
                  </Link>
                  <StatusBadge status={task.status || "pending"} />
                </li>
              ))}
            </ul>
          )}
        </Panel>
      ) : null}

      {tab === "whatsapp" ? (
        <Panel className="p-4 text-sm">
          {ticket.whatsapp_conversation_id ? (
            <Link
              href={`/whatsapp/inbox?conversation=${ticket.whatsapp_conversation_id}`}
              className="text-primary hover:underline"
            >
              {t("openWhatsAppConversation", { id: ticket.whatsapp_conversation_id })}
            </Link>
          ) : (
            <EmptyWorkspace label={t("noWhatsApp")} />
          )}
        </Panel>
      ) : null}

      {tab === "attachments" ? (
        <AttachmentGallery
          items={attachmentItems}
          uploading={uploading}
          onRefresh={() => void load()}
          onUpload={
            canUpload
              ? async (file) => {
                  setUploading(true);
                  try {
                    await uploadAttachment(id, file);
                    setSuccess(t("uploaded"));
                    await load();
                  } catch (err) {
                    setError(err instanceof ApiError ? err.message : tCommon("error"));
                  } finally {
                    setUploading(false);
                  }
                }
              : undefined
          }
        />
      ) : null}

      {tab === "related" ? (
        <Panel className="p-4">
          {(ticket.open_related ?? []).length === 0 ? (
            <EmptyWorkspace label={tCommon("empty")} />
          ) : (
            <ul className="space-y-2 text-sm">
              {(ticket.open_related ?? []).map((row) => (
                <li key={row.id}>
                  <Link href={`/tickets/${row.id}`} className="text-primary hover:underline">
                    {row.ticket_number} — {row.subject}
                  </Link>
                  <StatusBadge status={row.status} />
                </li>
              ))}
            </ul>
          )}
        </Panel>
      ) : null}

      {tab === "audit" ? (
        <Panel className="p-4 text-sm text-muted">
          <RecordSummary
            items={[
              { label: t("createdAt"), value: formatDateTime(ticket.created_at, locale) },
              { label: t("updatedAt"), value: formatDateTime(ticket.updated_at, locale) },
              { label: t("resolvedAt"), value: formatDateTime(ticket.resolved_at, locale) },
              { label: t("closedAt"), value: formatDateTime(ticket.closed_at, locale) },
              { label: t("reopenedCount"), value: ticket.reopened_count ?? 0 },
            ]}
          />
        </Panel>
      ) : null}

      <ConfirmActionDialog
        open={Boolean(confirmAction)}
        title={confirmAction ? t(`confirm_${confirmAction}` as "confirm_closed") : ""}
        description={
          confirmAction
            ? t(`confirm_${confirmAction}_desc` as "confirm_closed_desc")
            : ""
        }
        danger={confirmAction === "cancelled" || confirmAction === "closed"}
        loading={busy}
        onCancel={() => setConfirmAction(null)}
        onConfirm={() => {
          if (!confirmAction) return;
          const status = confirmAction;
          void run(async () => {
            if (status === "closed") await closeTicket(id, confirmReason || undefined);
            else if (status === "reopened") await reopenTicket(id, confirmReason || undefined);
            else await transitionTicket(id, status, confirmReason || undefined);
            setConfirmAction(null);
            setConfirmReason("");
          }, t("statusUpdated"));
        }}
      />

      <Drawer
        open={taskDrawerOpen}
        title={t("createTask")}
        onClose={() => setTaskDrawerOpen(false)}
      >
        <div className="space-y-4">
          <label className="grid gap-1 text-sm">
            <span>{t("taskTitle")}</span>
            <Input value={taskTitle} onChange={(e) => setTaskTitle(e.target.value)} />
          </label>
          <AssignmentPicker
            branchId={ticket.branch_id}
            value={taskAssignment}
            onChange={setTaskAssignment}
            allowedKinds={["user", "team", "department"]}
          />
          <Button
            disabled={busy || !taskTitle.trim()}
            onClick={() =>
              void run(async () => {
                await createTask({
                  branch_id: ticket.branch_id,
                  title: taskTitle.trim(),
                  ticket_id: ticket.id,
                  customer_id: ticket.customer_id ?? undefined,
                  assignee_id:
                    taskAssignment?.kind === "user"
                      ? Number(taskAssignment.id)
                      : undefined,
                  department_id:
                    taskAssignment?.kind === "department"
                      ? Number(taskAssignment.id)
                      : undefined,
                  team_id:
                    taskAssignment?.kind === "team"
                      ? Number(taskAssignment.id)
                      : undefined,
                });
                setTaskTitle("");
                setTaskAssignment(null);
                setTaskDrawerOpen(false);
              }, t("taskCreated"))
            }
          >
            {tCommon("create")}
          </Button>
        </div>
      </Drawer>
    </div>
  );
}
