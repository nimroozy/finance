"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { useRouter } from "@/i18n/navigation";
import { getAssignment, listAssignments } from "@/lib/assignments";
import { listInvoices } from "@/lib/customers";
import { getCurrentPosition } from "@/lib/geolocation";
import {
  confirmPayment,
  createPaymentDraft,
  listPaymentMethods,
  newIdempotencyKey,
  previewPayment,
} from "@/lib/payments";
import type {
  CreatePaymentPayload,
  CustomerAssignment,
  Invoice,
  PaymentMethod,
  PaymentPreview,
} from "@/lib/types";
import { formatMoney } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input, Label, Select, TextArea } from "@/components/ui/form";
import { Alert, LoadingState, PageHeader, Panel } from "@/components/ui/layout";
import { FormField } from "@/components/ui/form-field";
import { CustomerPicker } from "@/components/ui/pickers";
import { ErrorState } from "@/components/ui/error-state";
import { ConfirmationDialog } from "@/components/ui/confirm-dialog";
import type { SearchableOption } from "@/components/ui/searchable-select";

type Step = 1 | 2 | 3;

export default function CollectorNewPaymentPage() {
  const t = useTranslations("paymentsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const search = useSearchParams();

  const assignmentIdParam = search.get("assignment_id") || "";
  const customerIdParam = search.get("customer_id") || "";

  const [step, setStep] = useState<Step>(1);
  const [assignments, setAssignments] = useState<CustomerAssignment[]>([]);
  const [assignmentId, setAssignmentId] = useState(assignmentIdParam);
  const [customerId, setCustomerId] = useState(customerIdParam);
  const [selectedCustomer, setSelectedCustomer] = useState<SearchableOption | null>(null);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [allocations, setAllocations] = useState<Record<number, string>>({});
  const [amount, setAmount] = useState("");
  const [methods, setMethods] = useState<PaymentMethod[]>([]);
  const [methodId, setMethodId] = useState("");
  const [externalRef, setExternalRef] = useState("");
  const [notes, setNotes] = useState("");
  const [preview, setPreview] = useState<PaymentPreview | null>(null);
  const [lat, setLat] = useState<number | null>(null);
  const [lng, setLng] = useState<number | null>(null);
  const [accuracy, setAccuracy] = useState<number | null>(null);
  const [gpsMsg, setGpsMsg] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<unknown>(null);

  useEffect(() => {
    void (async () => {
      setLoading(true);
      try {
        const [a, m] = await Promise.all([
          listAssignments({ is_active: true, per_page: 50 }),
          listPaymentMethods(),
        ]);
        setAssignments(a.data);
        const allowed = m.data.filter((x) => x.collector_allowed !== false);
        setMethods(allowed.length ? allowed : m.data);
        if (allowed[0] || m.data[0]) {
          setMethodId(String((allowed[0] || m.data[0]).id));
        }
        if (assignmentIdParam) {
          const detail = await getAssignment(Number(assignmentIdParam));
          setCustomerId(String(detail.data.customer_id));
          setAssignmentId(String(detail.data.id));
          const c = detail.data.customer;
          if (c) {
            setSelectedCustomer({
              id: c.id,
              label: c.contact_name || c.company_name || `#${c.id}`,
              description: c.customer_number ?? undefined,
            });
          }
        }
      } catch (err) {
        setError(err);
      } finally {
        setLoading(false);
      }
    })();
  }, [assignmentIdParam, tCommon]);

  const selectedMethod = useMemo(
    () => methods.find((m) => String(m.id) === methodId),
    [methods, methodId],
  );

  const totalAllocated = useMemo(() => {
    return Object.values(allocations).reduce((sum, v) => {
      const n = Number(v);
      return sum + (Number.isFinite(n) ? n : 0);
    }, 0);
  }, [allocations]);

  const loadInvoices = useCallback(async () => {
    if (!customerId) {
      setError(t("assignmentRequired"));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const res = await listInvoices({
        customer_id: customerId,
        per_page: 50,
        status: "unpaid",
      });
      // Fallback: if unpaid filter empty/unsupported, load all with balance
      let rows = res.data.filter((inv) => Number(inv.effective_balance ?? inv.balance) > 0);
      if (rows.length === 0) {
        const all = await listInvoices({
          customer_id: customerId,
          per_page: 50,
        });
        rows = all.data.filter((inv) => Number(inv.effective_balance ?? inv.balance) > 0);
      }
      setInvoices(rows);
      const next: Record<number, string> = {};
      rows.forEach((inv) => {
        next[inv.id] = "";
      });
      setAllocations(next);
      setStep(2);
    } catch (err) {
      setError(err);
    } finally {
      setBusy(false);
    }
  }, [customerId, t]);

  async function captureGps() {
    const pos = await getCurrentPosition();
    if (pos.permission === "granted") {
      setLat(pos.lat);
      setLng(pos.lng);
      setAccuracy(pos.accuracy);
      setGpsMsg(t("gpsOk"));
    } else if (pos.permission === "denied") {
      setGpsMsg(t("gpsDenied"));
    } else {
      setGpsMsg(t("gpsUnavailable"));
    }
  }

  function buildPayload(withKey?: string): CreatePaymentPayload {
    const alloc = Object.entries(allocations)
      .map(([invoiceId, value]) => ({
        invoice_id: Number(invoiceId),
        amount: Number(value),
      }))
      .filter((a) => a.amount > 0);

    return {
      customer_id: Number(customerId),
      payment_method_id: Number(methodId),
      amount: Number(amount),
      allocations: alloc,
      external_reference: externalRef.trim() || undefined,
      notes: notes.trim() || undefined,
      latitude: lat ?? undefined,
      longitude: lng ?? undefined,
      gps_accuracy: accuracy ?? undefined,
      device_info:
        typeof navigator !== "undefined" ? navigator.userAgent.slice(0, 500) : undefined,
      idempotency_key: withKey,
    };
  }

  async function runPreview() {
    if (Math.abs(totalAllocated - Number(amount)) > 0.009) {
      setError(t("mismatch"));
      return;
    }
    if (selectedMethod?.requires_reference && !externalRef.trim()) {
      setError(tCommon("required"));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const res = await previewPayment(buildPayload());
      setPreview(res.data);
      setStep(3);
    } catch (err) {
      setError(err);
    } finally {
      setBusy(false);
    }
  }

  async function submitPayment() {
    if (busy) return;
    setBusy(true);
    setError(null);
    try {
      const draftKey = newIdempotencyKey();
      const draft = await createPaymentDraft(buildPayload(draftKey));
      const confirmed = await confirmPayment(draft.data.uuid);
      router.push(`/collector/payments/${confirmed.data.uuid}`);
    } catch (err) {
      setError(err);
      setBusy(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;

  return (
    <div className="space-y-4">
      <PageHeader title={t("createTitle")} />
      {error ? (
        typeof error === "string" ? (
          <Alert>{error}</Alert>
        ) : (
          <ErrorState
            error={error}
            onRetry={step === 1 ? () => void loadInvoices() : undefined}
          />
        )
      ) : null}
      {gpsMsg ? <Alert tone="info">{gpsMsg}</Alert> : null}

      <p className="text-sm text-muted">
        {step === 1
          ? t("stepCustomer")
          : step === 2
            ? t("stepAllocate")
            : t("stepMethod")}
      </p>

      {step === 1 ? (
        <Panel className="space-y-4 p-4">
          {assignments.length > 0 ? (
            <div>
              <Label>{t("selectAssignment")}</Label>
              <Select
                value={assignmentId}
                onChange={(e) => {
                  const id = e.target.value;
                  setAssignmentId(id);
                  const found = assignments.find((a) => String(a.id) === id);
                  if (found) {
                    setCustomerId(String(found.customer_id));
                    setSelectedCustomer({
                      id: found.customer_id,
                      label: found.customer?.contact_name || found.customer?.company_name || `#${found.customer_id}`,
                      description: found.customer?.customer_number ?? undefined,
                    });
                  }
                }}
                className="h-12"
              >
                <option value="">—</option>
                {assignments.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.customer?.contact_name || `#${a.customer_id}`} · {a.status}
                  </option>
                ))}
              </Select>
            </div>
          ) : null}
          <FormField label={t("customer")} required>
            <CustomerPicker
              value={selectedCustomer}
              onChange={(option) => {
                setSelectedCustomer(option);
                setCustomerId(option ? String(option.id) : "");
                setAssignmentId("");
              }}
            />
          </FormField>
          <Button
            className="h-12 w-full"
            disabled={busy || !customerId}
            onClick={() => void loadInvoices()}
          >
            {t("loadInvoices")}
          </Button>
        </Panel>
      ) : null}

      {step === 2 ? (
        <Panel className="space-y-4 p-4">
          <p className="text-sm text-muted">{t("allocateHint")}</p>
          <div>
            <Label>{t("paymentAmount")}</Label>
            <Input
              type="number"
              step="0.01"
              min="0"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              className="h-12"
              required
            />
          </div>
          <ul className="divide-y divide-border rounded-md border border-border">
            {invoices.map((inv) => (
              <li key={inv.id} className="space-y-2 p-3 text-sm">
                <div className="flex justify-between gap-2">
                  <span className="font-medium">
                    {inv.invoice_number || `#${inv.id}`}
                  </span>
                  <span className="text-end">
                    {t("effectiveBalance")}:{" "}
                    {formatMoney(
                      inv.effective_balance ?? inv.balance,
                      inv.currency,
                      locale,
                    )}
                  </span>
                </div>
                {inv.awaiting_zoho_refresh ? (
                  <p className="text-xs text-amber-700 dark:text-amber-400">
                    {t("awaitingZohoRefresh")}
                  </p>
                ) : null}
                {inv.balance_sync_warning ? (
                  <p className="text-xs text-danger">{inv.balance_sync_warning}</p>
                ) : null}
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder={t("allocated")}
                  value={allocations[inv.id] ?? ""}
                  onChange={(e) =>
                    setAllocations((prev) => ({
                      ...prev,
                      [inv.id]: e.target.value,
                    }))
                  }
                  className="h-11"
                />
              </li>
            ))}
          </ul>
          <p className="text-sm">
            {t("totalAllocated")}:{" "}
            {formatMoney(totalAllocated, invoices[0]?.currency, locale)}
          </p>
          <div className="flex gap-2">
            <Button variant="secondary" className="h-12 flex-1" onClick={() => setStep(1)}>
              {tCommon("back")}
            </Button>
            <Button
              className="h-12 flex-1"
              disabled={busy || !amount || totalAllocated <= 0}
              onClick={() => setStep(3)}
            >
              {tCommon("next")}
            </Button>
          </div>
        </Panel>
      ) : null}

      {step === 3 ? (
        <Panel className="space-y-4 p-4">
          <div>
            <Label>{t("method")}</Label>
            <Select
              value={methodId}
              onChange={(e) => setMethodId(e.target.value)}
              className="h-12"
              required
            >
              {methods.map((m) => (
                <option key={m.id} value={m.id}>
                  {locale === "fa" && m.name_fa ? m.name_fa : m.name_en}
                </option>
              ))}
            </Select>
          </div>
          {selectedMethod?.requires_reference ? (
            <div>
              <Label>{t("externalRef")}</Label>
              <Input
                value={externalRef}
                onChange={(e) => setExternalRef(e.target.value)}
                className="h-12"
                required
              />
            </div>
          ) : null}
          <div>
            <Label>{t("notes")}</Label>
            <TextArea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              className="min-h-24"
            />
          </div>
          <Button
            type="button"
            variant="secondary"
            className="h-11 w-full"
            onClick={() => void captureGps()}
          >
            {t("gpsOptional")}
          </Button>

          {preview ? (
            <div className="space-y-2 rounded-md border border-border bg-sand-soft/30 p-3 text-sm">
              <p className="font-medium">{t("previewTitle")}</p>
              <p>
                {t("amount")}:{" "}
                {formatMoney(preview.amount, preview.currency, locale)}
              </p>
              <ul className="space-y-1">
                {preview.allocations.map((line) => (
                  <li key={line.invoice_id}>
                    {line.invoice_number || line.invoice_id}:{" "}
                    {formatMoney(line.amount, preview.currency, locale)}
                  </li>
                ))}
              </ul>
              {preview.warnings?.length ? (
                <div>
                  <p className="font-medium">{t("warnings")}</p>
                  <ul className="list-disc ps-5">
                    {preview.warnings.map((w) => (
                      <li key={w}>{w}</li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </div>
          ) : null}

          <div className="flex flex-col gap-2">
            <Button
              variant="secondary"
              className="h-12 w-full"
              disabled={busy}
              onClick={() => void runPreview()}
            >
              {t("preview")}
            </Button>
            <Button
              className="h-12 w-full"
              disabled={busy || !preview}
              onClick={() => setConfirmOpen(true)}
            >
              {busy ? t("submitting") : t("confirmPay")}
            </Button>
            <Button
              variant="ghost"
              className="h-11 w-full"
              disabled={busy}
              onClick={() => {
                setPreview(null);
                setStep(2);
              }}
            >
              {tCommon("back")}
            </Button>
          </div>
        </Panel>
      ) : null}

      <ConfirmationDialog
        open={confirmOpen}
        title={t("confirmPay")}
        description={t("confirmPaySummary", {
          amount: formatMoney(preview?.amount ?? amount, preview?.currency, locale),
          customer: selectedCustomer?.label ?? "",
        })}
        confirmLabel={t("confirmPay")}
        loading={busy}
        onCancel={() => setConfirmOpen(false)}
        onConfirm={() => {
          setConfirmOpen(false);
          void submitPayment();
        }}
      />
    </div>
  );
}
