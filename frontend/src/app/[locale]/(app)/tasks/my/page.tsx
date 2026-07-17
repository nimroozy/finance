"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listMyTasks, type Task } from "@/lib/tasks";
import { formatDateTime } from "@/lib/utils";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  MobileRecordCard,
  WorkspaceHeader,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { LoadingState, Panel } from "@/components/ui/layout";

export default function MyTasksPage() {
  const t = useTranslations("tasks");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [rows, setRows] = useState<Task[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listMyTasks({ page });
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
    [tCommon],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  return (
    <div className="space-y-4">
      <WorkspaceHeader title={t("myTitle")} subtitle={t("mySubtitle")} />
      {error ? <ErrorWorkspace message={error} onRetry={() => void load(1)} /> : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyWorkspace /> : null}
      {!loading && rows.length > 0 ? (
        <div className="space-y-3">
          {rows.map((row) => (
            <MobileRecordCard
              key={row.id}
              href={`/tasks/${row.id}`}
              title={row.task_number}
              subtitle={row.title}
              badges={<StatusBadge status={row.status} />}
              meta={formatDateTime(row.due_at, locale)}
            />
          ))}
        </div>
      ) : null}
      {meta.last_page > 1 ? (
        <Panel className="flex items-center justify-between gap-3 px-4 py-3">
          <Button
            variant="secondary"
            size="sm"
            disabled={meta.current_page <= 1}
            onClick={() => void load(meta.current_page - 1)}
          >
            {tCommon("previous")}
          </Button>
          <p className="text-sm text-muted">
            {tCommon("page", { page: meta.current_page, total: meta.last_page })}
          </p>
          <Button
            variant="secondary"
            size="sm"
            disabled={meta.current_page >= meta.last_page}
            onClick={() => void load(meta.current_page + 1)}
          >
            {tCommon("next")}
          </Button>
        </Panel>
      ) : null}
      <p className="text-center text-sm">
        <Link href="/tasks" className="text-primary hover:underline">
          {t("viewAllTasks")}
        </Link>
      </p>
    </div>
  );
}
