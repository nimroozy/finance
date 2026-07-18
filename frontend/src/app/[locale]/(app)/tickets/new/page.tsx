"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listBranches } from "@/lib/auth";
import {
  TICKET_PRIORITIES,
  TICKET_SOURCES,
  createTicket,
  listTicketTypes,
  type TicketType,
} from "@/lib/tickets";
import type { Branch } from "@/lib/types";
import { Button } from "@/components/ui/button";
import { Input, Select, TextArea } from "@/components/ui/form";
import { Alert, PageHeader, Panel } from "@/components/ui/layout";

export default function NewTicketPage() {
  const t = useTranslations("tickets");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();

  const [branches, setBranches] = useState<Branch[]>([]);
  const [types, setTypes] = useState<TicketType[]>([]);
  const [branchId, setBranchId] = useState("");
  const [typeCode, setTypeCode] = useState("");
  const [subject, setSubject] = useState("");
  const [description, setDescription] = useState("");
  const [customerId, setCustomerId] = useState("");
  const [priority, setPriority] = useState("normal");
  const [source, setSource] = useState("manual");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void listBranches(1)
      .then((res) => setBranches(res.data))
      .catch(() => setBranches([]));
    void listTicketTypes()
      .then((res) => setTypes(res.data))
      .catch(() => setTypes([]));
  }, []);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!branchId || !typeCode || !subject.trim()) {
      setError(t("requiredFields"));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const res = await createTicket({
        branch_id: Number(branchId),
        type_code: typeCode,
        subject: subject.trim(),
        description: description.trim() || undefined,
        customer_id: customerId ? Number(customerId) : null,
        priority,
        source,
      });
      router.push(`/tickets/${res.data.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <PageHeader title={t("newTitle")} subtitle={t("newSubtitle")} />
      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      <Panel className="p-4">
        <form className="grid max-w-xl gap-4" onSubmit={(e) => void onSubmit(e)}>
          <label className="grid gap-1 text-sm">
            <span>{tCommon("branch")}</span>
            <Select value={branchId} onChange={(e) => setBranchId(e.target.value)} required>
              <option value="">{tCommon("branch")}</option>
              {branches.map((b) => (
                <option key={b.id} value={b.id}>
                  {locale === "fa" ? b.name_fa || b.name_en : b.name_en}
                </option>
              ))}
            </Select>
          </label>
          <label className="grid gap-1 text-sm">
            <span>{t("type")}</span>
            <Select value={typeCode} onChange={(e) => setTypeCode(e.target.value)} required>
              <option value="">{t("type")}</option>
              {types.map((ty) => (
                <option key={ty.code} value={ty.code}>
                  {locale === "fa" ? ty.name_fa || ty.name_en : ty.name_en}
                </option>
              ))}
            </Select>
          </label>
          <label className="grid gap-1 text-sm">
            <span>{t("subject")}</span>
            <Input value={subject} onChange={(e) => setSubject(e.target.value)} required />
          </label>
          <label className="grid gap-1 text-sm">
            <span>{t("description")}</span>
            <TextArea value={description} onChange={(e) => setDescription(e.target.value)} rows={4} />
          </label>
          <label className="grid gap-1 text-sm">
            <span>{t("customerId")}</span>
            <Input
              type="number"
              value={customerId}
              onChange={(e) => setCustomerId(e.target.value)}
            />
          </label>
          <label className="grid gap-1 text-sm">
            <span>{t("priority")}</span>
            <Select value={priority} onChange={(e) => setPriority(e.target.value)}>
              {TICKET_PRIORITIES.map((p) => (
                <option key={p} value={p}>
                  {t(`priority_${p}` as "priority_low")}
                </option>
              ))}
            </Select>
          </label>
          <label className="grid gap-1 text-sm">
            <span>{t("source")}</span>
            <Select value={source} onChange={(e) => setSource(e.target.value)}>
              {TICKET_SOURCES.map((s) => (
                <option key={s} value={s}>
                  {s.replace(/_/g, " ")}
                </option>
              ))}
            </Select>
          </label>
          <div className="flex gap-2">
            <Button type="submit" disabled={busy}>
              {busy ? tCommon("loading") : tCommon("create")}
            </Button>
          </div>
        </form>
      </Panel>
    </div>
  );
}
