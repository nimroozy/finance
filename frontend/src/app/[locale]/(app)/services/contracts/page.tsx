"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  createServiceContract,
  listServiceContracts,
  listServices,
  type IspService,
  type ServiceContract,
} from "@/lib/services";
import { useAuthStore } from "@/store/auth-store";
import {
  BranchPicker,
  CustomerPicker,
  EmptyWorkspace,
  ErrorWorkspace,
  WorkspaceHeader,
} from "@/components/ops";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/form";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

export default function ServiceContractsPage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const canManage = useAuthStore((s) => s.hasPermission("services.contracts.manage"));
  const [rows, setRows] = useState<ServiceContract[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [branchId, setBranchId] = useState("");
  const [branchLabel, setBranchLabel] = useState<string | null>(null);
  const [customerId, setCustomerId] = useState("");
  const [customerLabel, setCustomerLabel] = useState<string | null>(null);
  const [recurring, setRecurring] = useState("");
  const [serviceId, setServiceId] = useState("");
  const [services, setServices] = useState<IspService[]>([]);
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [res, svcRes] = await Promise.all([
        listServiceContracts({ per_page: 50 }),
        listServices({ per_page: 100 }).catch(() => null),
      ]);
      setRows(res.data);
      if (svcRes) setServices(svcRes.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onCreate(e: React.FormEvent) {
    e.preventDefault();
    if (!canManage || !branchId || !customerId) {
      setError(t("requiredFields"));
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      await createServiceContract({
        branch_id: Number(branchId),
        customer_id: Number(customerId),
        service_id: serviceId ? Number(serviceId) : undefined,
        start_date: startDate || undefined,
        end_date: endDate || undefined,
        recurring_fee: recurring ? Number(recurring) : undefined,
      });
      setSuccess(t("contractSuccess"));
      setRecurring("");
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  const columns: DataTableColumn<ServiceContract>[] = [
    {
      key: "number",
      label: t("columns.contractNumber"),
      render: (row) => (
        <Link href={`/services/contracts/${row.id}`} className="text-primary hover:underline">
          {row.contract_number || `#${row.id}`}
        </Link>
      ),
    },
    { key: "customer", label: t("columns.customer"), render: (row) => String(row.customer_id) },
    { key: "status", label: t("columns.status"), render: (row) => row.status || "—" },
    { key: "fee", label: t("columns.mrr"), render: (row) => String(row.recurring_fee ?? "—") },
    {
      key: "actions",
      label: tCommon("actions"),
      render: (row) => (
        <Link href={`/services/contracts/${row.id}`}>
          <Button size="sm" variant="secondary">
            {tCommon("edit")}
          </Button>
        </Link>
      ),
    },
  ];

  return (
    <div className="space-y-4" data-testid="services-contracts">
      <WorkspaceHeader title={t("contractsTitle")} subtitle={t("contractsSubtitle")} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}
      {canManage ? (
        <Panel className="p-4">
          <form className="grid gap-3 sm:grid-cols-2" onSubmit={onCreate}>
            <BranchPicker
              label={t("fields.branch")}
              value={branchId || null}
              selectedLabel={branchLabel}
              onChange={(opt) => {
                setBranchId(opt ? String(opt.id) : "");
                setBranchLabel(opt?.label ?? null);
              }}
            />
            <CustomerPicker
              label={t("fields.customer")}
              branchId={branchId ? Number(branchId) : undefined}
              value={customerId || null}
              selectedLabel={customerLabel}
              onChange={(opt) => {
                setCustomerId(opt ? String(opt.id) : "");
                setCustomerLabel(opt?.label ?? null);
              }}
            />
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("columns.service")}</span>
              <select
                className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                value={serviceId}
                onChange={(e) => setServiceId(e.target.value)}
              >
                <option value="">{t("fields.selectPackage")}</option>
                {services
                  .filter((s) => !customerId || String(s.customer_id) === customerId)
                  .map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.service_number}
                    </option>
                  ))}
              </select>
            </label>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("fields.startDate")}</span>
              <Input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
            </label>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("fields.endDate")}</span>
              <Input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} />
            </label>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("fields.mrr")}</span>
              <Input type="number" value={recurring} onChange={(e) => setRecurring(e.target.value)} />
            </label>
            <div className="flex items-end">
              <Button type="submit" disabled={busy}>
                {t("newContract")}
              </Button>
            </div>
          </form>
        </Panel>
      ) : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error && !rows.length ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && rows.length === 0 ? <EmptyWorkspace label={t("emptyContracts")} /> : null}
      {!loading && rows.length > 0 ? (
        <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} />
      ) : null}
    </div>
  );
}
