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
import { LtrValue } from "@/components/ltr-value";
import { Button } from "@/components/ui/button";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function NotificationsPage() {
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
    <div className="space-y-4" data-testid="notifications-page">
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
        <div className="space-y-2">
          {rows.map((row) => (
            <Panel key={row.id} className="flex items-start justify-between gap-3 p-4">
              <div className="min-w-0">
                <p className="text-sm font-medium text-foreground">{row.title}</p>
                {row.body ? <p className="mt-1 text-sm text-muted">{row.body}</p> : null}
                <p className="mt-2 text-xs text-muted">
                  <LtrValue>{formatDateTime(row.created_at, locale)}</LtrValue>
                </p>
              </div>
              {!row.read_at ? (
                <Button variant="secondary" size="sm" onClick={() => void onMark(row.id)}>
                  {t("markRead")}
                </Button>
              ) : null}
            </Panel>
          ))}
        </div>
      )}
    </div>
  );
}
