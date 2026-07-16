"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useRouter, Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  createCashboxTransfer,
  listCashboxTransfers,
  reverseCashboxTransfer,
  transitionCashboxTransfer,
} from "@/lib/cash";
import { listCashboxes, type BranchCashbox } from "@/lib/handovers";
import type { CashboxTransfer, CashTransferType } from "@/lib/types";
import { formatDateTime, formatMoney } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Input, Label, Select, TextArea } from "@/components/ui/form";
import { Alert, LoadingState, PageHeader, Panel } from "@/components/ui/layout";

function statusTone(status: string): "success" | "warning" | "danger" | "neutral" {
  if (status === "received") return "success";
  if (status === "reversed") return "danger";
  if (["submitted", "approved", "sent"].includes(status)) return "warning";
  return "neutral";
}

export function CashTransferListPage({ type }: { type: CashTransferType }) {
  const t = useTranslations("cashTransfers");
  const locale = useLocale();
  const [rows, setRows] = useState<CashboxTransfer[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await listCashboxTransfers(page, type);
      setRows(response.data.filter((item) => item.type === type));
      setLastPage(response.meta?.last_page ?? 1);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("loadError"));
    } finally {
      setLoading(false);
    }
  }, [page, t, type]);

  useEffect(() => { void load(); }, [load]);

  const columns: DataTableColumn<CashboxTransfer>[] = [
    { key: "number", label: t("number"), render: (row) => <Link className="font-medium text-primary hover:underline" href={`/transfers/${row.id}`}>{row.transfer_number ?? `#${row.id}`}</Link> },
    { key: "amount", label: t("amount"), render: (row) => formatMoney(row.amount, row.currency, locale) },
    { key: "from", label: t("fromCashbox"), render: (row) => `#${row.from_cashbox_id}` },
    { key: "to", label: t("toCashbox"), render: (row) => row.to_cashbox_id ? `#${row.to_cashbox_id}` : row.bank_name || "—" },
    { key: "status", label: t("status"), render: (row) => <Badge tone={statusTone(row.status)}>{t(`statuses.${row.status}` as "statuses.draft")}</Badge> },
    { key: "created", label: t("created"), render: (row) => formatDateTime(row.created_at, locale) },
  ];

  const isDeposit = type === "bank_deposit";
  return (
    <div>
      <PageHeader
        title={isDeposit ? t("depositsTitle") : t("transfersTitle")}
        subtitle={isDeposit ? t("depositsSubtitle") : t("transfersSubtitle")}
        actions={<Link href={isDeposit ? "/bank-deposits/new" : "/transfers/new"} className="inline-flex h-10 items-center rounded-md bg-primary px-4 text-sm font-medium text-white">{t("new")}</Link>}
      />
      {error ? <div className="mb-4"><Alert>{error}</Alert></div> : null}
      <DataTable
        rows={rows}
        columns={columns}
        rowKey={(row) => row.id}
        loading={loading}
        page={page}
        lastPage={lastPage}
        onPageChange={setPage}
        mobileCard={(row) => (
          <Link href={`/transfers/${row.id}`} className="block space-y-2">
            <div className="flex justify-between gap-3"><strong>{row.transfer_number ?? `#${row.id}`}</strong><Badge tone={statusTone(row.status)}>{row.status}</Badge></div>
            <p>{formatMoney(row.amount, row.currency, locale)}</p>
          </Link>
        )}
      />
    </div>
  );
}

export function NewCashTransferPage({ type }: { type: CashTransferType }) {
  const t = useTranslations("cashTransfers");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const [cashboxes, setCashboxes] = useState<BranchCashbox[]>([]);
  const [fromId, setFromId] = useState("");
  const [toId, setToId] = useState("");
  const [amount, setAmount] = useState("");
  const [bankName, setBankName] = useState("");
  const [bankReference, setBankReference] = useState("");
  const [depositedOn, setDepositedOn] = useState("");
  const [notes, setNotes] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void listCashboxes()
      .then((response) => setCashboxes(response.data))
      .catch((err) => setError(err instanceof ApiError ? err.message : t("loadError")));
  }, [t]);

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const response = await createCashboxTransfer({
        from_cashbox_id: Number(fromId),
        to_cashbox_id: type === "cashbox_transfer" ? Number(toId) : undefined,
        amount,
        type,
        bank_name: bankName || undefined,
        bank_reference: bankReference || undefined,
        deposited_on: depositedOn || undefined,
        notes: notes || undefined,
      });
      router.push(`/transfers/${response.data.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  const isDeposit = type === "bank_deposit";
  return (
    <div className="mx-auto max-w-2xl">
      <PageHeader title={isDeposit ? t("newDepositTitle") : t("newTransferTitle")} subtitle={t("draftHint")} />
      <Panel className="p-5">
        <form onSubmit={submit} className="space-y-4">
          {error ? <Alert>{error}</Alert> : null}
          <div><Label htmlFor="from">{t("fromCashbox")}</Label><Select id="from" value={fromId} required onChange={(e) => setFromId(e.target.value)}><option value="">—</option>{cashboxes.map((box) => <option key={box.id} value={box.id}>{box.name} ({box.currency} {box.balance})</option>)}</Select></div>
          {!isDeposit ? <div><Label htmlFor="to">{t("toCashbox")}</Label><Select id="to" value={toId} required onChange={(e) => setToId(e.target.value)}><option value="">—</option>{cashboxes.filter((box) => String(box.id) !== fromId).map((box) => <option key={box.id} value={box.id}>{box.name}</option>)}</Select></div> : null}
          <div><Label htmlFor="amount">{t("amount")}</Label><Input id="amount" type="number" min="0.01" step="0.01" value={amount} required onChange={(e) => setAmount(e.target.value)} /></div>
          {isDeposit ? <>
            <div><Label htmlFor="bank">{t("bankName")}</Label><Input id="bank" value={bankName} required onChange={(e) => setBankName(e.target.value)} /></div>
            <div><Label htmlFor="reference">{t("bankReference")}</Label><Input id="reference" value={bankReference} onChange={(e) => setBankReference(e.target.value)} /></div>
            <div><Label htmlFor="date">{t("depositedOn")}</Label><Input id="date" type="date" value={depositedOn} required onChange={(e) => setDepositedOn(e.target.value)} /></div>
          </> : null}
          <div><Label htmlFor="notes">{t("notes")}</Label><TextArea id="notes" value={notes} onChange={(e) => setNotes(e.target.value)} /></div>
          <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => router.back()}>{tCommon("cancel")}</Button><Button type="submit" disabled={busy}>{t("saveDraft")}</Button></div>
        </form>
      </Panel>
    </div>
  );
}

export function CashTransferDetailPage({ id }: { id: number }) {
  const t = useTranslations("cashTransfers");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const hasAnyPermission = useAuthStore((state) => state.hasAnyPermission);
  const [transfer, setTransfer] = useState<CashboxTransfer | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [confirmAction, setConfirmAction] = useState<"submit" | "approve" | "send" | "receive" | null>(null);
  const [reason, setReason] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      let response = await listCashboxTransfers(1);
      let match = response.data.find((item) => item.id === id);
      const lastPage = response.meta?.last_page ?? 1;
      for (let page = 2; !match && page <= lastPage; page += 1) {
        response = await listCashboxTransfers(page);
        match = response.data.find((item) => item.id === id);
      }
      setTransfer(match ?? null);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("loadError"));
    } finally {
      setLoading(false);
    }
  }, [id, t]);

  useEffect(() => { void load(); }, [load]);

  const allowed = useMemo(() => {
    if (!transfer) return [];
    if (transfer.status === "draft") return ["submit"];
    if (transfer.status === "submitted") return ["approve"];
    if (transfer.status === "approved") return ["send"];
    if (transfer.status === "sent") return ["receive"];
    return [];
  }, [transfer]);

  async function runAction(action: "submit" | "approve" | "send" | "receive") {
    setBusy(true);
    setError(null);
    try {
      const response = await transitionCashboxTransfer(id, action);
      setTransfer(response.data);
      setConfirmAction(null);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function reverse() {
    if (!reason.trim()) return;
    setBusy(true);
    try {
      const response = await reverseCashboxTransfer(id, reason);
      setTransfer(response.data);
      setReason("");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (!transfer) return <Alert>{t("notFound")}</Alert>;

  const canApprove = hasAnyPermission(["cashbox_transfers.approve"]);
  return (
    <div>
      <PageHeader title={transfer.transfer_number ?? `#${transfer.id}`} subtitle={t(transfer.type === "bank_deposit" ? "depositDetail" : "transferDetail")} />
      {error ? <div className="mb-4"><Alert>{error}</Alert></div> : null}
      <Panel className="p-5">
        <div className="grid gap-4 sm:grid-cols-2">
          <div><p className="text-xs text-muted">{t("status")}</p><Badge tone={statusTone(transfer.status)}>{transfer.status}</Badge></div>
          <div><p className="text-xs text-muted">{t("amount")}</p><strong>{formatMoney(transfer.amount, transfer.currency, locale)}</strong></div>
          <div><p className="text-xs text-muted">{t("fromCashbox")}</p><p>#{transfer.from_cashbox_id}</p></div>
          <div><p className="text-xs text-muted">{t("toCashbox")}</p><p>{transfer.to_cashbox_id ? `#${transfer.to_cashbox_id}` : transfer.bank_name || "—"}</p></div>
          <div><p className="text-xs text-muted">{t("created")}</p><p>{formatDateTime(transfer.created_at, locale)}</p></div>
          <div><p className="text-xs text-muted">{t("notes")}</p><p>{transfer.notes || "—"}</p></div>
        </div>
        {canApprove ? <div className="mt-6 flex flex-wrap gap-2">{allowed.map((action) => <Button key={action} disabled={busy} onClick={() => setConfirmAction(action as typeof confirmAction)}>{t(`actions.${action}` as "actions.submit")}</Button>)}</div> : null}
        {canApprove && !["draft", "reversed"].includes(transfer.status) ? <div className="mt-5 flex gap-2 border-t border-border pt-5"><Input value={reason} placeholder={t("reverseReason")} onChange={(e) => setReason(e.target.value)} /><Button variant="danger" disabled={busy || !reason.trim()} onClick={() => void reverse()}>{t("actions.reverse")}</Button></div> : null}
      </Panel>
      <ConfirmDialog open={Boolean(confirmAction)} title={confirmAction ? t(`actions.${confirmAction}` as "actions.submit") : ""} description={t("actionConfirm")} loading={busy} onCancel={() => setConfirmAction(null)} onConfirm={() => { if (confirmAction) void runAction(confirmAction); }} />
    </div>
  );
}
