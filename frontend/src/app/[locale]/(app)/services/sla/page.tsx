"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import {
  createServiceSlaTemplate,
  deactivateServiceSlaTemplate,
  listServiceSlaTemplates,
  updateServiceSlaTemplate,
  type ServiceSlaTemplate,
} from "@/lib/services";
import { useAuthStore } from "@/store/auth-store";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  WorkspaceHeader,
} from "@/components/ops";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/form";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

export default function ServiceSlaPage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const canManage = useAuthStore((s) => s.hasPermission("services.sla.manage"));
  const [rows, setRows] = useState<ServiceSlaTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [name, setName] = useState("");
  const [code, setCode] = useState("");
  const [responseMinutes, setResponseMinutes] = useState("60");
  const [resolveMinutes, setResolveMinutes] = useState("240");
  const [editId, setEditId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listServiceSlaTemplates();
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!canManage || !name.trim()) return;
    setBusy(true);
    setError(null);
    try {
      const payload = {
        name: name.trim(),
        code: code.trim() || undefined,
        response_minutes: Number(responseMinutes) || 60,
        resolution_minutes: Number(resolveMinutes) || 240,
      };
      if (editId) {
        await updateServiceSlaTemplate(editId, payload);
      } else {
        await createServiceSlaTemplate(payload);
      }
      setName("");
      setCode("");
      setEditId(null);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onDeactivate(id: number) {
    setBusy(true);
    try {
      await deactivateServiceSlaTemplate(id);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  const columns: DataTableColumn<ServiceSlaTemplate>[] = [
    { key: "name", label: t("columns.name"), render: (row) => row.name },
    { key: "code", label: t("columns.code"), render: (row) => row.code || "—" },
    {
      key: "response",
      label: t("fields.responseMinutes"),
      render: (row) => String(row.response_minutes ?? "—"),
    },
    {
      key: "resolve",
      label: t("fields.resolveMinutes"),
      render: (row) => String(row.resolution_minutes ?? row.resolve_minutes ?? "—"),
    },
    {
      key: "actions",
      label: tCommon("actions"),
      render: (row) =>
        canManage ? (
          <div className="flex gap-2">
            <Button
              size="sm"
              variant="secondary"
              onClick={() => {
                setEditId(row.id);
                setName(row.name);
                setCode(row.code || "");
                setResponseMinutes(String(row.response_minutes ?? 60));
                setResolveMinutes(String(row.resolution_minutes ?? row.resolve_minutes ?? 240));
              }}
            >
              {t("actions.saveSla")}
            </Button>
            {row.is_active !== false ? (
              <Button size="sm" variant="secondary" disabled={busy} onClick={() => void onDeactivate(row.id)}>
                {t("actions.deactivateSla")}
              </Button>
            ) : null}
          </div>
        ) : (
          "—"
        ),
    },
  ];

  return (
    <div className="space-y-4" data-testid="services-sla">
      <WorkspaceHeader title={t("slaTitle")} subtitle={t("slaSubtitle")} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {canManage ? (
        <Panel className="p-4">
          <form className="grid gap-3 sm:grid-cols-2" onSubmit={onSubmit}>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("columns.name")}</span>
              <Input value={name} onChange={(e) => setName(e.target.value)} required />
            </label>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("columns.code")}</span>
              <Input value={code} onChange={(e) => setCode(e.target.value)} />
            </label>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("fields.responseMinutes")}</span>
              <Input type="number" value={responseMinutes} onChange={(e) => setResponseMinutes(e.target.value)} />
            </label>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("fields.resolveMinutes")}</span>
              <Input type="number" value={resolveMinutes} onChange={(e) => setResolveMinutes(e.target.value)} />
            </label>
            <div className="flex items-end gap-2">
              <Button type="submit" disabled={busy}>
                {editId ? t("actions.saveSla") : t("actions.newSla")}
              </Button>
            </div>
          </form>
        </Panel>
      ) : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error && !rows.length ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyWorkspace label={t("emptySla")} /> : null}
      {!loading && rows.length > 0 ? (
        <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} />
      ) : null}
    </div>
  );
}
