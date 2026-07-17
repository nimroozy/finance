"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { listServiceSlaTemplates, type ServiceSlaTemplate } from "@/lib/services";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  WorkspaceHeader,
} from "@/components/ops";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { LoadingState } from "@/components/ui/layout";

export default function ServiceSlaPage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const [rows, setRows] = useState<ServiceSlaTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

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
      render: (row) => String(row.resolve_minutes ?? "—"),
    },
  ];

  return (
    <div className="space-y-4" data-testid="services-sla">
      <WorkspaceHeader title={t("slaTitle")} subtitle={t("slaSubtitle")} />
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? <EmptyWorkspace label={t("emptySla")} /> : null}
      {!loading && !error && rows.length > 0 ? (
        <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} />
      ) : null}
    </div>
  );
}
