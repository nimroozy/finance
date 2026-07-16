"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { listZohoMappingConflicts } from "@/lib/zoho";
import type { ZohoMappingConflict } from "@/lib/types";
import { Badge } from "@/components/ui/badge";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Alert, PageHeader } from "@/components/ui/layout";

export default function MappingConflictsPage() {
  const t = useTranslations("mappingConflicts");
  const [rows, setRows] = useState<ZohoMappingConflict[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [deferred, setDeferred] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const response = await listZohoMappingConflicts(page);
      setRows(response.data);
      setLastPage(response.meta?.last_page ?? 1);
    } catch (err) {
      if (err instanceof ApiError && err.status === 404) setDeferred(true);
      else setError(err instanceof ApiError ? err.message : t("loadError"));
    } finally {
      setLoading(false);
    }
  }, [page, t]);

  useEffect(() => { void load(); }, [load]);

  const columns: DataTableColumn<ZohoMappingConflict>[] = [
    { key: "customer", label: t("customer"), render: (row) => <div><strong>{row.name || `#${row.customer_id}`}</strong><p className="text-xs text-muted">#{row.customer_id}</p></div> },
    { key: "locations", label: t("locations"), render: (row) => row.locations.map((item) => item.name || item.zoho_location_id).join(", ") },
    { key: "branches", label: t("candidates"), render: (row) => row.locations.map((item) => item.branch?.name_en).filter(Boolean).join(", ") || "—" },
    { key: "activity", label: t("activity"), render: (row) => t("activityCounts", { payments: row.payments_count, assignments: row.assignments_count }) },
    { key: "status", label: t("status"), render: (row) => <Badge tone="warning">{t(row.classification as "multi_location_conflict")}</Badge> },
  ];

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />
      {deferred ? <div className="mb-4"><Alert tone="info">{t("apiDeferred")}</Alert></div> : null}
      {error ? <div className="mb-4"><Alert>{error}</Alert></div> : null}
      <DataTable rows={rows} columns={columns} rowKey={(row) => row.customer_id} loading={loading} page={page} lastPage={lastPage} onPageChange={setPage} />
    </div>
  );
}
