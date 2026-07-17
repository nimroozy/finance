"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  getInstallation,
  transitionInstallation,
  type Installation,
} from "@/lib/installations";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import {
  ErrorWorkspace,
  RecordSummary,
  StatusActionMenu,
  WorkspaceHeader,
  type StatusTransition,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Badge } from "@/components/ui/badge";
import { Alert, EmptyState, LoadingState, Panel } from "@/components/ui/layout";

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

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await getInstallation(id);
      setRow(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setRow(null);
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  const transitions: StatusTransition[] = useMemo(() => {
    return (row?.allowed_transitions ?? []).map((tr) => ({
      id: tr.status,
      label: tr.label,
      danger: tr.status === "cancelled",
      requiresReason: Boolean(tr.requires_reason),
    }));
  }, [row?.allowed_transitions]);

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (!row) {
    return <ErrorWorkspace message={error ?? tCommon("empty")} onRetry={() => void load()} />;
  }

  const equipmentConfirmed =
    row.equipment_confirmed || row.equipment_status === "confirmed";
  const radiusConfirmed = Boolean(row.radius_confirmed);

  return (
    <div className="space-y-4">
      <WorkspaceHeader
        title={row.installation_number}
        subtitle={row.contact_name || row.prospect_name || t("detail")}
        badges={[{
          label: row.status.replace(/_/g, " "),
          tone: "info",
        }]}
        actions={<StatusBadge status={row.status} />}
      />

      {error ? <Alert>{error}</Alert> : null}

      <div className="flex flex-wrap gap-2">
        <Badge tone={equipmentConfirmed ? "success" : "warning"}>
          {t("equipmentConfirm")}: {equipmentConfirmed ? t("confirmed") : t("pendingConfirm")}
        </Badge>
        <Badge tone={radiusConfirmed ? "success" : "warning"}>
          {t("radiusConfirm")}: {radiusConfirmed ? t("confirmed") : t("pendingConfirm")}
        </Badge>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel className="p-4">
          <RecordSummary
            items={[
              { label: t("phone"), value: row.phone || "—" },
              { label: t("address"), value: row.address || "—" },
              { label: t("package"), value: row.requested_package || "—" },
              { label: t("requestedDate"), value: row.requested_date || "—" },
              { label: t("createdAt"), value: formatDateTime(row.created_at, locale) },
              { label: t("technician"), value: row.technician?.name || "—" },
            ]}
          />
          <p className="mt-4 whitespace-pre-wrap text-sm">{row.notes || "—"}</p>
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
            <div className="border-t border-border pt-3">
              <StatusActionMenu
                transitions={transitions}
                loading={busy}
                onTransition={(status, reason) => {
                  setBusy(true);
                  setError(null);
                  void transitionInstallation(id, status, reason)
                    .then(() => load())
                    .catch((err) =>
                      setError(err instanceof ApiError ? err.message : tCommon("error")),
                    )
                    .finally(() => setBusy(false));
                }}
              />
            </div>
          ) : null}
        </Panel>
      </div>
    </div>
  );
}
