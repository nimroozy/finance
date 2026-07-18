"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  approveCancellation,
  completeCancellation,
  listCancellations,
  recoverCancellationEquipment,
  type ServiceCancellation,
} from "@/lib/services";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  WorkspaceHeader,
} from "@/components/ops";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { Alert, LoadingState } from "@/components/ui/layout";
import { StatusBadge } from "@/components/status-badge";

export default function CancellationsPage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const [rows, setRows] = useState<ServiceCancellation[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listCancellations({ per_page: 50 });
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

  async function run(id: number, action: "approve" | "recover" | "complete") {
    setBusy(id);
    setError(null);
    try {
      if (action === "approve") await approveCancellation(id);
      else if (action === "recover") await recoverCancellationEquipment(id, {});
      else await completeCancellation(id);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(null);
    }
  }

  const columns: DataTableColumn<ServiceCancellation>[] = [
    {
      key: "service",
      label: t("columns.service"),
      render: (row) =>
        row.service ? (
          <Link href={`/services/${row.service_id}`} className="text-primary hover:underline">
            {row.service.service_number}
          </Link>
        ) : (
          `#${row.service_id}`
        ),
    },
    {
      key: "status",
      label: t("columns.status"),
      render: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: "reason",
      label: t("fields.reason"),
      render: (row) => row.reason || "—",
    },
    {
      key: "actions",
      label: tCommon("actions"),
      render: (row) => (
        <div className="flex flex-wrap gap-2" data-testid={`cancellation-row-${row.id}`}>
          {row.status === "requested" ? (
            <Button size="sm" disabled={busy === row.id} onClick={() => void run(row.id, "approve")}>
              {t("actions.approveCancellation")}
            </Button>
          ) : null}
          {row.status === "equipment_recovery" || row.status === "approved" ? (
            <Button size="sm" disabled={busy === row.id} onClick={() => void run(row.id, "recover")}>
              {t("actions.recoverEquipment")}
            </Button>
          ) : null}
          {row.status !== "completed" && row.status !== "rejected" ? (
            <Button
              size="sm"
              variant="secondary"
              disabled={busy === row.id}
              onClick={() => void run(row.id, "complete")}
            >
              {t("actions.completeCancellation")}
            </Button>
          ) : null}
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-4" data-testid="services-cancellations">
      <WorkspaceHeader title={t("cancellationsTitle")} subtitle={t("cancellationsSubtitle")} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error && !rows.length ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && rows.length === 0 ? <EmptyWorkspace label={t("emptyCancellations")} /> : null}
      {!loading && rows.length > 0 ? (
        <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} />
      ) : null}
    </div>
  );
}
