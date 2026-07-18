"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { createTicketType, updateTicketType } from "@/lib/operations";
import { listTicketTypes, type TicketType } from "@/lib/tickets";
import { useAuthStore } from "@/store/auth-store";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function TicketTypesSettingsPage() {
  const t = useTranslations("ticketSettings");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canManage = useAuthStore((s) => s.hasPermission("ticket_types.manage"));

  const [rows, setRows] = useState<TicketType[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [code, setCode] = useState("");
  const [nameEn, setNameEn] = useState("");
  const [nameFa, setNameFa] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listTicketTypes();
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

  async function onCreate(e: React.FormEvent) {
    e.preventDefault();
    if (!canManage) return;
    setBusy(true);
    setError(null);
    try {
      await createTicketType({
        code: code.trim(),
        name_en: nameEn.trim(),
        name_fa: nameFa.trim() || undefined,
      });
      setCode("");
      setNameEn("");
      setNameFa("");
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <PageHeader title={t("ticketTypesTitle")} subtitle={t("ticketTypesSubtitle")} />
      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      {canManage ? (
        <Panel className="mb-4 p-4">
          <form className="grid gap-3 sm:grid-cols-4" onSubmit={(e) => void onCreate(e)}>
            <Input placeholder={t("code")} value={code} onChange={(e) => setCode(e.target.value)} required />
            <Input placeholder={t("nameEn")} value={nameEn} onChange={(e) => setNameEn(e.target.value)} required />
            <Input placeholder={t("nameFa")} value={nameFa} onChange={(e) => setNameFa(e.target.value)} />
            <Button type="submit" disabled={busy}>
              {tCommon("create")}
            </Button>
          </form>
        </Panel>
      ) : null}
      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : rows.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <ul className="divide-y divide-border">
            {rows.map((row) => (
              <li key={row.id} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                <div>
                  <p className="font-medium">{row.code}</p>
                  <p className="text-muted">
                    {locale === "fa" ? row.name_fa || row.name_en : row.name_en}
                  </p>
                </div>
                {canManage ? (
                  <Button
                    size="sm"
                    variant="secondary"
                    disabled={busy}
                    onClick={() =>
                      void (async () => {
                        setBusy(true);
                        try {
                          await updateTicketType(row.id, { is_active: !row.is_active });
                          await load();
                        } catch (err) {
                          setError(err instanceof ApiError ? err.message : tCommon("error"));
                        } finally {
                          setBusy(false);
                        }
                      })()
                    }
                  >
                    {row.is_active === false ? tCommon("active") : tCommon("disable")}
                  </Button>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </Panel>
    </div>
  );
}
