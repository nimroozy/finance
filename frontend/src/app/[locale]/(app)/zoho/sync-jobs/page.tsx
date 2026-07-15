"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listZohoSyncJobs, retryZohoSyncJob } from "@/lib/zoho";
import type { ZohoSyncJob } from "@/lib/types";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

function jobTone(
  status: string,
): "success" | "danger" | "warning" | "info" | "neutral" {
  if (status === "completed") return "success";
  if (status === "failed") return "danger";
  if (status === "partially_completed" || status === "retrying") return "warning";
  if (status === "running" || status === "pending") return "info";
  return "neutral";
}

export default function ZohoSyncJobsPage() {
  const t = useTranslations("zohoSyncJobs");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canSync = useAuthStore((s) => s.hasPermission("zoho.sync"));

  const [jobs, setJobs] = useState<ZohoSyncJob[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [retryingId, setRetryingId] = useState<number | null>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listZohoSyncJobs(page);
        setJobs(res.data);
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
    [tCommon],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  async function onRetry(id: number) {
    setRetryingId(id);
    setError(null);
    setSuccess(null);
    try {
      await retryZohoSyncJob(id);
      setSuccess(t("retrySuccess"));
      await load(meta.current_page);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setRetryingId(null);
    }
  }

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          <Link
            href="/zoho"
            className="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm hover:bg-sand-soft"
          >
            {t("backToZoho")}
          </Link>
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

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : jobs.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("id")}</th>
                  <th className="px-4 py-3 font-medium">{t("type")}</th>
                  <th className="px-4 py-3 font-medium">{t("status")}</th>
                  <th className="px-4 py-3 font-medium">{t("started")}</th>
                  <th className="px-4 py-3 font-medium">{t("finished")}</th>
                  <th className="px-4 py-3 font-medium">{t("error")}</th>
                  {canSync ? (
                    <th className="px-4 py-3 font-medium">{tCommon("actions")}</th>
                  ) : null}
                </tr>
              </thead>
              <tbody>
                {jobs.map((job) => (
                  <tr
                    key={job.id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-4 py-3 font-medium">{job.id}</td>
                    <td className="px-4 py-3">{job.type}</td>
                    <td className="px-4 py-3">
                      <Badge tone={jobTone(job.status)}>{job.status}</Badge>
                    </td>
                    <td className="px-4 py-3">
                      {formatDateTime(job.started_at, locale)}
                    </td>
                    <td className="px-4 py-3">
                      {formatDateTime(job.finished_at, locale)}
                    </td>
                    <td className="max-w-xs truncate px-4 py-3 text-danger">
                      {job.error_message || "—"}
                    </td>
                    {canSync ? (
                      <td className="px-4 py-3">
                        {job.status === "failed" ? (
                          <Button
                            variant="ghost"
                            size="sm"
                            disabled={retryingId === job.id}
                            onClick={() => void onRetry(job.id)}
                          >
                            {tCommon("retry")}
                          </Button>
                        ) : (
                          "—"
                        )}
                      </td>
                    ) : null}
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
    </div>
  );
}
