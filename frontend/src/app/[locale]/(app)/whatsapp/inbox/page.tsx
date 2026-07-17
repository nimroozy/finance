"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError, apiFetch } from "@/lib/api";
import { getWhatsAppStatus } from "@/lib/operations";
import {
  createTicket,
  createTicketFromIntake,
  getTicket,
  listTicketIntake,
  listTicketTypes,
  updateTicket,
  type TicketIntakeSuggestion,
  type TicketType,
} from "@/lib/tickets";
import { useAuthStore } from "@/store/auth-store";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  WorkspaceHeader,
} from "@/components/ops";
import { Button } from "@/components/ui/button";
import { Input, Select } from "@/components/ui/form";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";
import { Badge as UiBadge } from "@/components/ui/badge";

type ConversationRow = {
  id: number;
  phone_e164?: string;
  status?: string;
  last_message_at?: string;
  branch_id?: number;
  customer_id?: number | null;
};

type ConversationDetail = ConversationRow & {
  customer?: {
    id: number;
    contact_name?: string;
    name?: string;
    mobile?: string;
    phone?: string;
  };
  inbound_messages?: Array<{ id: number; body?: string; received_at?: string; from_phone?: string; read_at?: string | null }>;
  inboundMessages?: Array<{ id: number; body?: string; received_at?: string; from_phone?: string; read_at?: string | null }>;
};

export default function WhatsAppInboxPage() {
  const t = useTranslations("tickets");
  const tCommon = useTranslations("common");
  const canCreate = useAuthStore((s) =>
    s.hasAnyPermission(["tickets.create", "whatsapp.ticket_intake"]),
  );

  const [rows, setRows] = useState<ConversationRow[]>([]);
  const [selected, setSelected] = useState<ConversationDetail | null>(null);
  const [suggestions, setSuggestions] = useState<TicketIntakeSuggestion[]>([]);
  const [types, setTypes] = useState<TicketType[]>([]);
  const [typeCode, setTypeCode] = useState("");
  const [linkTicketId, setLinkTicketId] = useState("");
  const [linkedTicketId, setLinkedTicketId] = useState<string | null>(null);
  const [metaDisconnected, setMetaDisconnected] = useState(false);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [inbox, status] = await Promise.all([
        apiFetch<ConversationRow[]>("/whatsapp/inbox"),
        getWhatsAppStatus().catch(() => null),
      ]);
      setRows(inbox.data);
      const st = status?.data;
      setMetaDisconnected(
        Boolean(st && (st.connected === false || st.status === "disconnected")),
      );
    } catch (e) {
      setError(e instanceof ApiError ? e.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load();
    void listTicketTypes()
      .then((res) => {
        setTypes(res.data);
        if (res.data[0]) setTypeCode(res.data[0].code);
      })
      .catch(() => setTypes([]));
  }, [load]);

  async function openConversation(id: number) {
    setError(null);
    setSuccess(null);
    setLinkedTicketId(null);
    try {
      const response = await apiFetch<ConversationDetail>(`/whatsapp/inbox/${id}`);
      setSelected(response.data);
      const intake = await listTicketIntake({ status: "pending", per_page: 50 });
      setSuggestions(
        intake.data.filter((s) => s.conversation_id === id || !s.conversation_id),
      );
      const linked = intake.data.find(
        (s) => s.conversation_id === id && s.ticket_id,
      );
      if (linked?.ticket_id) setLinkedTicketId(String(linked.ticket_id));
    } catch (e) {
      setError(e instanceof ApiError ? e.message : tCommon("error"));
    }
  }

  async function onCreateTicket() {
    if (!selected || !canCreate || busy) return;
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      const pending = suggestions.find(
        (s) => s.conversation_id === selected.id && s.status === "pending",
      );
      if (pending) {
        const res = await createTicketFromIntake(pending.id);
        setSuccess(t("ticketCreatedFromInbox"));
        if (res.data.ticket?.id) setLinkedTicketId(String(res.data.ticket.id));
      } else {
        if (!selected.branch_id || !typeCode) {
          setError(t("requiredFields"));
          return;
        }
        const res = await createTicket({
          branch_id: selected.branch_id,
          type_code: typeCode,
          subject: t("whatsappSubject", { phone: selected.phone_e164 || selected.id }),
          source: "whatsapp",
          customer_id: selected.customer_id || selected.customer?.id || null,
          whatsapp_conversation_id: selected.id,
          priority: "normal",
        });
        setSuccess(t("ticketCreatedFromInbox"));
        setLinkedTicketId(String(res.data.id));
      }
    } catch (e) {
      setError(e instanceof ApiError ? e.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onLinkTicket() {
    if (!selected || !canCreate || busy) return;
    if (!linkTicketId.trim()) {
      setError(t("linkTicketRequired"));
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      const ticketId = Number(linkTicketId);
      await getTicket(ticketId);
      await updateTicket(ticketId, {
        whatsapp_conversation_id: selected.id,
      });
      setSuccess(t("ticketLinkedFromInbox"));
      setLinkedTicketId(String(ticketId));
    } catch (e) {
      setError(e instanceof ApiError ? e.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  const messages =
    selected?.inbound_messages ??
    selected?.inboundMessages ??
    [];

  return (
    <div className="space-y-4">
      <WorkspaceHeader title={t("inboxTitle")} subtitle={t("inboxSubtitle")} />

      {metaDisconnected ? (
        <Alert tone="warning">{t("metaUnavailable")}</Alert>
      ) : null}
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}

      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {!loading && error && rows.length === 0 ? (
        <ErrorWorkspace message={error} onRetry={() => void load()} />
      ) : null}
      {!loading && rows.length === 0 && !error ? <EmptyWorkspace /> : null}

      <div className="grid gap-4 lg:grid-cols-2">
        <div className="space-y-3">
          {rows.map((row) => {
            const isOpen = row.status === "open";
            return (
              <button
                key={row.id}
                type="button"
                className="w-full text-start"
                onClick={() => void openConversation(row.id)}
              >
                <Panel className="flex items-start justify-between gap-2 p-4 text-sm hover:border-primary">
                  <div>
                    <p className="font-medium">{row.phone_e164 ?? `#${row.id}`}</p>
                    <p className="mt-1 text-muted">{row.last_message_at ?? row.status ?? "—"}</p>
                  </div>
                  <div className="flex flex-col items-end gap-1">
                    {isOpen ? <UiBadge tone="info">{t("unreadOpen")}</UiBadge> : null}
                  </div>
                </Panel>
              </button>
            );
          })}
        </div>

        {selected ? (
          <Panel className="space-y-3 p-4 text-sm">
            <div className="flex items-start justify-between gap-2">
              <p className="font-medium">{selected.phone_e164}</p>
              {linkedTicketId ? (
                <UiBadge tone="success">{t("hasLinkedTicket")}</UiBadge>
              ) : null}
            </div>
            {selected.customer ? (
              <p>
                {t("customer")}:{" "}
                {selected.customer.contact_name ??
                  selected.customer.name ??
                  selected.customer.id}
              </p>
            ) : null}
            {messages.map((msg) => (
              <div key={msg.id} className="rounded border p-2">
                <p>{msg.body ?? "—"}</p>
                <p className="text-xs text-muted">{msg.received_at ?? msg.from_phone}</p>
              </div>
            ))}

            {canCreate ? (
              <div className="space-y-2 border-t border-border pt-3">
                <Select value={typeCode} onChange={(e) => setTypeCode(e.target.value)}>
                  <option value="">{t("type")}</option>
                  {types.map((ty) => (
                    <option key={ty.code} value={ty.code}>
                      {ty.name_en}
                    </option>
                  ))}
                </Select>
                <div className="flex flex-wrap gap-2">
                  <Button disabled={busy} onClick={() => void onCreateTicket()}>
                    {t("createTicket")}
                  </Button>
                  <Button
                    variant="secondary"
                    disabled={busy}
                    onClick={() => void onLinkTicket()}
                  >
                    {t("linkTicket")}
                  </Button>
                </div>
                <Input
                  type="number"
                  placeholder={t("linkTicketId")}
                  value={linkTicketId}
                  onChange={(e) => setLinkTicketId(e.target.value)}
                />
                {linkedTicketId ? (
                  <Link href={`/tickets/${linkedTicketId}`} className="text-primary hover:underline">
                    {t("openTicket")} #{linkedTicketId}
                  </Link>
                ) : null}
              </div>
            ) : null}
          </Panel>
        ) : null}
      </div>
    </div>
  );
}
