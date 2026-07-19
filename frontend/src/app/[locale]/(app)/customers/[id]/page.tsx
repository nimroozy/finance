"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { getCustomer, listInvoices } from "@/lib/customers";
import { listServices, type IspService } from "@/lib/services";
import { listPayments } from "@/lib/payments";
import { listTickets, type Ticket } from "@/lib/tickets";
import { listAssignments } from "@/lib/assignments";
import { listCustomerEquipment, type CustomerEquipment } from "@/lib/inventory";
import { getCustomerTimeline } from "@/lib/operations";
import { isModuleEnabled } from "@/config/feature-flags";
import { useAuthStore } from "@/store/auth-store";
import type { Customer, Invoice, Payment, CustomerAssignment } from "@/lib/types";
import { StatusBadge } from "@/components/status-badge";
import { LtrValue } from "@/components/ltr-value";
import { formatDate, formatDateTime, formatMoney } from "@/lib/utils";
import { DetailHeader } from "@/components/ui/detail-header";
import { DetailSection } from "@/components/ui/detail-section";
import { RecordTabs, type RecordTab } from "@/components/ui/record-tabs";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { LoadingSkeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";

type TabId =
  | "overview"
  | "services"
  | "invoices"
  | "payments"
  | "collections"
  | "tickets"
  | "installations"
  | "equipment"
  | "timeline"
  | "documents";

type TimelineEvent = { at: string; type: string; title: string; summary: string | null };

export default function CustomerDetailPage() {
  const t = useTranslations("customers");
  const tApps = useTranslations("apps");
  const locale = useLocale();
  const params = useParams<{ id: string }>();
  const id = Number(params.id);

  const canViewServices = useAuthStore((s) => s.hasPermission("services.view"));
  const showServices = isModuleEnabled("services") && canViewServices;
  const canViewTickets = useAuthStore((s) => s.hasPermission("tickets.view"));
  const canViewPayments = useAuthStore((s) => s.hasPermission("payments.view"));
  const canViewAssignments = useAuthStore((s) => s.hasPermission("assignments.view"));
  const canViewEquipment = useAuthStore((s) => s.hasPermission("inventory.equipment.view"));

  const [customer, setCustomer] = useState<Customer | null>(null);
  const [customerLoading, setCustomerLoading] = useState(true);
  const [customerError, setCustomerError] = useState<unknown>(null);

  const [tab, setTab] = useState<TabId>("overview");

  const [invoices, setInvoices] = useState<Invoice[] | null>(null);
  const [services, setServices] = useState<IspService[] | null>(null);
  const [payments, setPayments] = useState<Payment[] | null>(null);
  const [tickets, setTickets] = useState<Ticket[] | null>(null);
  const [assignments, setAssignments] = useState<CustomerAssignment[] | null>(null);
  const [equipment, setEquipment] = useState<CustomerEquipment[] | null>(null);
  const [timeline, setTimeline] = useState<TimelineEvent[] | null>(null);
  const [tabError, setTabError] = useState<Partial<Record<TabId, unknown>>>({});
  const [tabLoading, setTabLoading] = useState<Partial<Record<TabId, boolean>>>({});

  const loadCustomer = useCallback(async () => {
    if (!Number.isFinite(id) || id <= 0) {
      setCustomerError(new Error("invalid id"));
      setCustomerLoading(false);
      return;
    }
    setCustomerLoading(true);
    setCustomerError(null);
    try {
      const res = await getCustomer(id);
      setCustomer(res.data);
    } catch (err) {
      setCustomerError(err);
    } finally {
      setCustomerLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void loadCustomer();
  }, [loadCustomer]);

  const loadTab = useCallback(
    async (target: TabId) => {
      setTabLoading((prev) => ({ ...prev, [target]: true }));
      setTabError((prev) => ({ ...prev, [target]: null }));
      try {
        switch (target) {
          case "invoices": {
            const res = await listInvoices({ customer_id: id, per_page: 50 });
            setInvoices(res.data);
            break;
          }
          case "services": {
            const res = await listServices({ customer_id: id, per_page: 20 });
            setServices(res.data);
            break;
          }
          case "payments": {
            const res = await listPayments({ customer_id: id, per_page: 30 });
            setPayments(res.data);
            break;
          }
          case "tickets": {
            const res = await listTickets({ customer_id: id, per_page: 30 });
            setTickets(res.data);
            break;
          }
          case "collections": {
            const res = await listAssignments({ customer_id: id, per_page: 20 });
            setAssignments(res.data);
            break;
          }
          case "equipment": {
            const res = await listCustomerEquipment({ customer_id: id });
            setEquipment(res.data);
            break;
          }
          case "timeline": {
            const res = await getCustomerTimeline(id);
            setTimeline((res.data as { events?: TimelineEvent[] }).events ?? (res.data as unknown as TimelineEvent[]) ?? []);
            break;
          }
          default:
            break;
        }
      } catch (err) {
        setTabError((prev) => ({ ...prev, [target]: err }));
      } finally {
        setTabLoading((prev) => ({ ...prev, [target]: false }));
      }
    },
    [id],
  );

  useEffect(() => {
    if (!customer) return;
    if (tab === "overview" || tab === "installations" || tab === "documents") return;
    const alreadyLoaded =
      (tab === "invoices" && invoices !== null) ||
      (tab === "services" && services !== null) ||
      (tab === "payments" && payments !== null) ||
      (tab === "tickets" && tickets !== null) ||
      (tab === "collections" && assignments !== null) ||
      (tab === "equipment" && equipment !== null) ||
      (tab === "timeline" && timeline !== null);
    if (!alreadyLoaded) void loadTab(tab);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tab, customer]);

  // Overview needs invoices + services + timeline summarized; load quietly once customer is ready.
  useEffect(() => {
    if (!customer) return;
    if (invoices === null) void loadTab("invoices");
    if (showServices && services === null) void loadTab("services");
    if (canViewTickets && timeline === null) void loadTab("timeline");
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [customer]);

  const tabs: RecordTab[] = useMemo(() => {
    const list: RecordTab[] = [{ id: "overview", label: t("syncedOverview") }];
    if (showServices) list.push({ id: "services", label: t("syncedServices"), count: services?.length });
    list.push({ id: "invoices", label: t("invoices"), count: invoices?.length });
    if (canViewPayments) list.push({ id: "payments", label: t("syncedPayments"), count: payments?.length });
    if (canViewAssignments) list.push({ id: "collections", label: tApps("collections") });
    if (canViewTickets) list.push({ id: "tickets", label: t("syncedTickets"), count: tickets?.length });
    list.push({ id: "installations", label: t("syncedInstallations") });
    if (canViewEquipment) list.push({ id: "equipment", label: t("syncedEquipment"), count: equipment?.length });
    list.push({ id: "timeline", label: t("syncedTimeline") });
    list.push({ id: "documents", label: t("syncedDocuments") });
    return list;
  }, [
    t,
    tApps,
    showServices,
    services,
    invoices,
    canViewPayments,
    payments,
    canViewAssignments,
    canViewTickets,
    tickets,
    canViewEquipment,
    equipment,
  ]);

  if (customerLoading) {
    return <LoadingSkeleton />;
  }

  if (customerError || !customer) {
    return (
      <div>
        <DetailHeader title={t("detailTitle")} />
        <ErrorState error={customerError} onRetry={loadCustomer} />
        <div className="mt-4">
          <Link href="/customers" className="text-sm text-primary hover:underline">
            {t("back")}
          </Link>
        </div>
      </div>
    );
  }

  const openInvoiceCount = invoices?.filter((inv) => Number(inv.effective_balance ?? inv.balance) > 0).length ?? null;
  const openTicketCount = tickets?.filter((tk) => !["closed", "resolved"].includes(tk.status)).length ?? null;

  return (
    <div>
      <DetailHeader
        title={customer.contact_name || customer.company_name || t("detailTitle")}
        meta={
          <>
            {customer.customer_number ? <LtrValue>{customer.customer_number}</LtrValue> : null}
            {customer.branch ? <span>{customer.branch.code}</span> : null}
            {customer.mobile || customer.phone ? <LtrValue>{customer.mobile || customer.phone}</LtrValue> : null}
          </>
        }
        status={
          <>
            <StatusBadge status={customer.status} />
            <StatusBadge status={customer.sync_status ?? undefined} />
          </>
        }
        actions={
          <Link href="/customers">
            <Button variant="secondary" size="sm">
              {t("back")}
            </Button>
          </Link>
        }
      />

      <RecordTabs tabs={tabs} active={tab} onChange={(next) => setTab(next as TabId)} />

      {tab === "overview" ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <DetailSection title={t("detailTitle")}>
            <dl className="grid grid-cols-2 gap-4 text-sm">
              <Field label={t("company")} value={customer.company_name || "—"} />
              <Field label={t("email")} value={customer.email || "—"} ltr />
              <Field label={t("currency")} value={customer.currency || "—"} />
              <Field label={t("paymentTerms")} value={customer.payment_terms || "—"} />
              <Field label={t("balance")} value={formatMoney(customer.outstanding_receivable, customer.currency, locale)} />
              <Field label={t("lastSynced")} value={formatDateTime(customer.last_synced_at, locale)} />
              <Field
                label={t("zohoLink")}
                value={customer.zoho_contact_id ? <LtrValue>{customer.zoho_contact_id}</LtrValue> : t("noZohoLink")}
              />
            </dl>
          </DetailSection>
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <StatTile label={t("openInvoicesSummary")} value={openInvoiceCount} />
              {canViewTickets ? <StatTile label={t("openTickets")} value={openTicketCount} /> : null}
              {showServices ? <StatTile label={t("activeServices")} value={services?.length ?? null} /> : null}
            </div>
            <DetailSection title={t("recentActivity")}>
              {timeline === null ? (
                <LoadingSkeleton rows={3} />
              ) : timeline.length === 0 ? (
                <EmptyState title={t("noData")} />
              ) : (
                <ul className="space-y-3">
                  {timeline.slice(0, 5).map((event, i) => (
                    <li key={i} className="text-sm">
                      <p className="font-medium text-foreground">{event.title}</p>
                      {event.summary ? <p className="text-muted">{event.summary}</p> : null}
                      <p className="text-xs text-muted">{formatDateTime(event.at, locale)}</p>
                    </li>
                  ))}
                </ul>
              )}
            </DetailSection>
          </div>
        </div>
      ) : null}

      {tab === "services" ? (
        <TabPanel loading={tabLoading.services} error={tabError.services} onRetry={() => loadTab("services")}>
          {services && services.length > 0 ? (
            <ul className="divide-y divide-border rounded-lg border border-border bg-surface-elevated">
              {services.map((svc) => (
                <li key={svc.id} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                  <Link href={`/services/${svc.id}`} className="font-medium text-primary hover:underline">
                    <LtrValue>{svc.service_number}</LtrValue>
                  </Link>
                  <div className="flex items-center gap-2">
                    <StatusBadge status={svc.commercial_status} />
                    <StatusBadge status={svc.operational_status} />
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState title={t("noData")} />
          )}
        </TabPanel>
      ) : null}

      {tab === "invoices" ? (
        <TabPanel loading={tabLoading.invoices} error={tabError.invoices} onRetry={() => loadTab("invoices")}>
          <InvoicesTable invoices={invoices ?? []} locale={locale} t={t} />
        </TabPanel>
      ) : null}

      {tab === "payments" ? (
        <TabPanel loading={tabLoading.payments} error={tabError.payments} onRetry={() => loadTab("payments")}>
          {payments && payments.length > 0 ? (
            <ul className="divide-y divide-border rounded-lg border border-border bg-surface-elevated">
              {payments.map((p) => (
                <li key={p.uuid} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                  <Link href={`/payments/${p.uuid}`} className="font-medium text-primary hover:underline">
                    {formatMoney(p.amount, p.currency, locale)}
                  </Link>
                  <div className="flex items-center gap-2">
                    <StatusBadge status={p.status} />
                    <span className="text-muted">{formatDate(p.confirmed_at, locale)}</span>
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState title={t("noData")} />
          )}
        </TabPanel>
      ) : null}

      {tab === "collections" ? (
        <TabPanel loading={tabLoading.collections} error={tabError.collections} onRetry={() => loadTab("collections")}>
          {assignments && assignments.length > 0 ? (
            <ul className="divide-y divide-border rounded-lg border border-border bg-surface-elevated">
              {assignments.map((a) => (
                <li key={a.id} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                  <span>{a.collector?.user?.name || t("assignedCollector")}</span>
                  <StatusBadge status={a.status} />
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState title={t("noData")} />
          )}
        </TabPanel>
      ) : null}

      {tab === "tickets" ? (
        <TabPanel loading={tabLoading.tickets} error={tabError.tickets} onRetry={() => loadTab("tickets")}>
          {tickets && tickets.length > 0 ? (
            <ul className="divide-y divide-border rounded-lg border border-border bg-surface-elevated">
              {tickets.map((tk) => (
                <li key={tk.id} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                  <Link href={`/tickets/${tk.id}`} className="font-medium text-primary hover:underline">
                    {tk.subject}
                  </Link>
                  <StatusBadge status={tk.status} />
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState title={t("noData")} />
          )}
        </TabPanel>
      ) : null}

      {tab === "installations" ? (
        <EmptyState title={t("noData")} description={t("documentsComingSoon")} />
      ) : null}

      {tab === "equipment" ? (
        <TabPanel loading={tabLoading.equipment} error={tabError.equipment} onRetry={() => loadTab("equipment")}>
          {equipment && equipment.length > 0 ? (
            <ul className="divide-y divide-border rounded-lg border border-border bg-surface-elevated">
              {equipment.map((eq) => (
                <li key={eq.id} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                  <span>{eq.product?.name_en || eq.equipment?.serial_number || `#${eq.id}`}</span>
                  <StatusBadge status={eq.status ?? undefined} />
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState title={t("noData")} />
          )}
        </TabPanel>
      ) : null}

      {tab === "timeline" ? (
        <TabPanel loading={tabLoading.timeline} error={tabError.timeline} onRetry={() => loadTab("timeline")}>
          {timeline && timeline.length > 0 ? (
            <ul className="space-y-3">
              {timeline.map((event, i) => (
                <li key={i} className="rounded-lg border border-border bg-surface-elevated p-3 text-sm">
                  <p className="font-medium text-foreground">{event.title}</p>
                  {event.summary ? <p className="text-muted">{event.summary}</p> : null}
                  <p className="text-xs text-muted">{formatDateTime(event.at, locale)}</p>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState title={t("noData")} />
          )}
        </TabPanel>
      ) : null}

      {tab === "documents" ? <EmptyState title={t("noData")} description={t("documentsComingSoon")} /> : null}
    </div>
  );
}

function Field({ label, value, ltr }: { label: string; value: React.ReactNode; ltr?: boolean }) {
  return (
    <div>
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="mt-1 font-medium text-foreground">{ltr ? <LtrValue>{value}</LtrValue> : value}</dd>
    </div>
  );
}

function StatTile({ label, value }: { label: string; value: number | null }) {
  return (
    <div className="rounded-lg border border-border bg-surface-elevated p-4">
      <p className="text-xs text-muted">{label}</p>
      <p className="mt-1 text-2xl font-semibold text-primary">{value ?? "—"}</p>
    </div>
  );
}

function TabPanel({
  loading,
  error,
  onRetry,
  children,
}: {
  loading?: boolean;
  error?: unknown;
  onRetry: () => void;
  children: React.ReactNode;
}) {
  if (error) return <ErrorState error={error} onRetry={onRetry} />;
  if (loading) return <LoadingSkeleton />;
  return <>{children}</>;
}

function InvoicesTable({
  invoices,
  locale,
  t,
}: {
  invoices: Invoice[];
  locale: string;
  t: ReturnType<typeof useTranslations<"customers">>;
}) {
  const columns: DataTableColumn<Invoice>[] = [
    { key: "number", label: t("invoiceNumber"), render: (row) => row.invoice_number || "—" },
    { key: "date", label: t("invoiceDate"), render: (row) => formatDate(row.invoice_date, locale) },
    { key: "due", label: t("dueDate"), render: (row) => formatDate(row.due_date, locale) },
    { key: "status", label: t("status"), render: (row) => <StatusBadge status={row.status} /> },
    { key: "total", label: t("total"), render: (row) => formatMoney(row.total, row.currency, locale) },
    { key: "balance", label: t("invoiceBalance"), render: (row) => formatMoney(row.balance, row.currency, locale) },
  ];
  return (
    <DataTable rows={invoices} columns={columns} rowKey={(row) => row.id} emptyLabel={t("noInvoices")} />
  );
}
