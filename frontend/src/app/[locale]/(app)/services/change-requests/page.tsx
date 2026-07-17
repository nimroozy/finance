"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  advanceChangeRequest,
  listChangeRequests,
  type ServiceChangeRequest,
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

export default function ChangeRequestsPage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const [rows, setRows] = useState<ServiceChangeRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listChangeRequests({ per_page: 50 });
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

  async function advance(id: number, step: string) {
    setBusy(id);
    setError(null);
    try {
      await advanceChangeRequest(id, { step });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(null);
    }
  }

  const columns: DataTableColumn<ServiceChangeRequest>[] = [
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
        <div className="flex flex-wrap gap-2">
          {row.status === "requested" || row.status === "pending" ? (
            <Button size="sm" disabled={busy === row.id} onClick={() => void advance(row.id, "technical")}>
              {t("actions.technicalReview")}
            </Button>
          ) : null}
          {row.status === "finance_review" ? (
            <Button size="sm" disabled={busy === row.id} onClick={() => void advance(row.id, "finance")}>
              {t("actions.financeReview")}
            </Button>
          ) : null}
          {row.status === "approved" ? (
            <Button size="sm" disabled={busy === row.id} onClick={() => void advance(row.id, "apply")}>
              {t("actions.applyChange")}
            </Button>
          ) : null}
          {row.status === "applied" ? (
            <Button size="sm" variant="secondary" disabled={busy === row.id} onClick={() => void advance(row.id, "close")}>
              {t("actions.closeChange")}
            </Button>
          ) : null}
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-4" data-testid="services-change-requests">
      <WorkspaceHeader title={t("changeRequestsTitle")} subtitle={t("changeRequestsSubtitle")} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error && !rows.length ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && rows.length === 0 ? <EmptyWorkspace label={t("emptyChangeRequests")} /> : null}
      {!loading && rows.length > 0 ? (
        <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} />
      ) : null}
    </div>
  );
}
