"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { PageHeader } from "@/components/ui/layout";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { CustomerPicker, CollectorPicker } from "@/components/ui/pickers";
import { DatePicker } from "@/components/ui/date-picker";
import { ErrorState } from "@/components/ui/error-state";
import { ValidationSummary } from "@/components/ui/validation-summary";
import { ConfirmationDialog } from "@/components/ui/confirm-dialog";
import { useToast } from "@/components/ui/toast";
import { LtrValue } from "@/components/ltr-value";
import type { SearchableOption } from "@/components/ui/searchable-select";
import {
  cancelTemporaryAssignment,
  createTemporaryAssignment,
  listTemporaryAssignments,
  type TemporaryAssignment,
} from "@/lib/ownership";
import { ApiError } from "@/lib/api";
import { formatDate } from "@/lib/utils";

export default function TemporaryAssignmentsPage() {
  const t = useTranslations("temporaryAssignmentsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const { show } = useToast();

  const [rows, setRows] = useState<TemporaryAssignment[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<unknown>(null);

  const [customer, setCustomer] = useState<SearchableOption | null>(null);
  const [collector, setCollector] = useState<SearchableOption | null>(null);
  const [startDate, setStartDate] = useState(new Date().toISOString().slice(0, 10));
  const [endDate, setEndDate] = useState(new Date().toISOString().slice(0, 10));
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<unknown>(null);
  const [cancelTarget, setCancelTarget] = useState<TemporaryAssignment | null>(null);
  const [cancelling, setCancelling] = useState(false);

  async function load() {
    setLoading(true);
    setLoadError(null);
    try {
      const res = await listTemporaryAssignments();
      setRows(res.data);
    } catch (error) {
      setLoadError(error);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (!customer || !collector) return;
    setSubmitting(true);
    setSubmitError(null);
    try {
      await createTemporaryAssignment({
        customer_id: Number(customer.id),
        temporary_collector_id: Number(collector.id),
        start_date: startDate,
        end_date: endDate,
      });
      show({ tone: "success", title: t("createSuccess") });
      setCustomer(null);
      setCollector(null);
      await load();
    } catch (error) {
      setSubmitError(error);
    } finally {
      setSubmitting(false);
    }
  }

  async function onCancel() {
    if (!cancelTarget) return;
    setCancelling(true);
    try {
      await cancelTemporaryAssignment(cancelTarget.id);
      show({ tone: "success", title: t("cancelSuccess") });
      setCancelTarget(null);
      await load();
    } catch (error) {
      show({ tone: "error", title: tCommon("error"), description: error instanceof ApiError ? error.message : undefined });
    } finally {
      setCancelling(false);
    }
  }

  const columns: DataTableColumn<TemporaryAssignment>[] = [
    {
      key: "customer",
      label: t("colCustomer"),
      render: (row) => row.customer?.contact_name || row.customer?.company_name || `#${row.customer_id}`,
    },
    {
      key: "collector",
      label: t("colCollector"),
      render: (row) => row.temporaryCollector?.user?.name || `#${row.temporary_collector_id}`,
    },
    { key: "start", label: t("colStart"), render: (row) => <LtrValue>{formatDate(row.start_date, locale)}</LtrValue> },
    { key: "end", label: t("colEnd"), render: (row) => <LtrValue>{formatDate(row.end_date, locale)}</LtrValue> },
    { key: "status", label: t("colStatus"), render: (row) => <StatusBadge status={row.status} /> },
    {
      key: "actions",
      label: tCommon("actions"),
      render: (row) =>
        row.is_active ? (
          <Button size="sm" variant="secondary" onClick={() => setCancelTarget(row)}>
            {t("cancel")}
          </Button>
        ) : null,
    },
  ];

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      <form onSubmit={onSubmit} className="mb-6 rounded-lg border border-border bg-surface-elevated p-4">
        <ValidationSummary error={submitError} />
        {submitError && !(submitError instanceof ApiError && submitError.status === 422) ? (
          <div className="mb-3">
            <ErrorState error={submitError} />
          </div>
        ) : null}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <FormField label={t("customer")} required>
            <CustomerPicker value={customer} onChange={setCustomer} />
          </FormField>
          <FormField label={t("temporaryCollector")} required>
            <CollectorPicker value={collector} onChange={setCollector} />
          </FormField>
          <FormField label={t("startDate")} required>
            <DatePicker value={startDate} onChange={setStartDate} />
          </FormField>
          <FormField label={t("endDate")} required>
            <DatePicker value={endDate} onChange={setEndDate} />
          </FormField>
        </div>
        <Button type="submit" className="mt-4" disabled={!customer || !collector || submitting}>
          {t("create")}
        </Button>
      </form>

      {loadError ? (
        <ErrorState error={loadError} onRetry={load} />
      ) : (
        <DataTable rows={rows} columns={columns} rowKey={(row) => row.id} loading={loading} emptyLabel={t("empty")} />
      )}

      <ConfirmationDialog
        open={Boolean(cancelTarget)}
        title={t("cancelConfirmTitle")}
        description={t("cancelConfirmBody")}
        confirmLabel={t("cancel")}
        danger
        loading={cancelling}
        onCancel={() => setCancelTarget(null)}
        onConfirm={onCancel}
      />
    </div>
  );
}

