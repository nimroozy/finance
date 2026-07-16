"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { listCashReconciliations, runCashReconciliation } from "@/lib/cash";
import { listCashboxes, type BranchCashbox } from "@/lib/handovers";
import type { CashReconciliationRun } from "@/lib/types";
import { formatMoney } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Input, Label, Select } from "@/components/ui/form";
import { Alert, PageHeader, Panel } from "@/components/ui/layout";

export default function ReconciliationPage() {
  const t = useTranslations("reconciliation");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canRun = useAuthStore((state) => state.hasPermission("cash_reconciliation.run"));
  const [rows, setRows] = useState<CashReconciliationRun[]>([]);
  const [cashboxes, setCashboxes] = useState<BranchCashbox[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [cashboxId, setCashboxId] = useState("");
  const [date, setDate] = useState("");
  const [counted, setCounted] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [runs, boxes] = await Promise.all([listCashReconciliations(page), listCashboxes()]);
      setRows(runs.data);
      setLastPage(runs.meta?.last_page ?? 1);
      setCashboxes(boxes.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [page, tCommon]);

  useEffect(() => { void load(); }, [load]);

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    setMessage(null);
    try {
      await runCashReconciliation({ cashbox_id: Number(cashboxId), date, counted_amount: counted || undefined });
      setMessage(t("runSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  const columns: DataTableColumn<CashReconciliationRun>[] = [
    { key: "date", label: t("date"), render: (row) => row.reconciliation_date },
    { key: "cashbox", label: t("cashbox"), render: (row) => cashboxes.find((box) => box.id === row.cashbox_id)?.name ?? `#${row.cashbox_id}` },
    { key: "expected", label: t("expected"), render: (row) => formatMoney(row.expected_balance, row.currency, locale) },
    { key: "counted", label: t("counted"), render: (row) => row.counted_balance === null ? "—" : formatMoney(row.counted_balance, row.currency, locale) },
    { key: "variance", label: t("variance"), render: (row) => formatMoney(row.variance_amount, row.currency, locale) },
    { key: "status", label: tCommon("status"), render: (row) => <Badge tone={row.status === "completed" ? "success" : "neutral"}>{row.status}</Badge> },
  ];

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />
      {error ? <div className="mb-4"><Alert>{error}</Alert></div> : null}
      {message ? <div className="mb-4"><Alert tone="success">{message}</Alert></div> : null}
      {canRun ? <Panel className="mb-6 p-4"><form onSubmit={submit} className="grid gap-3 sm:grid-cols-4 sm:items-end">
        <div><Label htmlFor="cashbox">{t("cashbox")}</Label><Select id="cashbox" value={cashboxId} required onChange={(e) => setCashboxId(e.target.value)}><option value="">—</option>{cashboxes.map((box) => <option key={box.id} value={box.id}>{box.name}</option>)}</Select></div>
        <div><Label htmlFor="date">{t("date")}</Label><Input id="date" type="date" value={date} required onChange={(e) => setDate(e.target.value)} /></div>
        <div><Label htmlFor="counted">{t("counted")}</Label><Input id="counted" type="number" step="0.01" value={counted} onChange={(e) => setCounted(e.target.value)} /></div>
        <Button type="submit" disabled={busy}>{t("run")}</Button>
      </form></Panel> : null}
      <DataTable rows={rows} columns={columns} rowKey={(row) => row.id} loading={loading} page={page} lastPage={lastPage} onPageChange={setPage} />
    </div>
  );
}
