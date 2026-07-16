"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale } from "next-intl";
import { ApiError } from "@/lib/api";
import {
  approveCustodyReversal,
  getCustodyReversal,
  listCustodyReversals,
  rejectCustodyReversal,
  type CustodyReversalConflict,
  type CustodyReversalDetail,
} from "@/lib/custody";
import { formatMoney } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { TextArea } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CustodyReversalsPage() {
  const locale = useLocale();
  const fa = locale === "fa";
  const [rows, setRows] = useState<CustodyReversalConflict[]>([]);
  const [selected, setSelected] = useState<CustodyReversalDetail | null>(null);
  const [rejectReason, setRejectReason] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listCustodyReversals({ status: "pending_review" });
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function openDetail(id: number) {
    setBusy(true);
    setError(null);
    try {
      const res = await getCustodyReversal(id);
      setSelected(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  async function onApprove() {
    if (!selected) return;
    setBusy(true);
    setError(null);
    try {
      const res = await approveCustodyReversal(selected.conflict.id);
      setSelected(res.data);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  async function onReject() {
    if (!selected || rejectReason.trim().length < 3) return;
    setBusy(true);
    setError(null);
    try {
      await rejectCustodyReversal(selected.conflict.id, rejectReason.trim());
      setSelected(null);
      setRejectReason("");
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <PageHeader
        title={fa ? "برگشت‌های حضانت" : "Custody reversals"}
        subtitle={
          fa
            ? "برگشت پرداخت پس از تحویل تأییدشده — فقط با بدهکاری جبرانی صندوق"
            : "Post-handover payment reversals with compensating cashbox debit"
        }
      />
      {error ? <Alert tone="danger">{error}</Alert> : null}

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel className="p-4">
          {loading ? (
            <LoadingState label={fa ? "در حال بارگذاری…" : "Loading…"} />
          ) : rows.length === 0 ? (
            <EmptyState label={fa ? "موردی نیست" : "No pending custody reversals"} />
          ) : (
            <ul className="divide-y divide-border">
              {rows.map((row) => (
                <li key={row.id} className="flex items-center justify-between gap-3 py-3 text-sm">
                  <div>
                    <p className="font-medium">
                      {row.payment?.payment_reference || row.payment?.uuid || `#${row.payment_id}`}
                    </p>
                    <p className="text-muted">
                      {formatMoney(row.payment?.amount, row.payment?.currency || "AFN", locale)} ·{" "}
                      {row.handover?.handover_number || "—"}
                    </p>
                  </div>
                  <Button variant="secondary" disabled={busy} onClick={() => void openDetail(row.id)}>
                    {fa ? "جزئیات" : "Open"}
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </Panel>

        <Panel className="space-y-3 p-4 text-sm">
          {!selected ? (
            <p className="text-muted">{fa ? "یک مورد را انتخاب کنید" : "Select a custody reversal"}</p>
          ) : (
            <>
              <p>
                <strong>{fa ? "پرداخت" : "Payment"}:</strong>{" "}
                {selected.payment?.payment_reference || selected.payment?.uuid}
              </p>
              <p>
                <strong>{fa ? "رسید" : "Receipt"}:</strong>{" "}
                {selected.receipt?.receipt_number || selected.receipt?.uuid || "—"}
              </p>
              <p>
                <strong>{fa ? "تحویل" : "Handover"}:</strong>{" "}
                {selected.handover?.handover_number || "—"} ({selected.handover?.status})
              </p>
              <p>
                <strong>{fa ? "جمع‌آور" : "Collector"}:</strong>{" "}
                {selected.collector?.user?.name || "—"}
              </p>
              <p>
                <strong>{fa ? "صندوق" : "Cashbox"}:</strong>{" "}
                {formatMoney(selected.cashbox?.balance, selected.cashbox?.currency || "AFN", locale)}
              </p>
              <p>
                <strong>{fa ? "مبلغ" : "Amount"}:</strong>{" "}
                {formatMoney(selected.amount, selected.currency || "AFN", locale)}
              </p>
              <p>
                <strong>{fa ? "وضعیت زوهو" : "Zoho state"}:</strong>{" "}
                {selected.zoho_state?.status || selected.conflict.zoho_void_status || "—"}
              </p>
              <p>
                <strong>{fa ? "دلیل" : "Reason"}:</strong> {selected.reason}
              </p>

              {selected.conflict.status === "pending_review" ? (
                <div className="space-y-2 pt-2">
                  <Button className="w-full" disabled={busy} onClick={() => void onApprove()}>
                    {fa ? "تأیید برگشت حضانت" : "Approve custody reversal"}
                  </Button>
                  <TextArea
                    value={rejectReason}
                    onChange={(e) => setRejectReason(e.target.value)}
                    placeholder={fa ? "دلیل رد" : "Rejection reason"}
                  />
                  <Button
                    variant="secondary"
                    className="w-full"
                    disabled={busy || rejectReason.trim().length < 3}
                    onClick={() => void onReject()}
                  >
                    {fa ? "رد" : "Reject"}
                  </Button>
                </div>
              ) : (
                <p className="text-muted">
                  {fa ? "وضعیت" : "Status"}: {selected.conflict.status}
                </p>
              )}
            </>
          )}
        </Panel>
      </div>
    </div>
  );
}
