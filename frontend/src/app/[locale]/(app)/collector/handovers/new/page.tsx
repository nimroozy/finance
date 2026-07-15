"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { fetchMe } from "@/lib/auth";
import {
  draftCashHandover,
  listEligibleHandoverPayments,
  submitCashHandover,
} from "@/lib/handovers";
import { formatMoney } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

type Eligible = { id: number; amount: string; payment_reference?: string };

export default function CollectorNewHandoverPage() {
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const [branchId, setBranchId] = useState<number | null>(null);
  const [eligible, setEligible] = useState<Eligible[]>([]);
  const [selected, setSelected] = useState<number[]>([]);
  const [declared, setDeclared] = useState("");
  const [notes, setNotes] = useState("");
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [resultId, setResultId] = useState<number | null>(null);

  useEffect(() => {
    void fetchMe()
      .then((u) => {
        const id = u.branches?.[0]?.id ?? null;
        setBranchId(id);
      })
      .catch(() => setBranchId(null));
  }, []);

  const load = useCallback(async () => {
    if (!branchId) return;
    setLoading(true);
    setError(null);
    try {
      const res = await listEligibleHandoverPayments(branchId);
      const rows = res.data ?? [];
      setEligible(rows);
      setSelected(rows.map((r) => r.id));
      const total = rows.reduce((sum, r) => sum + Number(r.amount), 0);
      setDeclared(total.toFixed(4));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [branchId, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  const selectedTotal = useMemo(
    () =>
      eligible
        .filter((e) => selected.includes(e.id))
        .reduce((sum, r) => sum + Number(r.amount), 0)
        .toFixed(4),
    [eligible, selected],
  );

  async function onSubmit() {
    if (!branchId || selected.length === 0) return;
    setSubmitting(true);
    setError(null);
    try {
      const draft = await draftCashHandover({
        branch_id: branchId,
        payment_ids: selected,
        declared_amount: declared || selectedTotal,
        notes: notes || undefined,
      });
      const submitted = await submitCashHandover(draft.data.id);
      setResultId(submitted.data.id);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;

  return (
    <div className="space-y-4">
      <PageHeader
        title={locale === "fa" ? "تحویل نقدی جدید" : "New cash handover"}
        subtitle={
          locale === "fa"
            ? "پرداخت‌های نقدی واجد شرایط را انتخاب و تحویل دهید"
            : "Select eligible cash payments and submit for branch review"
        }
      />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {resultId ? (
        <Alert tone="success">
          {locale === "fa" ? "تحویل ثبت شد" : "Handover submitted"} #{resultId}
        </Alert>
      ) : null}
      <Panel>
        {eligible.length === 0 ? (
          <EmptyState
            label={locale === "fa" ? "پرداخت واجد شرایط نیست" : "No eligible payments"}
          />
        ) : (
          <ul className="space-y-2">
            {eligible.map((row) => (
              <li key={row.id} className="flex items-center gap-3 border-b py-2">
                <input
                  type="checkbox"
                  checked={selected.includes(row.id)}
                  onChange={(e) => {
                    setSelected((prev) =>
                      e.target.checked
                        ? [...prev, row.id]
                        : prev.filter((id) => id !== row.id),
                    );
                  }}
                />
                <span className="flex-1 text-sm">
                  #{row.id} {row.payment_reference}
                </span>
                <strong>{formatMoney(row.amount, "AFN", locale)}</strong>
              </li>
            ))}
          </ul>
        )}
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          <label className="text-sm">
            {locale === "fa" ? "مجموع انتخاب‌شده" : "Selected total"}
            <Input value={selectedTotal} readOnly />
          </label>
          <label className="text-sm">
            {locale === "fa" ? "مبلغ اعلام‌شده" : "Declared cash"}
            <Input value={declared} onChange={(e) => setDeclared(e.target.value)} />
          </label>
        </div>
        <label className="mt-3 block text-sm">
          {locale === "fa" ? "یادداشت" : "Notes"}
          <Input value={notes} onChange={(e) => setNotes(e.target.value)} />
        </label>
        <div className="mt-4">
          <Button
            disabled={submitting || selected.length === 0}
            onClick={() => void onSubmit()}
          >
            {submitting
              ? tCommon("loading")
              : locale === "fa"
                ? "ثبت تحویل"
                : "Submit handover"}
          </Button>
        </div>
      </Panel>
    </div>
  );
}
