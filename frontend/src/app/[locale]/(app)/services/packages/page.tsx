"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  createServicePackage,
  listServiceAccessTechnologies,
  listServicePackages,
  listServiceTypes,
  type ServiceAccessTechnology,
  type ServicePackage,
  type ServiceType,
} from "@/lib/services";
import { useAuthStore } from "@/store/auth-store";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  MobileRecordCard,
  WorkspaceHeader,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { Input, Select } from "@/components/ui/form";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

export default function ServicePackagesPage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const canManage = useAuthStore((s) => s.hasPermission("services.packages.manage"));
  const [rows, setRows] = useState<ServicePackage[]>([]);
  const [types, setTypes] = useState<ServiceType[]>([]);
  const [techs, setTechs] = useState<ServiceAccessTechnology[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [form, setForm] = useState({
    code: "",
    name: "",
    service_type_code: "",
    access_technology_code: "",
    monthly_price: "",
    download_mbps: "",
    upload_mbps: "",
  });

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [pkgRes, typeRes, techRes] = await Promise.all([
        listServicePackages({ per_page: 100 }),
        listServiceTypes().catch(() => ({ data: [] as ServiceType[] })),
        listServiceAccessTechnologies().catch(() => ({ data: [] as ServiceAccessTechnology[] })),
      ]);
      setRows(pkgRes.data);
      setTypes(typeRes.data);
      setTechs(techRes.data);
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
    if (!canManage) return;
    if (!form.code.trim() || !form.name.trim() || !form.service_type_code || !form.access_technology_code) {
      setError(t("requiredFields"));
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      await createServicePackage({
        code: form.code.trim(),
        name: form.name.trim(),
        service_type_code: form.service_type_code,
        access_technology_code: form.access_technology_code,
        monthly_price: form.monthly_price ? Number(form.monthly_price) : undefined,
        download_mbps: form.download_mbps ? Number(form.download_mbps) : undefined,
        upload_mbps: form.upload_mbps ? Number(form.upload_mbps) : undefined,
      });
      setSuccess(t("packageSuccess"));
      setShowForm(false);
      setForm({
        code: "",
        name: "",
        service_type_code: "",
        access_technology_code: "",
        monthly_price: "",
        download_mbps: "",
        upload_mbps: "",
      });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  const columns: DataTableColumn<ServicePackage>[] = [
    {
      key: "code",
      label: t("columns.code"),
      render: (row) => (
        <Link href={`/services/packages/${row.id}`} className="font-medium text-primary hover:underline">
          {row.code}
        </Link>
      ),
    },
    { key: "name", label: t("columns.name"), render: (row) => row.name },
    {
      key: "speed",
      label: t("columns.speed"),
      render: (row) =>
        row.download_mbps != null ? `${row.download_mbps}/${row.upload_mbps ?? "—"} Mbps` : "—",
    },
    {
      key: "price",
      label: t("columns.mrr"),
      render: (row) => String(row.monthly_price ?? "—"),
    },
    {
      key: "active",
      label: t("columns.status"),
      render: (row) => <StatusBadge status={row.is_active === false ? "inactive" : "active"} />,
    },
  ];

  return (
    <div className="space-y-4" data-testid="services-packages">
      <WorkspaceHeader
        title={t("packagesTitle")}
        subtitle={t("packagesSubtitle")}
        actions={
          canManage ? (
            <Button data-testid="new-package" onClick={() => setShowForm((v) => !v)}>
              {t("newPackage")}
            </Button>
          ) : null
        }
      />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}
      {showForm && canManage ? (
        <Panel className="p-4">
          <form className="grid gap-3 sm:grid-cols-2" onSubmit={onCreate} data-testid="package-form">
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("columns.code")}</span>
              <Input
                data-testid="package-code"
                value={form.code}
                onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))}
                required
              />
            </label>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("columns.name")}</span>
              <Input
                data-testid="package-name"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                required
              />
            </label>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("fields.serviceType")}</span>
              <Select
                value={form.service_type_code}
                onChange={(e) => setForm((f) => ({ ...f, service_type_code: e.target.value }))}
                required
              >
                <option value="">{t("fields.selectType")}</option>
                {types.map((ty) => (
                  <option key={ty.code} value={ty.code}>
                    {ty.name}
                  </option>
                ))}
              </Select>
            </label>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("fields.technology")}</span>
              <Select
                value={form.access_technology_code}
                onChange={(e) => setForm((f) => ({ ...f, access_technology_code: e.target.value }))}
                required
              >
                <option value="">{t("fields.selectType")}</option>
                {techs.map((tech) => (
                  <option key={tech.code} value={tech.code}>
                    {tech.name}
                  </option>
                ))}
              </Select>
            </label>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("fields.mrr")}</span>
              <Input
                type="number"
                value={form.monthly_price}
                onChange={(e) => setForm((f) => ({ ...f, monthly_price: e.target.value }))}
              />
            </label>
            <div className="flex items-end gap-2">
              <Button type="submit" disabled={busy} data-testid="create-package">
                {tCommon("create")}
              </Button>
            </div>
          </form>
        </Panel>
      ) : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error && !rows.length ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && rows.length === 0 ? <EmptyWorkspace label={t("emptyPackages")} /> : null}
      {!loading && rows.length > 0 ? (
        <>
          <div className="hidden md:block">
            <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} />
          </div>
          <div className="space-y-3 md:hidden">
            {rows.map((row) => (
              <MobileRecordCard
                key={row.id}
                href={`/services/packages/${row.id}`}
                title={row.code}
                subtitle={row.name}
                badges={<StatusBadge status={row.is_active === false ? "inactive" : "active"} />}
                meta={String(row.monthly_price ?? "")}
              />
            ))}
          </div>
        </>
      ) : null}
    </div>
  );
}
