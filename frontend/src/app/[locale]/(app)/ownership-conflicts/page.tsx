"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { PageHeader } from "@/components/ui/layout";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { ErrorState } from "@/components/ui/error-state";
import { ConfirmationDialog } from "@/components/ui/confirm-dialog";
import { useToast } from "@/components/ui/toast";
import { ApiError } from "@/lib/api";
import { listOwnershipConflicts, resolveOwnershipConflict, type OwnershipConflict } from "@/lib/ownership";

export default function OwnershipConflictsPage() {
  const t = useTranslations("ownershipConflictsPage");
  const tCommon = useTranslations("common");
  const { show } = useToast();

  const [rows, setRows] = useState<OwnershipConflict[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const [resolveTarget, setResolveTarget] = useState<OwnershipConflict | null>(null);
  const [resolving, setResolving] = useState(false);

  async function load() {
    setLoading(true);
    setError(null);
    try {
      const res = await listOwnershipConflicts();
      setRows(res.data);
    } catch (err) {
      setError(err);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  async function onResolve() {
    if (!resolveTarget) return;
    setResolving(true);
    try {
      await resolveOwnershipConflict(resolveTarget.id);
      show({ tone: "success", title: t("resolveSuccess") });
      setResolveTarget(null);
      await load();
    } catch (err) {
      show({ tone: "error", title: tCommon("error"), description: err instanceof ApiError ? err.message : undefined });
    } finally {
      setResolving(false);
    }
  }

  const columns: DataTableColumn<OwnershipConflict>[] = [
    {
      key: "customer",
      label: t("customer"),
      render: (row) => row.customer?.contact_name || row.customer?.company_name || `#${row.customer_id}`,
    },
    { key: "type", label: t("type"), render: (row) => row.conflict_type },
    { key: "status", label: t("status"), render: (row) => <StatusBadge status={row.status} /> },
    {
      key: "actions",
      label: tCommon("actions"),
      render: (row) =>
        row.status !== "resolved" ? (
          <Button size="sm" variant="secondary" onClick={() => setResolveTarget(row)}>
            {t("resolve")}
          </Button>
        ) : null,
    },
  ];

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />
      {error ? (
        <ErrorState error={error} onRetry={load} />
      ) : (
        <DataTable rows={rows} columns={columns} rowKey={(row) => row.id} loading={loading} emptyLabel={t("empty")} />
      )}

      <ConfirmationDialog
        open={Boolean(resolveTarget)}
        title={t("resolveConfirmTitle")}
        description={t("resolveConfirmBody")}
        confirmLabel={t("resolve")}
        loading={resolving}
        onCancel={() => setResolveTarget(null)}
        onConfirm={onResolve}
      />
    </div>
  );
}
