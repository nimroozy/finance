"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import {
  decideZohoLocationMapping,
  listZohoLocationMappingReview,
} from "@/lib/zoho";
import type { ZohoLocationMappingReview } from "@/lib/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Alert, PageHeader } from "@/components/ui/layout";

export default function ZohoLocationMappingPage() {
  const t = useTranslations("locationMapping");
  const tCommon = useTranslations("common");
  const [locations, setLocations] = useState<ZohoLocationMappingReview[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [target, setTarget] = useState<{
    row: ZohoLocationMappingReview;
    action: "import_branch" | "link_existing" | "ignore" | "defer";
  } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const response = await listZohoLocationMappingReview();
      setLocations(response.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => { void load(); }, [load]);

  const reviewCounts = useMemo(() => ({
    mapped: locations.filter((item) => item.linked_branch).length,
    pending: locations.filter((item) => !item.linked_branch && !item.decision).length,
  }), [locations]);

  async function confirmDecision() {
    if (!target) return;
    setBusy(true);
    setError(null);
    try {
      await decideZohoLocationMapping(target.row.zoho_location_id, {
        action: target.action,
        branch_id: target.action === "link_existing" ? target.row.candidate_branch?.id : undefined,
      });
      setMessage(t("decisionSuccess"));
      setTarget(null);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  const columns: DataTableColumn<ZohoLocationMappingReview>[] = [
    { key: "location", label: t("location"), render: (row) => <div><strong>{row.name}</strong><p className="text-xs text-muted">{row.zoho_location_id}</p></div> },
    { key: "customers", label: t("customers"), render: (row) => row.customer_count },
    { key: "invoices", label: t("invoices"), render: (row) => `${row.mapped_invoice_count}/${row.invoice_count}` },
    { key: "status", label: tCommon("status"), render: (row) => <Badge tone={row.linked_branch ? "success" : row.decision ? "neutral" : "warning"}>{row.linked_branch ? t("linked") : row.decision ?? t("needsReview")}</Badge> },
    { key: "actions", label: tCommon("actions"), render: (row) => <div className="flex flex-wrap gap-1">
      <Button size="sm" variant="secondary" disabled={Boolean(row.linked_branch)} onClick={() => setTarget({ row, action: "import_branch" })}>{t("import")}</Button>
      <Button size="sm" variant="secondary" disabled={Boolean(row.linked_branch) || !row.candidate_branch} title={!row.candidate_branch ? t("noCandidate") : undefined} onClick={() => setTarget({ row, action: "link_existing" })}>{t("link")}</Button>
      <Button size="sm" variant="ghost" disabled={Boolean(row.linked_branch)} onClick={() => setTarget({ row, action: "ignore" })}>{t("ignore")}</Button>
      <Button size="sm" variant="ghost" disabled={Boolean(row.linked_branch)} onClick={() => setTarget({ row, action: "defer" })}>{t("defer")}</Button>
    </div> },
  ];

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />
      <p className="mb-4 text-sm text-muted">{t("reviewSummary", reviewCounts)}</p>
      {error ? <div className="mb-4"><Alert>{error}</Alert></div> : null}
      {message ? <div className="mb-4"><Alert tone="success">{message}</Alert></div> : null}
      <DataTable rows={locations} columns={columns} rowKey={(row) => row.zoho_location_id} loading={loading} />
      <ConfirmDialog open={Boolean(target)} title={t("previewTitle")} description={target ? t("decisionConfirm", { action: t(target.action), name: target.row.name }) : ""} confirmLabel={target ? t(target.action) : undefined} loading={busy} onCancel={() => setTarget(null)} onConfirm={() => void confirmDecision()} />
    </div>
  );
}
