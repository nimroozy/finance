"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { createWorkLog } from "@/lib/tickets";
import {
  acceptTask,
  arriveTask,
  blockTask,
  completeTask,
  getTask,
  startTask,
  startTravelTask,
  uploadTaskAttachment,
  type Task,
} from "@/lib/tasks";
import { useAuthStore } from "@/store/auth-store";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Input, TextArea } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function TaskDetailPage() {
  const t = useTranslations("tasks");
  const tCommon = useTranslations("common");
  const params = useParams();
  const id = String(params.id || "");

  const canAccept = useAuthStore((s) => s.hasPermission("tasks.accept"));
  const canComplete = useAuthStore((s) => s.hasPermission("tasks.complete"));
  const canUpload = useAuthStore((s) => s.hasPermission("attachments.upload"));

  const [task, setTask] = useState<Task | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [note, setNote] = useState("");
  const [blockReason, setBlockReason] = useState("");

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await getTask(id);
      setTask(res.data);
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
  if (!task) {
    return (
      <div>
        {error ? <Alert>{error}</Alert> : <EmptyState label={tCommon("empty")} />}
      </div>
    );
  }

  const status = task.status;
  const showAccept = canAccept && (status === "offered" || status === "pending");
  const showTravel =
    canComplete && (status === "accepted" || status === "scheduled");
  const showArrive = canComplete && status === "travelling";
  const showStart =
    canComplete && (status === "arrived" || status === "accepted" || status === "scheduled");
  const showComplete = canComplete && status === "in_progress";
  const showBlock =
    canComplete &&
    !["completed", "approved", "cancelled", "failed"].includes(status);

  return (
    <div className="mx-auto max-w-lg">
      <PageHeader
        title={task.task_number}
        subtitle={task.title}
        actions={<StatusBadge status={task.status} />}
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

      <Panel className="mb-4 space-y-2 p-4 text-sm">
        <p className="whitespace-pre-wrap">{task.description || "—"}</p>
        <p className="text-muted">
          {t("priority")}: {task.priority}
        </p>
        {task.ticket_id ? (
          <p>
            <Link href={`/tickets/${task.ticket_id}`} className="text-primary hover:underline">
              {t("linkedTicket")} #{task.ticket_id}
            </Link>
          </p>
        ) : null}
      </Panel>

      <div className="grid gap-3">
        {showAccept ? (
          <Button
            className="h-14 text-base"
            disabled={busy}
            onClick={() => void run(() => acceptTask(id), t("accepted"))}
          >
            {t("actionAccept")}
          </Button>
        ) : null}
        {showTravel ? (
          <Button
            className="h-14 text-base"
            disabled={busy}
            onClick={() => void run(() => startTravelTask(id), t("travelStarted"))}
          >
            {t("actionStartTravel")}
          </Button>
        ) : null}
        {showArrive ? (
          <Button
            className="h-14 text-base"
            disabled={busy}
            onClick={() => void run(() => arriveTask(id), t("arrived"))}
          >
            {t("actionArrived")}
          </Button>
        ) : null}
        {showStart ? (
          <Button
            className="h-14 text-base"
            disabled={busy}
            onClick={() => void run(() => startTask(id), t("workStarted"))}
          >
            {t("actionStartWork")}
          </Button>
        ) : null}
        {canUpload ? (
          <label className="block">
            <span className="sr-only">{t("actionUploadPhoto")}</span>
            <Input
              type="file"
              accept="image/*"
              capture="environment"
              className="h-14"
              disabled={busy}
              onChange={(e) => {
                const file = e.target.files?.[0];
                if (!file) return;
                void run(
                  () => uploadTaskAttachment(id, file, "photo"),
                  t("photoUploaded"),
                );
              }}
            />
            <p className="mt-1 text-center text-sm text-muted">{t("actionUploadPhoto")}</p>
          </label>
        ) : null}
        <Panel className="space-y-2 p-3">
          <TextArea
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder={t("actionAddNote")}
            rows={2}
          />
          <Button
            className="h-12 w-full"
            variant="secondary"
            disabled={busy || !note.trim() || !canComplete}
            onClick={() =>
              void run(async () => {
                await createWorkLog({
                  task_id: Number(id),
                  internal_note: note.trim(),
                });
                setNote("");
              }, t("noteAdded"))
            }
          >
            {t("actionAddNote")}
          </Button>
        </Panel>
        {showBlock ? (
          <Panel className="space-y-2 p-3">
            <TextArea
              value={blockReason}
              onChange={(e) => setBlockReason(e.target.value)}
              placeholder={t("blockReason")}
              rows={2}
            />
            <Button
              className="h-12 w-full"
              variant="danger"
              disabled={busy || !blockReason.trim()}
              onClick={() =>
                void run(async () => {
                  await blockTask(id, blockReason.trim());
                  setBlockReason("");
                }, t("blocked"))
              }
            >
              {t("actionMarkBlocked")}
            </Button>
          </Panel>
        ) : null}
        {showComplete ? (
          <Button
            className="h-14 text-base"
            disabled={busy}
            onClick={() => void run(() => completeTask(id), t("completed"))}
          >
            {t("actionComplete")}
          </Button>
        ) : null}
      </div>
    </div>
  );
}
