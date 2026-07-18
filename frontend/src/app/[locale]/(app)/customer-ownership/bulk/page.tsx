"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { X } from "lucide-react";
import { PageHeader } from "@/components/ui/layout";
import { FormField } from "@/components/ui/form-field";
import { CustomerPicker, CollectorPicker } from "@/components/ui/pickers";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/form";
import { Button } from "@/components/ui/button";
import { ErrorState } from "@/components/ui/error-state";
import { ValidationSummary } from "@/components/ui/validation-summary";
import { InlineAlert } from "@/components/ui/layout";
import { useToast } from "@/components/ui/toast";
import type { SearchableOption } from "@/components/ui/searchable-select";
import { bulkAssignOwnership } from "@/lib/ownership";
import { ApiError } from "@/lib/api";

export default function BulkOwnershipPage() {
  const t = useTranslations("ownershipBulkPage");
  const { show } = useToast();
  const [customers, setCustomers] = useState<SearchableOption[]>([]);
  const [pickerValue, setPickerValue] = useState<SearchableOption | null>(null);
  const [collector, setCollector] = useState<SearchableOption | null>(null);
  const [startDate, setStartDate] = useState(new Date().toISOString().slice(0, 10));
  const [reason, setReason] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<unknown>(null);
  const [result, setResult] = useState<{ assigned: number; skipped: number } | null>(null);

  function addCustomer(option: SearchableOption | null) {
    if (!option) return;
    setCustomers((prev) => (prev.some((c) => c.id === option.id) ? prev : [...prev, option]));
    setPickerValue(null);
  }

  function removeCustomer(id: string | number) {
    setCustomers((prev) => prev.filter((c) => c.id !== id));
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (customers.length === 0 || !collector) return;
    setSubmitting(true);
    setError(null);
    setResult(null);
    try {
      const res = await bulkAssignOwnership({
        customer_ids: customers.map((c) => Number(c.id)),
        collector_id: Number(collector.id),
        start_date: startDate,
        reason: reason || undefined,
      });
      setResult(res.data);
      show({ tone: "success", title: t("resultSummary", { assigned: res.data.assigned, skipped: res.data.skipped }) });
      setCustomers([]);
      setCollector(null);
      setReason("");
    } catch (err) {
      setError(err);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      <form onSubmit={onSubmit} className="max-w-2xl rounded-lg border border-border bg-surface-elevated p-4">
        <ValidationSummary error={error} />
        {error && !(error instanceof ApiError && error.status === 422) ? (
          <div className="mb-3">
            <ErrorState error={error} />
          </div>
        ) : null}

        <FormField label={t("addCustomer")}>
          <CustomerPicker value={pickerValue} onChange={addCustomer} />
        </FormField>

        <div className="mt-3">
          <p className="mb-1.5 text-sm font-medium text-foreground">
            {t("customers", { count: customers.length })}
          </p>
          {customers.length === 0 ? (
            <p className="text-sm text-muted">{t("noCustomers")}</p>
          ) : (
            <ul className="flex flex-wrap gap-1.5">
              {customers.map((c) => (
                <li
                  key={c.id}
                  className="flex items-center gap-1 rounded-full border border-border bg-surface-muted px-2.5 py-1 text-sm"
                >
                  {c.label}
                  <button
                    type="button"
                    onClick={() => removeCustomer(c.id)}
                    className="rounded-full p-0.5 text-muted hover:text-danger"
                    aria-label="Remove"
                  >
                    <X className="h-3 w-3" />
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="mt-4 grid gap-4 sm:grid-cols-3">
          <FormField label={t("collector")} required>
            <CollectorPicker value={collector} onChange={setCollector} />
          </FormField>
          <FormField label={t("startDate")} required>
            <DatePicker value={startDate} onChange={setStartDate} />
          </FormField>
          <FormField label={t("reason")}>
            <Input value={reason} onChange={(e) => setReason(e.target.value)} />
          </FormField>
        </div>

        <Button type="submit" className="mt-4" disabled={customers.length === 0 || !collector || submitting}>
          {t("submit")}
        </Button>

        {result ? (
          <div className="mt-4">
            <InlineAlert tone="success">
              {t("resultSummary", { assigned: result.assigned, skipped: result.skipped })}
            </InlineAlert>
          </div>
        ) : null}
      </form>
    </div>
  );
}
