"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listFinanceHolds, releaseFinanceHold, type ServiceFinanceHold } from "@/lib/services";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  WorkspaceHeader,
} from "@/components/ops";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { Alert, LoadingState } from "@/components/ui/layout";
import { StatusBadge } from "@/components/status-badge";

export default function FinanceHoldServicesPage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const [rows, setRows] = useState<ServiceFinanceHold[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listFinanceHolds({ per_page: 50 });
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

  async function onRelease(id: number) {
    setBusy(id);
    try {
      await releaseFinanceHold(id);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(null);
    }
  }

  const columns: DataTableColumn<ServiceFinanceHold>[] = [
    {
      key: "service",
      label: t("columns.service"),
      render: (row) => (
        <Link href={`/services/${row.service_id}`} className="text-primary hover:underline">
          #{row.service_id}
        </Link>
      ),
    },
    {
      key: "status",
      label: t("columns.status"),
      render: (row) => <StatusBadge status={row.status || "unknown"} />,
    },
    { key: "reason", label: t("fields.reason"), render: (row) => row.reason || "—" },
    {
      key: "actions",
      label: tCommon("actions"),
      render: (row) =>
        row.status === "active" ? (
          <Button size="sm" disabled={busy === row.id} onClick={() => void onRelease(row.id)}>
            {t("actions.releaseHold")}
          </Button>
        ) : (
          "—"
        ),
    },
  ];

  return (
    <div className="space-y-4" data-testid="services-finance-hold">
      <WorkspaceHeader title={t("financeHoldTitle")} subtitle={t("financeHoldSubtitle")} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error && !rows.length ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && rows.length === 0 ? <EmptyWorkspace label={t("emptyFinanceHolds")} /> : null}
      {!loading && rows.length > 0 ? <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} /> : null}
    </div>
  );
}
