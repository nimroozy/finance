"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  INSTALLATION_STATUSES,
  getInstallation,
  transitionInstallation,
  type Installation,
} from "@/lib/installations";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function InstallationDetailPage() {
  const t = useTranslations("installations");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const params = useParams();
  const id = String(params.id || "");
  const canUpdate = useAuthStore((s) => s.hasPermission("installations.update"));

  const [row, setRow] = useState<Installation | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [nextStatus, setNextStatus] = useState("");

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await getInstallation(id);
      setRow(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onTransition() {
    if (!nextStatus) return;
    setBusy(true);
    setError(null);
    try {
      await transitionInstallation(id, nextStatus);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (!row) {
    return (
      <div>
        {error ? <Alert>{error}</Alert> : <EmptyState label={tCommon("empty")} />}
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={row.installation_number}
        subtitle={row.contact_name || row.prospect_name || t("detail")}
        actions={<StatusBadge status={row.status} />}
      />
      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      <div className="grid gap-4 lg:grid-cols-2">
        <Panel className="space-y-2 p-4 text-sm">
          <p>
            <span className="text-muted">{t("phone")}: </span>
            {row.phone || "—"}
          </p>
          <p>
            <span className="text-muted">{t("address")}: </span>
            {row.address || "—"}
          </p>
          <p>
            <span className="text-muted">{t("package")}: </span>
            {row.requested_package || "—"}
          </p>
          <p>
            <span className="text-muted">{t("requestedDate")}: </span>
            {row.requested_date || "—"}
          </p>
          <p>
            <span className="text-muted">{t("createdAt")}: </span>
            {formatDateTime(row.created_at, locale)}
          </p>
          <p className="whitespace-pre-wrap">{row.notes || "—"}</p>
        </Panel>
        <Panel className="space-y-3 p-4">
          <h2 className="font-medium">{t("tasks")}</h2>
          {(row.tasks ?? []).length === 0 ? (
            <EmptyState label={tCommon("empty")} />
          ) : (
            <ul className="space-y-2 text-sm">
              {(row.tasks ?? []).map((task) => (
                <li key={task.id}>
                  <Link href={`/tasks/${task.id}`} className="text-primary hover:underline">
                    {task.task_number || `#${task.id}`} — {task.title}
                  </Link>
                </li>
              ))}
            </ul>
          )}
          {canUpdate ? (
            <div className="flex flex-wrap gap-2 pt-2">
              <Select value={nextStatus} onChange={(e) => setNextStatus(e.target.value)}>
                <option value="">{t("changeStatus")}</option>
                {INSTALLATION_STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {s.replace(/_/g, " ")}
                  </option>
                ))}
              </Select>
              <Button disabled={busy || !nextStatus} onClick={() => void onTransition()}>
                {tCommon("apply")}
              </Button>
            </div>
          ) : null}
        </Panel>
      </div>
    </div>
  );
}
