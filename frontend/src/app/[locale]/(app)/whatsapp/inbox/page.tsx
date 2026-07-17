"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale } from "next-intl";
import { apiFetch } from "@/lib/api";
import { Alert, EmptyState, LoadingState, PageHeader, Panel } from "@/components/ui/layout";

type ConversationRow = {
  id: number;
  phone_e164?: string;
  status?: string;
  last_message_at?: string;
};

type ConversationDetail = ConversationRow & {
  customer?: { id: number; name?: string; mobile?: string; phone?: string };
  inbound_messages?: Array<{ id: number; body?: string; received_at?: string; from_phone?: string }>;
};

export default function WhatsAppInboxPage() {
  const fa = useLocale() === "fa";
  const [rows, setRows] = useState<ConversationRow[]>([]);
  const [selected, setSelected] = useState<ConversationDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const response = await apiFetch<ConversationRow[]>("/whatsapp/inbox");
      setRows(response.data);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to load");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => void load(), [load]);

  async function openConversation(id: number) {
    try {
      const response = await apiFetch<ConversationDetail>(`/whatsapp/inbox/${id}`);
      setSelected(response.data);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to load conversation");
    }
  }

  return (
    <div>
      <PageHeader
        title={fa ? "پیام‌های ورودی واتساپ" : "WhatsApp inbound"}
        subtitle={
          fa
            ? "ذخیره پایه ورودی — پشتیبانی کامل تیکت در مرحله ۷"
            : "Basic inbound storage — full support desk arrives in Stage 7 ticketing"
        }
      />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {loading ? <LoadingState label={fa ? "بارگذاری…" : "Loading…"} /> : null}
      {!loading && rows.length === 0 ? <EmptyState label={fa ? "موردی موجود نیست" : "No records"} /> : null}
      <div className="grid gap-4 lg:grid-cols-2">
        <div className="space-y-3">
          {rows.map((row) => (
            <button key={row.id} type="button" className="w-full text-left" onClick={() => void openConversation(row.id)}>
              <Panel className="p-4 text-sm hover:border-primary">
                <p className="font-medium">{row.phone_e164 ?? `#${row.id}`}</p>
                <p className="mt-1 text-muted">{row.status ?? row.last_message_at ?? "—"}</p>
              </Panel>
            </button>
          ))}
        </div>
        {selected ? (
          <Panel className="space-y-3 p-4 text-sm">
            <p className="font-medium">{selected.phone_e164}</p>
            {selected.customer ? (
              <p>{fa ? "مشتری" : "Customer"}: {selected.customer.name ?? selected.customer.id}</p>
            ) : null}
            {(selected.inbound_messages ?? (selected as ConversationDetail & { inboundMessages?: ConversationDetail["inbound_messages"] }).inboundMessages ?? []).map((msg) => (
              <div key={msg.id} className="rounded border p-2">
                <p>{msg.body ?? "—"}</p>
                <p className="text-muted text-xs">{msg.received_at ?? msg.from_phone}</p>
              </div>
            ))}
          </Panel>
        ) : null}
      </div>
    </div>
  );
}
