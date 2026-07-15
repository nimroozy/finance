"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  approveCashHandover,
  getCashHandover,
  listCashHandovers,
  rejectCashHandover,
  type CashHandover,
} from "@/lib/handovers";
import { formatMoney } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function ManagerHandoversPage() {
  const locale = useLocale();
  const [rows, setRows] = useState<CashHandover[]>([]);
  const [selected, setSelected] = useState<CashHandover | null>(null);
  const [counted, setCounted] = useState("");
  const [notes, setNotes] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listCashHandovers(1);
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

  async function openReview(id: number) {
    setBusy(true);
    try {
      const row = await getCashHandover(id);
      setSelected(row.data);
      setCounted(row.data.declared_amount);
      setNotes("");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  async function approve() {
    if (!selected) return;
    setBusy(true);
    try {
      await approveCashHandover(selected.id, {
        counted_amount: counted,
        approved_amount: counted,
        notes: notes || undefined,
      });
      setSelected(null);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  async function reject() {
    if (!selected) return;
    setBusy(true);
    try {
      await rejectCashHandover(selected.id, notes || "Rejected");
      setSelected(null);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingState label={locale === "fa" ? "در حال بارگذاری…" : "Loading…"} />;

  return (
    <div className="space-y-4">
      <PageHeader
        title={locale === "fa" ? "تحویل‌های نقدی" : "Cash handovers"}
        subtitle={
          locale === "fa"
            ? "بررسی و تأیید تحویل نقد از تحصیلداران"
            : "Review and approve collector cash handovers"
        }
      />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      <Panel>
        {rows.length === 0 ? (
          <EmptyState label={locale === "fa" ? "موردی نیست" : "No handovers"} />
        ) : (
          <ul className="divide-y">
            {rows.map((row) => (
              <li key={row.id} className="flex items-center gap-3 py-3">
                <div className="flex-1">
                  <div className="font-medium">
                    {row.handover_number || `#${row.id}`}
                  </div>
                  <div className="text-sm text-muted-foreground">
                    {formatMoney(row.declared_amount, row.currency, locale)}
                  </div>
                </div>
                <StatusBadge status={row.status} />
                <Button
                  variant="secondary"
                  disabled={busy}
                  onClick={() => void openReview(row.id)}
                >
                  {locale === "fa" ? "بررسی" : "Review"}
                </Button>
              </li>
            ))}
          </ul>
        )}
      </Panel>

      {selected ? (
        <Panel>
          <h2 className="mb-3 text-lg font-semibold">
            {locale === "fa" ? "بررسی تحویل" : "Review handover"} #{selected.id}
          </h2>
          <p className="text-sm">
            {locale === "fa" ? "مجموع پرداخت‌ها" : "Selected total"}:{" "}
            {formatMoney(selected.selected_payment_total, selected.currency, locale)}
          </p>
          <label className="mt-3 block text-sm">
            {locale === "fa" ? "مبلغ شمارش‌شده" : "Counted amount"}
            <Input value={counted} onChange={(e) => setCounted(e.target.value)} />
          </label>
          <label className="mt-3 block text-sm">
            {locale === "fa" ? "یادداشت" : "Notes"}
            <Input value={notes} onChange={(e) => setNotes(e.target.value)} />
          </label>
          <div className="mt-4 flex gap-2">
            <Button disabled={busy || selected.status !== "submitted"} onClick={() => void approve()}>
              {locale === "fa" ? "تأیید" : "Approve"}
            </Button>
            <Button
              variant="secondary"
              disabled={busy || selected.status !== "submitted"}
              onClick={() => void reject()}
            >
              {locale === "fa" ? "رد" : "Reject"}
            </Button>
            <Link href="/cashboxes" className="self-center text-sm underline">
              {locale === "fa" ? "صندوق شعبه" : "Branch cashboxes"}
            </Link>
          </div>
        </Panel>
      ) : null}
    </div>
  );
}
