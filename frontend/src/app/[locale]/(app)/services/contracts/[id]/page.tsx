"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  getServiceContract,
  updateServiceContract,
  type ServiceContract,
} from "@/lib/services";
import { useAuthStore } from "@/store/auth-store";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  RecordSummary,
  WorkspaceHeader,
} from "@/components/ops";
import { Button } from "@/components/ui/button";
import { Input, Select } from "@/components/ui/form";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

export default function ServiceContractDetailPage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const params = useParams();
  const id = String(params.id || "");
  const canManage = useAuthStore((s) => s.hasPermission("services.contracts.manage"));

  const [contract, setContract] = useState<ServiceContract | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [status, setStatus] = useState("");
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [recurring, setRecurring] = useState("");
  const [zohoRef, setZohoRef] = useState("");

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await getServiceContract(id);
      setContract(res.data);
      setStatus(res.data.status || "");
      setStartDate(res.data.start_date ? String(res.data.start_date).slice(0, 10) : "");
      setEndDate(res.data.end_date ? String(res.data.end_date).slice(0, 10) : "");
      setRecurring(res.data.recurring_fee != null ? String(res.data.recurring_fee) : "");
      setZohoRef(res.data.zoho_reference || "");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setContract(null);
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onSave(e: React.FormEvent) {
    e.preventDefault();
    if (!canManage || !contract) return;
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      await updateServiceContract(contract.id, {
        status: status || undefined,
        start_date: startDate || undefined,
        end_date: endDate || undefined,
        recurring_fee: recurring ? Number(recurring) : undefined,
        zoho_reference: zohoRef || undefined,
      });
      setSuccess(t("contractUpdateSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (error && !contract) return <ErrorWorkspace message={error} onRetry={() => void load()} />;
  if (!contract) return <EmptyWorkspace label={t("emptyContracts")} />;

  return (
    <div className="space-y-4" data-testid="service-contract-detail">
      <WorkspaceHeader
        title={contract.contract_number || `Contract #${contract.id}`}
        subtitle={t("contractsSubtitle")}
        actions={
          <Link href="/services/contracts">
            <Button variant="secondary">{tCommon("back")}</Button>
          </Link>
        }
      />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}

      <Panel className="p-4">
        <RecordSummary
          items={[
            { label: t("columns.customer"), value: String(contract.customer_id) },
            { label: t("columns.service"), value: contract.service_id ? String(contract.service_id) : "—" },
            { label: t("columns.status"), value: contract.status || "—" },
            { label: t("columns.mrr"), value: String(contract.recurring_fee ?? "—") },
          ]}
        />
      </Panel>

      {canManage ? (
        <Panel className="p-4" data-testid="service-contract-edit">
          <h2 className="mb-3 text-sm font-semibold">{t("editContract")}</h2>
          <form className="grid gap-3 sm:grid-cols-2" onSubmit={onSave}>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("columns.status")}</span>
              <Select value={status} onChange={(e) => setStatus(e.target.value)}>
                <option value="">{t("fields.selectStatus")}</option>
                <option value="draft">draft</option>
                <option value="active">active</option>
                <option value="expired">expired</option>
                <option value="cancelled">cancelled</option>
              </Select>
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
            <label className="block space-y-1 sm:col-span-2">
              <span className="text-sm font-medium">{t("fields.zohoReference")}</span>
              <Input value={zohoRef} onChange={(e) => setZohoRef(e.target.value)} />
            </label>
            <div className="flex items-end">
              <Button type="submit" disabled={busy}>
                {t("actions.saveContract")}
              </Button>
            </div>
          </form>
        </Panel>
      ) : null}
    </div>
  );
}
