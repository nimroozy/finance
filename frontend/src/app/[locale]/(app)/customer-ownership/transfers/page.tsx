"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { PageHeader } from "@/components/ui/layout";
import { FormField } from "@/components/ui/form-field";
import { CustomerPicker, CollectorPicker } from "@/components/ui/pickers";
import { DatePicker } from "@/components/ui/date-picker";
import { TextArea } from "@/components/ui/form";
import { Button } from "@/components/ui/button";
import { ErrorState } from "@/components/ui/error-state";
import { ValidationSummary } from "@/components/ui/validation-summary";
import { useToast } from "@/components/ui/toast";
import type { SearchableOption } from "@/components/ui/searchable-select";
import { transferOwnership } from "@/lib/ownership";
import { ApiError } from "@/lib/api";

export default function OwnershipTransfersPage() {
  const t = useTranslations("ownershipTransferPage");
  const { show } = useToast();
  const [customer, setCustomer] = useState<SearchableOption | null>(null);
  const [toCollector, setToCollector] = useState<SearchableOption | null>(null);
  const [effectiveDate, setEffectiveDate] = useState(new Date().toISOString().slice(0, 10));
  const [reason, setReason] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<unknown>(null);

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (!customer || !toCollector || !reason.trim()) return;
    setSubmitting(true);
    setError(null);
    try {
      await transferOwnership({
        customer_id: Number(customer.id),
        to_collector_id: Number(toCollector.id),
        effective_date: effectiveDate,
        reason,
      });
      show({ tone: "success", title: t("success") });
      setCustomer(null);
      setToCollector(null);
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

      <form onSubmit={onSubmit} className="max-w-xl rounded-lg border border-border bg-surface-elevated p-4">
        <ValidationSummary error={error} />
        {error && !(error instanceof ApiError && error.status === 422) ? (
          <div className="mb-3">
            <ErrorState error={error} />
          </div>
        ) : null}

        <div className="space-y-4">
          <FormField label={t("customer")} required>
            <CustomerPicker value={customer} onChange={setCustomer} />
          </FormField>
          <FormField label={t("toCollector")} required>
            <CollectorPicker value={toCollector} onChange={setToCollector} />
          </FormField>
          <FormField label={t("effectiveDate")} required>
            <DatePicker value={effectiveDate} onChange={setEffectiveDate} />
          </FormField>
          <FormField label={t("reason")} required>
            <TextArea value={reason} onChange={(e) => setReason(e.target.value)} rows={3} />
          </FormField>
        </div>

        <Button type="submit" className="mt-4" disabled={!customer || !toCollector || !reason.trim() || submitting}>
          {t("submit")}
        </Button>
      </form>
    </div>
  );
}
