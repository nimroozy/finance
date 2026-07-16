"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  cleanupZohoFailedJobs,
  getZohoHealth,
  resumeZohoCircuit,
  syncZohoStructure,
  testZohoConnection,
  triggerZohoSync,
} from "@/lib/zoho";
import type { ZohoCircuitBreaker, ZohoHealth } from "@/lib/types";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Alert, LoadingState, PageHeader, Panel } from "@/components/ui/layout";

function tone(status: string): "success" | "danger" | "warning" | "neutral" {
  const value = status.toLowerCase();
  if (["connected", "healthy", "completed", "closed", "idle"].includes(value)) return "success";
  if (["error", "failed", "open"].includes(value)) return "danger";
  if (["running", "half_open", "paused"].includes(value)) return "warning";
  return "neutral";
}

export default function ZohoSyncHealthPage() {
  const t = useTranslations("zohoSyncHealth");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canView = useAuthStore((s) => s.hasPermission("zoho.view"));
  const canSync = useAuthStore((s) => s.hasPermission("zoho.sync"));
  const canConfigure = useAuthStore((s) => s.hasPermission("zoho.configure"));
  const [health, setHealth] = useState<ZohoHealth | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [resumeTarget, setResumeTarget] = useState<ZohoCircuitBreaker | null>(null);
  const [cleanupOpen, setCleanupOpen] = useState(false);

  const load = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      return;
    }
    setLoading(true);
    try {
      const response = await getZohoHealth();
      setHealth(response.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [canView, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function run(action: () => Promise<unknown>, success: string) {
    setBusy(true);
    setError(null);
    setMessage(null);
    try {
      await action();
      setMessage(success);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function previewCleanup() {
    setBusy(true);
    setError(null);
    try {
      const response = await cleanupZohoFailedJobs(false);
      setMessage(response.data.output || t("cleanupDryRun"));
      setCleanupOpen(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  const heartbeats = health ? Object.values(health.heartbeats) : [];
  const circuits = health ? Object.values(health.circuit_breakers) : [];
  const cursors = health ? Object.values(health.cursors) : [];

  if (!canView) return <Alert>{t("noPermission")}</Alert>;

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          <div className="flex flex-wrap gap-2">
            <Link href="/zoho/branch-mappings" className="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm hover:bg-sand-soft">
              {t("branchMappings")}
            </Link>
            <Link href="/zoho/sync-jobs" className="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm hover:bg-sand-soft">
              {t("syncJobs")}
            </Link>
          </div>
        }
      />

      {error ? <div className="mb-4"><Alert>{error}</Alert></div> : null}
      {message ? <div className="mb-4"><Alert tone="success">{message}</Alert></div> : null}
      {loading || !health ? <LoadingState label={tCommon("loading")} /> : (
        <>
          <div className="mb-4">
            <Alert tone={!health.connection || health.failed_jobs > 0 || circuits.some((item) => item.state !== "closed") ? "warning" : "success"}>
              {!health.connection
                ? t("humanDisconnected")
                : health.failed_jobs > 0
                  ? t("humanFailedJobs", { count: health.failed_jobs })
                  : circuits.some((item) => item.state !== "closed")
                    ? t("humanPaused")
                    : t("humanHealthy")}
            </Alert>
          </div>
          <Panel className="mb-6 p-4">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div><p className="text-xs text-muted">{t("connection")}</p><Badge tone={health.connection ? tone(String(health.connection.status ?? "connected")) : "neutral"}>{String(health.connection?.status ?? t("disconnected"))}</Badge></div>
              <div><p className="text-xs text-muted">{t("organization")}</p><p className="text-sm font-medium">{health.connection?.organization_name ?? "—"}</p></div>
              <div><p className="text-xs text-muted">{t("failedJobs")}</p><p className="text-sm font-medium">{health.failed_jobs}</p></div>
              <div><p className="text-xs text-muted">{t("checkedAt")}</p><p className="text-sm font-medium">{formatDateTime(health.checked_at, locale)}</p></div>
            </div>
          </Panel>

          {(canSync || canConfigure) ? (
            <Panel className="mb-6 p-4">
              <p className="mb-3 text-sm font-medium">{t("actions")}</p>
              <div className="flex flex-wrap gap-2">
                {canConfigure ? <Button variant="secondary" disabled={busy} onClick={() => void run(testZohoConnection, t("testSuccess"))}>{t("testConnection")}</Button> : null}
                {canSync ? <>
                  <Button variant="secondary" disabled={busy} onClick={() => void run(syncZohoStructure, t("structureQueued"))}>{t("syncStructure")}</Button>
                  <Button variant="secondary" disabled={busy} onClick={() => void run(() => triggerZohoSync("customers", true), t("customersQueued"))}>{t("syncCustomers")}</Button>
                  <Button variant="secondary" disabled={busy} onClick={() => void run(() => triggerZohoSync("invoices", true), t("invoicesQueued"))}>{t("syncInvoices")}</Button>
                  <Button variant="danger" disabled={busy} onClick={() => void previewCleanup()}>{t("cleanupFailedJobs")}</Button>
                </> : null}
              </div>
            </Panel>
          ) : null}

          <div className="mb-6 grid gap-4 sm:grid-cols-3">
            <Panel className="p-4"><p className="text-xs text-muted">{t("locations")}</p><p className="text-2xl font-semibold">{health.structure.locations}</p></Panel>
            <Panel className="p-4"><p className="text-xs text-muted">{t("reportingTags")}</p><p className="text-2xl font-semibold">{health.structure.reporting_tags}</p></Panel>
            <Panel className="p-4"><p className="text-xs text-muted">{t("paymentModes")}</p><p className="text-2xl font-semibold">{health.structure.payment_modes}</p></Panel>
          </div>

          <Panel className="mb-6">
            <h2 className="p-4 text-sm font-medium">{t("heartbeats")}</h2>
            <div className="overflow-x-auto"><table className="w-full min-w-[850px] text-sm"><thead className="border-y border-border bg-sand-soft/40 text-muted"><tr>{["component", "status", "lastHeartbeat", "lastStart", "lastCompletion", "lastError"].map((key) => <th key={key} className="px-4 py-3 text-start font-medium">{t(key)}</th>)}</tr></thead><tbody>
              {heartbeats.map((item) => <tr key={item.component} className="border-b border-border last:border-0"><td className="px-4 py-3">{item.component}</td><td className="px-4 py-3"><Badge tone={tone(item.status)}>{item.status}</Badge></td><td className="px-4 py-3">{formatDateTime(item.last_heartbeat_at, locale)}</td><td className="px-4 py-3">{formatDateTime(item.last_start_at, locale)}</td><td className="px-4 py-3">{formatDateTime(item.last_completion_at, locale)}</td><td className="max-w-xs truncate px-4 py-3 text-danger">{item.last_error || "—"}</td></tr>)}
            </tbody></table></div>
          </Panel>

          <Panel className="mb-6">
            <h2 className="p-4 text-sm font-medium">{t("circuitBreakers")}</h2>
            <div className="overflow-x-auto"><table className="w-full min-w-[650px] text-sm"><thead className="border-y border-border bg-sand-soft/40 text-muted"><tr><th className="px-4 py-3 text-start">{t("component")}</th><th className="px-4 py-3 text-start">{t("state")}</th><th className="px-4 py-3 text-start">{t("lastError")}</th><th className="px-4 py-3 text-start">{tCommon("actions")}</th></tr></thead><tbody>
              {circuits.map((item) => <tr key={item.sync_type} className="border-b border-border last:border-0"><td className="px-4 py-3">{item.sync_type}</td><td className="px-4 py-3"><Badge tone={tone(item.state)}>{item.state}</Badge></td><td className="px-4 py-3 text-danger">{item.last_error_message || "—"}</td><td className="px-4 py-3">{canSync && item.state !== "closed" ? <Button size="sm" variant="secondary" onClick={() => setResumeTarget(item)}>{t("resume")}</Button> : "—"}</td></tr>)}
            </tbody></table></div>
          </Panel>

          <Panel>
            <h2 className="p-4 text-sm font-medium">{t("cursors")}</h2>
            <div className="overflow-x-auto"><table className="w-full min-w-[650px] text-sm"><thead className="border-y border-border bg-sand-soft/40 text-muted"><tr><th className="px-4 py-3 text-start">{t("entity")}</th><th className="px-4 py-3 text-start">{t("successfulCursor")}</th><th className="px-4 py-3 text-start">{t("lastSuccess")}</th><th className="px-4 py-3 text-start">{t("lastError")}</th></tr></thead><tbody>
              {cursors.map((item) => <tr key={item.entity} className="border-b border-border last:border-0"><td className="px-4 py-3">{item.entity}</td><td className="px-4 py-3">{formatDateTime(item.successful_cursor, locale)}</td><td className="px-4 py-3">{formatDateTime(item.last_success_at, locale)}</td><td className="px-4 py-3 text-danger">{item.last_error || "—"}</td></tr>)}
            </tbody></table></div>
          </Panel>
        </>
      )}

      <ConfirmDialog open={Boolean(resumeTarget)} title={t("resume")} description={t("resumeConfirm")} confirmLabel={t("resume")} loading={busy} onCancel={() => setResumeTarget(null)} onConfirm={() => { if (!resumeTarget) return; const type = resumeTarget.sync_type; setResumeTarget(null); void run(() => resumeZohoCircuit(type), t("resumed")); }} />
      <ConfirmDialog open={cleanupOpen} title={t("cleanupFailedJobs")} description={t("cleanupConfirm")} confirmLabel={tCommon("confirm")} danger loading={busy} onCancel={() => setCleanupOpen(false)} onConfirm={() => { setCleanupOpen(false); void run(() => cleanupZohoFailedJobs(true), t("cleanupApplied")); }} />
    </div>
  );
}
