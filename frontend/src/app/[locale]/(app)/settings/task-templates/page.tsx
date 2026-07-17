"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import {
  createTaskTemplate,
  listTaskTemplates,
  type TaskTemplate,
} from "@/lib/operations";
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

export default function TaskTemplatesPage() {
  const t = useTranslations("ticketSettings");
  const tCommon = useTranslations("common");
  const canManage = useAuthStore((s) => s.hasPermission("task_templates.manage"));

  const [rows, setRows] = useState<TaskTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [code, setCode] = useState("");
  const [name, setName] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listTaskTemplates();
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
      await createTaskTemplate({ code: code.trim(), name: name.trim() });
      setCode("");
      setName("");
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <PageHeader title={t("templatesTitle")} subtitle={t("templatesSubtitle")} />
      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      {canManage ? (
        <Panel className="mb-4 p-4">
          <form className="grid gap-3 sm:grid-cols-3" onSubmit={(e) => void onCreate(e)}>
            <Input placeholder={t("code")} value={code} onChange={(e) => setCode(e.target.value)} required />
            <Input placeholder={t("name")} value={name} onChange={(e) => setName(e.target.value)} required />
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
              <li key={row.id} className="px-4 py-3 text-sm">
                <p className="font-medium">{row.code}</p>
                <p className="text-muted">{row.name}</p>
              </li>
            ))}
          </ul>
        )}
      </Panel>
    </div>
  );
}
