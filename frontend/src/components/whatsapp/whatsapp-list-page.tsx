"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale } from "next-intl";
import { apiFetch } from "@/lib/api";
import { Alert, EmptyState, LoadingState, PageHeader, Panel } from "@/components/ui/layout";

export function WhatsAppListPage({ title, endpoint }: { title: string; endpoint: string }) {
  const fa = useLocale() === "fa";
  const [rows, setRows] = useState<Array<Record<string, unknown>>>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const response = await apiFetch<Array<Record<string, unknown>>>(endpoint);
      setRows(response.data);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to load");
    } finally {
      setLoading(false);
    }
  }, [endpoint]);

  useEffect(() => void load(), [load]);

  return (
    <div>
      <PageHeader title={title} subtitle={fa ? "زیرساخت واتساپ کلاد API" : "WhatsApp Cloud API operations"} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {loading ? <LoadingState label={fa ? "بارگذاری…" : "Loading…"} /> : null}
      {!loading && rows.length === 0 ? <EmptyState label={fa ? "موردی موجود نیست" : "No records"} /> : null}
      <div className="space-y-3">
        {rows.map((row, index) => (
          <Panel key={String(row.id ?? index)} className="p-4 text-sm">
            <p className="font-medium">
              {String(row.template_name ?? row.name ?? row.phone_e164 ?? row.code ?? `#${row.id ?? index + 1}`)}
            </p>
            <p className="mt-1 text-muted">
              {String(row.status ?? row.event_type ?? row.message ?? row.last_message_at ?? "—")}
            </p>
          </Panel>
        ))}
      </div>
    </div>
  );
}
