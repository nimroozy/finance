"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { createWorkLog } from "@/lib/tickets";
import {
  acceptTask,
  arriveTask,
  blockTask,
  cancelTask,
  completeTask,
  getTask,
  listTaskAttachments,
  rejectTask,
  reassignTask,
  startTask,
  startTravelTask,
  uploadTaskAttachment,
  verifyTask,
  type Task,
} from "@/lib/tasks";
import type { AttachmentSummary } from "@/lib/tickets";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import {
  ActivityComposer,
  AssignmentPicker,
  AttachmentGallery,
  ErrorWorkspace,
  RecordSummary,
  StatusActionMenu,
  WorkspaceHeader,
  type AssignmentValue,
  type AttachmentItem,
  type StatusTransition,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { TextArea } from "@/components/ui/form";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

const ACTION_BY_STATUS: Record<
  string,
  (id: string, reason?: string) => Promise<unknown>
> = {
  accepted: (id, reason) => acceptTask(id, reason),
  rejected: (id, reason) => rejectTask(id, reason || "Rejected"),
  travelling: (id, reason) => startTravelTask(id, reason),
  arrived: (id, reason) => arriveTask(id, reason),
  in_progress: (id, reason) => startTask(id, reason),
  completed: (id, reason) => completeTask(id, reason),
  blocked: (id, reason) => blockTask(id, reason || "Blocked"),
  approved: (id, reason) => verifyTask(id, reason),
  verification_pending: (id, reason) => verifyTask(id, reason),
  cancelled: (id, reason) => cancelTask(id, reason || "Cancelled"),
};

export default function TaskDetailPage() {
  const t = useTranslations("tasks");
  const tCommon = useTranslations("common");
  const params = useParams();
  const id = String(params.id || "");

  const canUpload = useAuthStore((s) => s.hasPermission("attachments.upload"));
  const canReassign = useAuthStore((s) => s.hasPermission("tasks.reassign") || s.hasPermission("tasks.assign"));

  const [task, setTask] = useState<Task | null>(null);
  const [attachments, setAttachments] = useState<AttachmentSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [reassignValue, setReassignValue] = useState<AssignmentValue>(null);
  const [reassignReason, setReassignReason] = useState("");

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const [taskRes, attRes] = await Promise.all([
        getTask(id),
        listTaskAttachments(id).catch(() => ({ data: [] as AttachmentSummary[] })),
      ]);
      setTask(taskRes.data);
      setAttachments(
        attRes.data.length ? attRes.data : taskRes.data.attachments_summary ?? [],
      );
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setTask(null);
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

  const transitions: StatusTransition[] = useMemo(() => {
    return (task?.allowed_transitions ?? []).map((tr) => ({
      id: tr.status,
      label: tr.label,
      danger: ["cancelled", "rejected", "failed", "blocked"].includes(tr.status),
      requiresReason: Boolean(tr.requires_reason),
    }));
  }, [task?.allowed_transitions]);

  const attachmentItems: AttachmentItem[] = attachments.map((a) => ({
    id: String(a.id),
    name: a.original_name,
    downloadUrl: a.download_url,
    meta: formatDateTime(a.created_at),
  }));

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (!task) {
    return <ErrorWorkspace message={error ?? tCommon("empty")} onRetry={() => void load()} />;
  }

  return (
    <div className="mx-auto max-w-lg space-y-4">
      <WorkspaceHeader
        title={task.task_number}
        subtitle={task.title}
        actions={<StatusBadge status={task.status} />}
      />

      {error ? <Alert>{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}

      <Panel className="space-y-3 p-4 text-sm">
        <RecordSummary
          columns={1}
          items={[
            { label: t("priority"), value: task.priority },
            { label: t("type"), value: task.type },
            { label: t("assignee"), value: task.assignee?.name || "—" },
            { label: t("dueAt"), value: formatDateTime(task.due_at) },
          ]}
        />
        <p className="whitespace-pre-wrap">{task.description || "—"}</p>
        {task.ticket_id ? (
          <Link href={`/tickets/${task.ticket_id}`} className="text-primary hover:underline">
            {t("linkedTicket")} {task.ticket?.ticket_number || `#${task.ticket_id}`}
          </Link>
        ) : null}
      </Panel>

      <StatusActionMenu
        large
        transitions={transitions}
        loading={busy}
        onTransition={(status, reason) =>
          run(async () => {
            const fn = ACTION_BY_STATUS[status];
            if (fn) await fn(id, reason);
            else throw new Error(t("impossibleAction"));
          }, t("statusUpdated"))
        }
      />

      {canReassign ? (
        <Panel className="space-y-3 p-4">
          <p className="text-sm font-medium">{t("reassign")}</p>
          <AssignmentPicker
            branchId={task.branch_id}
            value={reassignValue}
            onChange={setReassignValue}
            allowedKinds={["user"]}
          />
          <TextArea
            value={reassignReason}
            onChange={(e) => setReassignReason(e.target.value)}
            placeholder={t("reassignReason")}
            rows={2}
          />
          <Button
            className="h-12 w-full"
            variant="secondary"
            disabled={busy || reassignValue?.kind !== "user" || !reassignReason.trim()}
            onClick={() =>
              void run(
                () =>
                  reassignTask(id, Number(reassignValue!.id), reassignReason.trim()),
                t("reassigned"),
              )
            }
          >
            {t("reassign")}
          </Button>
        </Panel>
      ) : null}

      <Panel className="space-y-3 p-4">
        <ActivityComposer
          loading={busy}
          onSubmit={({ type, body }) =>
            run(async () => {
              await createWorkLog({
                task_id: Number(id),
                internal_note: type === "internal" ? body : undefined,
                customer_visible_note: type === "customer_visible" ? body : undefined,
              });
            }, t("noteAdded"))
          }
        />
      </Panel>

      <AttachmentGallery
        items={attachmentItems}
        uploading={uploading}
        onRefresh={() => void load()}
        onUpload={
          canUpload
            ? async (file) => {
                setUploading(true);
                try {
                  await uploadTaskAttachment(id, file, "photo");
                  setSuccess(t("photoUploaded"));
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
    </div>
  );
}
