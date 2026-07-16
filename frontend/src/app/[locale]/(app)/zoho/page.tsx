"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  connectZoho,
  disconnectZoho,
  getZohoStatus,
  listZohoOrganizations,
  selectZohoOrganization,
  testZohoConnection,
  triggerZohoSync,
} from "@/lib/zoho";
import type { ZohoOrganization, ZohoStatus, ZohoSyncType } from "@/lib/types";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Label, Select } from "@/components/ui/form";
import {
  Alert,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

function statusTone(status: string): "success" | "danger" | "warning" | "neutral" {
  if (status === "connected") return "success";
  if (status === "error") return "danger";
  if (status === "disconnected") return "neutral";
  return "warning";
}

export default function ZohoPage() {
  const t = useTranslations("zoho");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const searchParams = useSearchParams();
  const canConfigure = useAuthStore((s) => s.hasPermission("zoho.configure"));
  const canSync = useAuthStore((s) => s.hasPermission("zoho.sync"));
  const canManageCustomers = useAuthStore((s) =>
    s.hasPermission("customers.manage"),
  );

  const [status, setStatus] = useState<ZohoStatus | null>(null);
  const [orgs, setOrgs] = useState<ZohoOrganization[]>([]);
  const [selectedOrg, setSelectedOrg] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [disconnectOpen, setDisconnectOpen] = useState(false);
  const [disconnecting, setDisconnecting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const statusRes = await getZohoStatus();
      setStatus(statusRes.data);
      if (canConfigure) {
        const orgRes = await listZohoOrganizations().catch(() => ({
          data: [] as ZohoOrganization[],
        }));
        setOrgs(orgRes.data);
        const selected =
          orgRes.data.find((o) => o.is_selected)?.zoho_org_id ??
          statusRes.data.organization_id ??
          "";
        setSelectedOrg(selected || "");
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [canConfigure, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    const connected = searchParams.get("connected");
    const oauthError = searchParams.get("error");
    if (connected === "1") {
      setSuccess(t("connectedSuccess"));
    } else if (connected === "0" || oauthError) {
      setError(oauthError ? decodeURIComponent(oauthError) : t("connectedFailed"));
    }
  }, [searchParams, t]);

  async function onConnect() {
    setBusy(true);
    setError(null);
    try {
      await connectZoho();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setBusy(false);
    }
  }

  async function onTest() {
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      await testZohoConnection();
      setSuccess(t("testSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("testFailed"));
    } finally {
      setBusy(false);
    }
  }

  async function onSelectOrg(e: React.FormEvent) {
    e.preventDefault();
    if (!selectedOrg) return;
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      await selectZohoOrganization(selectedOrg);
      setSuccess(t("orgSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onSync(type: ZohoSyncType) {
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      await triggerZohoSync(type);
      setSuccess(t("syncQueued"));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function confirmDisconnect() {
    setDisconnecting(true);
    setError(null);
    try {
      await disconnectZoho();
      setDisconnectOpen(false);
      setSuccess(t("disconnected"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setDisconnectOpen(false);
    } finally {
      setDisconnecting(false);
    }
  }

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          canConfigure ? (
            <div className="flex flex-wrap gap-2">
              {!status?.connected ? (
                <Button onClick={() => void onConnect()} disabled={busy}>
                  {t("connect")}
                </Button>
              ) : (
                <>
                  <Button
                    variant="secondary"
                    onClick={() => void onTest()}
                    disabled={busy}
                  >
                    {t("test")}
                  </Button>
                  <Button
                    variant="danger"
                    onClick={() => setDisconnectOpen(true)}
                    disabled={busy}
                  >
                    {t("disconnect")}
                  </Button>
                </>
              )}
            </div>
          ) : undefined
        }
      />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      {success ? (
        <div className="mb-4">
          <Alert tone="success">{success}</Alert>
        </div>
      ) : null}

      <Panel className="mb-6 p-4">
        {loading || !status ? (
          <LoadingState label={tCommon("loading")} />
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <p className="text-xs text-muted">{t("status")}</p>
              <div className="mt-1">
                <Badge tone={statusTone(status.status)}>
                  {status.connected
                    ? t("connected")
                    : status.status === "error"
                      ? t("error")
                      : t("disconnected")}
                </Badge>
              </div>
            </div>
            <div>
              <p className="text-xs text-muted">{t("organization")}</p>
              <p className="mt-1 text-sm font-medium">
                {status.organization_name || t("none")}
              </p>
            </div>
            <div>
              <p className="text-xs text-muted">{t("dataCenter")}</p>
              <p className="mt-1 text-sm font-medium">
                {status.data_center || t("none")}
              </p>
            </div>
            <div>
              <p className="text-xs text-muted">{t("tokenExpires")}</p>
              <p className="mt-1 text-sm font-medium">
                {formatDateTime(status.token_expires_at, locale)}
              </p>
            </div>
            <div>
              <p className="text-xs text-muted">{t("lastConnected")}</p>
              <p className="mt-1 text-sm font-medium">
                {formatDateTime(status.last_connected_at, locale)}
              </p>
            </div>
            <div>
              <p className="text-xs text-muted">{t("lastCustomerSync")}</p>
              <p className="mt-1 text-sm font-medium">
                {formatDateTime(status.last_customer_sync_at, locale)}
              </p>
            </div>
            <div>
              <p className="text-xs text-muted">{t("lastInvoiceSync")}</p>
              <p className="mt-1 text-sm font-medium">
                {formatDateTime(status.last_invoice_sync_at, locale)}
              </p>
            </div>
            {status.last_error ? (
              <div className="sm:col-span-2 lg:col-span-3">
                <p className="text-xs text-muted">{t("lastError")}</p>
                <p className="mt-1 text-sm text-danger">{status.last_error}</p>
              </div>
            ) : null}
          </div>
        )}
      </Panel>

      {canConfigure && status?.connected ? (
        <Panel className="mb-6 p-4">
          <form
            onSubmit={onSelectOrg}
            className="flex flex-col gap-3 sm:flex-row sm:items-end"
          >
            <div className="flex-1">
              <Label htmlFor="org">{t("selectOrg")}</Label>
              <Select
                id="org"
                value={selectedOrg}
                onChange={(e) => setSelectedOrg(e.target.value)}
              >
                <option value="">—</option>
                {orgs.map((org) => (
                  <option key={org.id} value={org.zoho_org_id}>
                    {org.name}
                    {org.currency_code ? ` (${org.currency_code})` : ""}
                  </option>
                ))}
              </Select>
            </div>
            <Button type="submit" disabled={busy || !selectedOrg}>
              {t("saveOrg")}
            </Button>
          </form>
        </Panel>
      ) : null}

      {canSync && status?.connected ? (
        <Panel className="mb-6 p-4">
          <div className="flex flex-wrap gap-2">
            <Button
              variant="secondary"
              disabled={busy}
              onClick={() => void onSync("customers")}
            >
              {t("syncCustomers")}
            </Button>
            <Button
              variant="secondary"
              disabled={busy}
              onClick={() => void onSync("invoices")}
            >
              {t("syncInvoices")}
            </Button>
            <Button disabled={busy} onClick={() => void onSync("full")}>
              {t("syncFull")}
            </Button>
          </div>
        </Panel>
      ) : null}

      <Panel className="p-4">
        <p className="mb-3 text-sm font-medium text-foreground">{t("links")}</p>
        <div className="flex flex-wrap gap-2">
          <Link
            href="/zoho/sync-health"
            className="rounded-md border border-primary bg-primary px-3 py-2 text-sm text-white hover:opacity-90"
          >
            {t("syncHealth")}
          </Link>
          {canConfigure ? (
            <Link
              href="/zoho/branch-mappings"
              className="rounded-md border border-primary px-3 py-2 text-sm font-medium text-primary hover:bg-sand-soft"
            >
              {t("branchMappings")}
            </Link>
          ) : null}
          <Link
            href="/zoho/sync-jobs"
            className="rounded-md border border-border px-3 py-2 text-sm hover:bg-sand-soft"
          >
            {t("syncJobs")}
          </Link>
          <Link
            href="/zoho/api-logs"
            className="rounded-md border border-border px-3 py-2 text-sm hover:bg-sand-soft"
          >
            {t("apiLogs")}
          </Link>
          {canConfigure || canManageCustomers ? (
            <Link
              href="/zoho/unmapped"
              className="rounded-md border border-border px-3 py-2 text-sm hover:bg-sand-soft"
            >
              {t("unmapped")}
            </Link>
          ) : null}
        </div>
      </Panel>

      <ConfirmDialog
        open={disconnectOpen}
        title={t("disconnect")}
        description={t("disconnectConfirm")}
        confirmLabel={t("disconnect")}
        danger
        loading={disconnecting}
        onCancel={() => setDisconnectOpen(false)}
        onConfirm={() => void confirmDisconnect()}
      />
    </div>
  );
}
