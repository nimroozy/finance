"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  createServicePackageVersion,
  getServicePackage,
  type ServicePackage,
} from "@/lib/services";
import { useAuthStore } from "@/store/auth-store";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  RecordSummary,
  WorkspaceHeader,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/form";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

export default function ServicePackageDetailPage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const params = useParams();
  const id = String(params.id || "");
  const canManage = useAuthStore((s) => s.hasPermission("services.packages.manage"));

  const [pkg, setPkg] = useState<ServicePackage | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [monthlyPrice, setMonthlyPrice] = useState("");
  const [download, setDownload] = useState("");
  const [upload, setUpload] = useState("");

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await getServicePackage(id);
      setPkg(res.data);
      setMonthlyPrice(String(res.data.monthly_price ?? ""));
      setDownload(res.data.download_mbps != null ? String(res.data.download_mbps) : "");
      setUpload(res.data.upload_mbps != null ? String(res.data.upload_mbps) : "");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setPkg(null);
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onVersion(e: React.FormEvent) {
    e.preventDefault();
    if (!pkg || !monthlyPrice) return;
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      await createServicePackageVersion(pkg.id, {
        monthly_price: Number(monthlyPrice),
        download_mbps: download ? Number(download) : undefined,
        upload_mbps: upload ? Number(upload) : undefined,
      });
      setSuccess(t("versionSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (error && !pkg) return <ErrorWorkspace message={error} onRetry={() => void load()} />;
  if (!pkg) return <EmptyWorkspace label={t("packageNotFound")} />;

  return (
    <div className="space-y-4" data-testid="package-detail">
      <WorkspaceHeader
        title={pkg.code}
        subtitle={pkg.name}
        badges={[{
          label: pkg.is_active === false ? "inactive" : "active",
          tone: pkg.is_active === false ? "neutral" : "success",
        }]}
        actions={
          <Link href="/services/packages">
            <Button variant="secondary">{tCommon("back")}</Button>
          </Link>
        }
        meta={<StatusBadge status={pkg.is_active === false ? "inactive" : "active"} />}
      />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}
      <Panel className="p-4">
        <RecordSummary
          items={[
            { label: t("fields.serviceType"), value: pkg.service_type_code },
            { label: t("fields.technology"), value: pkg.access_technology_code },
            {
              label: t("columns.speed"),
              value:
                pkg.download_mbps != null
                  ? `${pkg.download_mbps}/${pkg.upload_mbps ?? "—"} Mbps`
                  : "—",
            },
            { label: t("columns.mrr"), value: String(pkg.monthly_price ?? "—") },
            {
              label: t("fields.versions"),
              value: String(pkg.versions?.length ?? 0),
            },
          ]}
        />
      </Panel>
      {canManage ? (
        <Panel className="p-4">
          <h2 className="mb-3 text-sm font-semibold">{t("newVersion")}</h2>
          <form className="grid max-w-lg gap-3 sm:grid-cols-3" onSubmit={onVersion}>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("fields.mrr")}</span>
              <Input
                data-testid="version-price"
                type="number"
                value={monthlyPrice}
                onChange={(e) => setMonthlyPrice(e.target.value)}
                required
              />
            </label>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("fields.download")}</span>
              <Input type="number" value={download} onChange={(e) => setDownload(e.target.value)} />
            </label>
            <label className="block space-y-1">
              <span className="text-sm font-medium">{t("fields.upload")}</span>
              <Input type="number" value={upload} onChange={(e) => setUpload(e.target.value)} />
            </label>
            <div className="sm:col-span-3">
              <Button type="submit" disabled={busy} data-testid="create-version">
                {t("newVersion")}
              </Button>
            </div>
          </form>
        </Panel>
      ) : null}
    </div>
  );
}
