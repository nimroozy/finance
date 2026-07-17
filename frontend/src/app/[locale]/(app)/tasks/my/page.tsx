"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listMyTasks, type Task } from "@/lib/tasks";
import { formatDateTime } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

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
    <div>
      <PageHeader title={t("myTitle")} subtitle={t("mySubtitle")} />
      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : rows.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="space-y-3 p-3">
            {rows.map((row) => (
              <Link
                key={row.id}
                href={`/tasks/${row.id}`}
                className="block rounded-md border border-border p-4 hover:border-primary"
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-medium text-primary">{row.task_number}</p>
                    <p className="mt-1 text-sm">{row.title}</p>
                    <p className="mt-1 text-xs text-muted">
                      {formatDateTime(row.due_at, locale)}
                    </p>
                  </div>
                  <StatusBadge status={row.status} />
                </div>
              </Link>
            ))}
          </div>
        )}
        {meta.last_page > 1 ? (
          <div className="flex items-center justify-between gap-3 border-t border-border px-4 py-3">
            <p className="text-sm text-muted">
              {tCommon("page", { page: meta.current_page, total: meta.last_page })}
            </p>
            <div className="flex gap-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page <= 1}
                onClick={() => void load(meta.current_page - 1)}
              >
                {tCommon("previous")}
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page >= meta.last_page}
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
