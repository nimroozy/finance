"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import {
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from "@/lib/notifications";
import type { AppNotification } from "@/lib/types";
import { formatDateTime } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CollectorNotificationsPage() {
  const t = useTranslations("collectorNotifications");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [rows, setRows] = useState<AppNotification[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listNotifications({ per_page: 40 });
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onMarkAll() {
    try {
      await markAllNotificationsRead();
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    }
  }

  async function onMark(id: number) {
    try {
      await markNotificationRead(id);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    }
  }

  return (
    <div className="space-y-4">
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          <Button variant="secondary" className="h-11" onClick={() => void onMarkAll()}>
            {t("markAll")}
          </Button>
        }
      />

      {error ? <Alert>{error}</Alert> : null}

      {loading ? (
        <LoadingState label={tCommon("loading")} />
      ) : rows.length === 0 ? (
        <EmptyState label={t("empty")} />
      ) : (
        <div className="space-y-3">
          {rows.map((row) => (
            <Panel
              key={row.id}
              className={`p-4 ${row.read_at ? "" : "border-primary/40"}`}
            >
              <div className="flex items-start justify-between gap-2">
                <div>
                  <p className="font-semibold">{row.title}</p>
                  <p className="mt-1 text-sm text-muted">{row.body}</p>
                  <p className="mt-2 text-xs text-muted">
                    {formatDateTime(row.created_at, locale)}
                    {!row.read_at ? ` · ${t("unread")}` : ""}
                  </p>
                </div>
                {!row.read_at ? (
                  <Button
                    size="sm"
                    variant="secondary"
                    onClick={() => void onMark(row.id)}
                  >
                    {t("markRead")}
                  </Button>
                ) : null}
              </div>
            </Panel>
          ))}
        </div>
      )}
    </div>
  );
}
