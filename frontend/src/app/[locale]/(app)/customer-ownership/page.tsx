"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { PageHeader } from "@/components/ui/layout";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { CustomerPicker, CollectorPicker } from "@/components/ui/pickers";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/form";
import { ErrorState } from "@/components/ui/error-state";
import { ValidationSummary } from "@/components/ui/validation-summary";
import { useToast } from "@/components/ui/toast";
import { LtrValue } from "@/components/ltr-value";
import type { SearchableOption } from "@/components/ui/searchable-select";
import { assignOwnership, listOwnership, type CustomerOwnership } from "@/lib/ownership";
import { ApiError } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import { useLocale } from "next-intl";

export default function OwnershipPage() {
  const t = useTranslations("ownershipPage");
  const locale = useLocale();
  const { show } = useToast();
  const [rows, setRows] = useState<CustomerOwnership[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<unknown>(null);

  const [customer, setCustomer] = useState<SearchableOption | null>(null);
  const [collector, setCollector] = useState<SearchableOption | null>(null);
  const [startDate, setStartDate] = useState(new Date().toISOString().slice(0, 10));
  const [reason, setReason] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<unknown>(null);

  async function load() {
    setLoading(true);
    setLoadError(null);
    try {
      const res = await listOwnership({ status: "active" });
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
      await assignOwnership({
        customer_id: Number(customer.id),
        collector_id: Number(collector.id),
        start_date: startDate,
        reason: reason || undefined,
      });
      show({ tone: "success", title: t("assignSuccess") });
      setCustomer(null);
      setCollector(null);
      setReason("");
      await load();
    } catch (error) {
      setSubmitError(error);
    } finally {
      setSubmitting(false);
    }
  }

  const columns: DataTableColumn<CustomerOwnership>[] = [
    {
      key: "customer",
      label: t("colCustomer"),
      render: (row) => row.customer?.contact_name || row.customer?.company_name || `#${row.customer_id}`,
    },
    {
      key: "collector",
      label: t("colCollector"),
      render: (row) => row.collector?.user?.name || `#${row.collector_id}`,
    },
    {
      key: "branch",
      label: t("colBranch"),
      render: (row) => row.branch?.name_en || row.branch?.name_fa || "—",
    },
    {
      key: "start",
      label: t("colStart"),
      render: (row) => <LtrValue>{formatDate(row.start_date, locale)}</LtrValue>,
    },
    {
      key: "status",
      label: t("colStatus"),
      render: (row) => <StatusBadge status={row.status} />,
    },
  ];

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          <>
            <Link href="/customer-ownership/bulk">
              <Button variant="secondary" size="sm">
                {t("viewBulk")}
              </Button>
            </Link>
            <Link href="/customer-ownership/transfers">
              <Button variant="secondary" size="sm">
                {t("viewTransfer")}
              </Button>
            </Link>
            <Link href="/customer-ownership/unassigned">
              <Button variant="secondary" size="sm">
                {t("viewUnassigned")}
              </Button>
            </Link>
          </>
        }
      />

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
          <FormField label={t("collector")} required>
            <CollectorPicker value={collector} onChange={setCollector} />
          </FormField>
          <FormField label={t("startDate")} required>
            <DatePicker value={startDate} onChange={setStartDate} />
          </FormField>
          <FormField label={t("reasonOptional")}>
            <Input value={reason} onChange={(e) => setReason(e.target.value)} />
          </FormField>
        </div>
        <Button type="submit" className="mt-4" disabled={!customer || !collector || submitting}>
          {t("assign")}
        </Button>
      </form>

      {loadError ? (
        <ErrorState error={loadError} onRetry={load} />
      ) : (
        <DataTable
          rows={rows}
          columns={columns}
          rowKey={(row) => row.id}
          loading={loading}
          emptyLabel={t("empty")}
        />
      )}
    </div>
  );
}
