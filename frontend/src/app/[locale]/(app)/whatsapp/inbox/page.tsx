"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError, apiFetch } from "@/lib/api";
import {
  createTicket,
  createTicketFromIntake,
  listTicketIntake,
  listTicketTypes,
  type TicketIntakeSuggestion,
  type TicketType,
} from "@/lib/tickets";
import { useAuthStore } from "@/store/auth-store";
import { Button } from "@/components/ui/button";
import { Input, Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

type ConversationRow = {
  id: number;
  phone_e164?: string;
  status?: string;
  last_message_at?: string;
  branch_id?: number;
  customer_id?: number | null;
};

type ConversationDetail = ConversationRow & {
  customer?: { id: number; name?: string; mobile?: string; phone?: string };
  inbound_messages?: Array<{ id: number; body?: string; received_at?: string; from_phone?: string }>;
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
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const response = await apiFetch<ConversationRow[]>("/whatsapp/inbox");
      setRows(response.data);
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
    try {
      const response = await apiFetch<ConversationDetail>(`/whatsapp/inbox/${id}`);
      setSelected(response.data);
      const intake = await listTicketIntake({ status: "pending", per_page: 50 });
      setSuggestions(
        intake.data.filter((s) => s.conversation_id === id || !s.conversation_id),
      );
    } catch (e) {
      setError(e instanceof ApiError ? e.message : tCommon("error"));
    }
  }

  async function onCreateTicket() {
    if (!selected || !canCreate) return;
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
        if (res.data.ticket?.id) {
          setLinkTicketId(String(res.data.ticket.id));
        }
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
        setLinkTicketId(String(res.data.id));
      }
    } catch (e) {
      setError(e instanceof ApiError ? e.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onLinkTicket() {
    if (!selected || !canCreate) return;
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      const pending = suggestions.find(
        (s) => s.conversation_id === selected.id && s.status === "pending",
      );
      if (pending) {
        const res = await createTicketFromIntake(pending.id);
        setSuccess(t("ticketLinkedFromInbox"));
        if (res.data.ticket?.id) setLinkTicketId(String(res.data.ticket.id));
      } else if (linkTicketId) {
        // Intake link path: create from conversation context when no suggestion exists.
        if (!selected.branch_id || !typeCode) {
          setError(t("requiredFields"));
          return;
        }
        await createTicket({
          branch_id: selected.branch_id,
          type_code: typeCode,
          subject: t("whatsappLinkSubject", { id: linkTicketId }),
          source: "whatsapp",
          customer_id: selected.customer_id || selected.customer?.id || null,
          whatsapp_conversation_id: selected.id,
          priority: "normal",
        });
        setSuccess(t("ticketLinkedFromInbox"));
      } else {
        setError(t("linkTicketRequired"));
      }
    } catch (e) {
      setError(e instanceof ApiError ? e.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <PageHeader title={t("inboxTitle")} subtitle={t("inboxSubtitle")} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {success ? (
        <div className="mb-4">
          <Alert tone="success">{success}</Alert>
        </div>
      ) : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {!loading && rows.length === 0 ? <EmptyState label={tCommon("empty")} /> : null}
      <div className="grid gap-4 lg:grid-cols-2">
        <div className="space-y-3">
          {rows.map((row) => (
            <button
              key={row.id}
              type="button"
              className="w-full text-start"
              onClick={() => void openConversation(row.id)}
            >
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
              <p>
                {t("customer")}: {selected.customer.name ?? selected.customer.id}
              </p>
            ) : null}
            {(
              selected.inbound_messages ??
              (
                selected as ConversationDetail & {
                  inboundMessages?: ConversationDetail["inbound_messages"];
                }
              ).inboundMessages ??
              []
            ).map((msg) => (
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
                {linkTicketId ? (
                  <Link href={`/tickets/${linkTicketId}`} className="text-primary hover:underline">
                    {t("openTicket")} #{linkTicketId}
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
