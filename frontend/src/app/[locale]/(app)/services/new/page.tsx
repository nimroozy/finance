"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  createService,
  listServicePackages,
  listServiceTypes,
  type ServicePackage,
  type ServiceType,
} from "@/lib/services";
import { useAuthStore } from "@/store/auth-store";
import { BranchPicker, CustomerPicker, WorkspaceHeader } from "@/components/ops";
import { Button } from "@/components/ui/button";
import { Input, Select, TextArea } from "@/components/ui/form";
import { Alert, Panel } from "@/components/ui/layout";

export default function NewServicePage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const canCreate = useAuthStore((s) => s.hasPermission("services.create"));

  const [branchId, setBranchId] = useState("");
  const [branchLabel, setBranchLabel] = useState<string | null>(null);
  const [customerId, setCustomerId] = useState("");
  const [customerLabel, setCustomerLabel] = useState<string | null>(null);
  const [packageId, setPackageId] = useState("");
  const [serviceType, setServiceType] = useState("");
  const [mrr, setMrr] = useState("");
  const [notes, setNotes] = useState("");
  const [packages, setPackages] = useState<ServicePackage[]>([]);
  const [types, setTypes] = useState<ServiceType[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void listServicePackages({ per_page: 100, is_active: true })
      .then((res) => setPackages(res.data))
      .catch(() => setPackages([]));
    void listServiceTypes()
      .then((res) => setTypes(res.data))
      .catch(() => setTypes([]));
  }, []);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (busy || !canCreate) return;
    if (!branchId || !customerId) {
      setError(t("requiredFields"));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const res = await createService({
        branch_id: Number(branchId),
        customer_id: Number(customerId),
        package_id: packageId ? Number(packageId) : undefined,
        service_type_code: serviceType || undefined,
        mrr: mrr ? Number(mrr) : undefined,
        notes: notes.trim() || undefined,
      });
      router.push(`/services/${res.data.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  if (!canCreate) {
    return (
      <div className="space-y-4" data-testid="permission-denied">
        <WorkspaceHeader title={t("newService")} subtitle={t("newServiceSubtitle")} />
        <Alert tone="danger">{t("permissionDenied")}</Alert>
      </div>
    );
  }

  return (
    <div className="space-y-4" data-testid="new-service-page">
      <WorkspaceHeader
        title={t("newService")}
        subtitle={t("newServiceSubtitle")}
        actions={
          <Link href="/services">
            <Button variant="secondary">{tCommon("cancel")}</Button>
          </Link>
        }
      />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      <Panel>
        <form className="grid gap-4 p-4 sm:grid-cols-2" onSubmit={onSubmit}>
          <div className="sm:col-span-2">
            <BranchPicker
              label={t("fields.branch")}
              value={branchId || null}
              selectedLabel={branchLabel}
              onChange={(opt) => {
                setBranchId(opt ? String(opt.id) : "");
                setBranchLabel(opt?.label ?? null);
              }}
            />
          </div>
          <div className="sm:col-span-2">
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
          </div>
          <label className="block space-y-1">
            <span className="text-sm font-medium">{t("fields.package")}</span>
            <Select
              data-testid="service-package"
              value={packageId}
              onChange={(e) => setPackageId(e.target.value)}
            >
              <option value="">{t("fields.selectPackage")}</option>
              {packages.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.code} — {p.name}
                </option>
              ))}
            </Select>
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium">{t("fields.serviceType")}</span>
            <Select value={serviceType} onChange={(e) => setServiceType(e.target.value)}>
              <option value="">{t("fields.selectType")}</option>
              {types.map((ty) => (
                <option key={ty.code} value={ty.code}>
                  {ty.name}
                </option>
              ))}
            </Select>
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium">{t("fields.mrr")}</span>
            <Input
              data-testid="service-mrr"
              type="number"
              value={mrr}
              onChange={(e) => setMrr(e.target.value)}
            />
          </label>
          <label className="block space-y-1 sm:col-span-2">
            <span className="text-sm font-medium">{t("fields.notes")}</span>
            <TextArea value={notes} onChange={(e) => setNotes(e.target.value)} rows={3} />
          </label>
          <div className="sm:col-span-2">
            <Button type="submit" disabled={busy} data-testid="create-service">
              {tCommon("create")}
            </Button>
          </div>
        </form>
      </Panel>
    </div>
  );
}
