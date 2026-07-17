"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams, useSearchParams } from "next/navigation";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listCustomerEquipment, type CustomerEquipment } from "@/lib/inventory";
import {
  ACTIVATION_CHECKLIST,
  activateService,
  cancelService,
  confirmServiceOnline,
  createChangeRequest,
  getActivationChecklist,
  getService,
  getServiceBilling,
  getServiceTimeline,
  listServiceLocations,
  listServicePackages,
  placeFinanceHold,
  reactivateService,
  relocateService,
  suspendService,
  type ActivationChecklist,
  type IspService,
  type ServiceBillingView,
  type ServiceLocation,
  type ServicePackage,
  type ServiceTimelineEvent,
} from "@/lib/services";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  RecordSummary,
  ResponsiveTabs,
  StatusActionMenu,
  Timeline,
  WorkspaceHeader,
  type ResponsiveTab,
  type StatusTransition,
  type TimelineItem,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Input, Select, TextArea } from "@/components/ui/form";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

const ACTION_TABS = new Set([
  "overview",
  "billing",
  "equipment",
  "timeline",
  "change",
  "relocate",
  "suspend",
  "cancel",
  "hold",
]);

export default function ServiceDetailPage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const params = useParams();
  const searchParams = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const id = String(params.id || "");

  const canActivate = useAuthStore((s) => s.hasPermission("services.activate"));
  const canSuspend = useAuthStore((s) => s.hasPermission("services.suspend"));
  const canCancel = useAuthStore((s) => s.hasPermission("services.cancel"));
  const canChange = useAuthStore((s) => s.hasPermission("services.change"));
  const canRelocate = useAuthStore((s) => s.hasPermission("services.relocate"));
  const canHold = useAuthStore((s) => s.hasPermission("services.finance_holds.manage"));
  const canNocConfirm = useAuthStore(
    (s) => s.hasPermission("services.noc.view") || s.hasPermission("services.activate"),
  );
  const canBilling = useAuthStore((s) => s.hasPermission("services.billing.view") || s.hasPermission("services.view"));

  const initialTab = searchParams.get("tab") || "overview";
  const [tab, setTab] = useState(ACTION_TABS.has(initialTab) ? initialTab : "overview");
  const [service, setService] = useState<IspService | null>(null);
  const [billing, setBilling] = useState<ServiceBillingView | null>(null);
  const [timeline, setTimeline] = useState<ServiceTimelineEvent[]>([]);
  const [equipment, setEquipment] = useState<CustomerEquipment[]>([]);
  const [packages, setPackages] = useState<ServicePackage[]>([]);
  const [locations, setLocations] = useState<ServiceLocation[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [changePackageId, setChangePackageId] = useState("");
  const [changeReason, setChangeReason] = useState("");
  const [relocateLocationId, setRelocateLocationId] = useState("");
  const [holdReason, setHoldReason] = useState("");
  const [checklist, setChecklist] = useState<ActivationChecklist | null>(null);
  const [nocReason, setNocReason] = useState("");
  const [cancelId, setCancelId] = useState<number | null>(null);
  const [changeStep, setChangeStep] = useState(1);
  const [relocateStep, setRelocateStep] = useState(1);

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await getService(id);
      setService(res.data);
      const customerId = res.data.customer_id;
      const [billingRes, timelineRes, equipRes, pkgRes, locRes, checklistRes] = await Promise.all([
        canBilling ? getServiceBilling(id).catch(() => null) : Promise.resolve(null),
        getServiceTimeline(id).catch(() => null),
        listCustomerEquipment({ customer_id: customerId, per_page: 50 }).catch(() => null),
        listServicePackages({ per_page: 100 }).catch(() => null),
        listServiceLocations({ customer_id: customerId, per_page: 100 }).catch(() => null),
        getActivationChecklist(id).catch(() => null),
      ]);
      if (checklistRes) setChecklist(checklistRes.data);
      if (billingRes) setBilling(billingRes.data);
      if (timelineRes) setTimeline(timelineRes.data);
      if (equipRes) {
        const rows = equipRes.data.filter(
          (e) => !(e as CustomerEquipment & { service_id?: number }).service_id ||
            (e as CustomerEquipment & { service_id?: number }).service_id === res.data.id,
        );
        setEquipment(rows);
      }
      if (pkgRes) setPackages(pkgRes.data);
      if (locRes) setLocations(locRes.data);
      setChangePackageId(res.data.package_id ? String(res.data.package_id) : "");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setService(null);
    } finally {
      setLoading(false);
    }
  }, [canBilling, id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    const next = searchParams.get("tab") || "overview";
    if (ACTION_TABS.has(next)) setTab(next);
  }, [searchParams]);

  function onTabChange(next: string) {
    setTab(next);
    const params = new URLSearchParams(searchParams.toString());
    if (next === "overview") params.delete("tab");
    else params.set("tab", next);
    const qs = params.toString();
    router.replace(qs ? `${pathname}?${qs}` : pathname);
  }

  const transitions: StatusTransition[] = useMemo(() => {
    if (!service) return [];
    const items: StatusTransition[] = [];
    const status = service.commercial_status;
    if (
      canActivate &&
      (status === "draft" || status === "pending_activation")
    ) {
      items.push({ id: "activate", label: t("actions.activate") });
    }
    if (canSuspend && status === "active") {
      items.push({ id: "suspend", label: t("actions.suspend"), requiresReason: true, danger: true });
    }
    if (canActivate && status === "suspended") {
      items.push({ id: "reactivate", label: t("actions.reactivate") });
    }
    if (canCancel && !["cancelled", "pending_cancellation"].includes(status)) {
      items.push({ id: "cancel", label: t("actions.cancel"), requiresReason: true, danger: true });
    }
    return items;
  }, [canActivate, canCancel, canSuspend, service, t]);

  const tabs: ResponsiveTab[] = [
    { id: "overview", label: t("tabs.overview") },
    { id: "billing", label: t("tabs.billing") },
    { id: "equipment", label: t("tabs.equipment") },
    { id: "timeline", label: t("tabs.timeline") },
    { id: "change", label: t("tabs.change") },
    { id: "relocate", label: t("tabs.relocate") },
    { id: "hold", label: t("tabs.hold") },
  ];

  const timelineItems: TimelineItem[] = useMemo(() => {
    return timeline.map((ev, idx) => {
      const meta = ev.meta || ev.data || {};
      const title =
        ev.title ||
        (ev.type === "status_transition"
          ? `${String(meta.from_commercial ?? meta.from_status ?? "—")} → ${String(meta.to_commercial ?? meta.to_status ?? "—")}`
          : t(`timelineTypes.${ev.type}` as "timelineTypes.activation"));
      const when = ev.occurred_at || ev.at;
      return {
        id: ev.id || `${ev.type}-${idx}`,
        title,
        description: ev.summary || String(meta.reason || meta.notes || meta.comment || "") || undefined,
        meta: when ? formatDateTime(String(when), locale) : undefined,
      };
    });
  }, [locale, t, timeline]);

  async function onAction(actionId: string, reason?: string) {
    if (!service) return;
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      if (actionId === "activate") {
        await activateService(service.id, {
          idempotency_key: `ui-${service.id}-${Date.now()}`,
          reason,
        });
        setSuccess(t("activateSuccess"));
      } else if (actionId === "suspend") {
        await suspendService(service.id, { reason: reason || "suspended" });
        setSuccess(t("suspendSuccess"));
        onTabChange("overview");
      } else if (actionId === "reactivate") {
        await reactivateService(service.id, { reason });
        setSuccess(t("reactivateSuccess"));
      } else if (actionId === "cancel") {
        const res = await cancelService(service.id, {
          reason: reason || "cancelled",
          complete: false,
          equipment_return_required: true,
        });
        const data = res.data as { id?: number };
        if (data?.id) setCancelId(Number(data.id));
        setSuccess(t("cancelWizardHint"));
        onTabChange("cancel");
      }
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onChangePackage(e: React.FormEvent) {
    e.preventDefault();
    if (!service || !changePackageId) return;
    setBusy(true);
    setError(null);
    try {
      await createChangeRequest(service.id, {
        type: "package_change",
        requested_values: { package_id: Number(changePackageId) },
        reason: changeReason || undefined,
        apply: false,
      });
      setChangeStep(2);
      setSuccess(t("changeWizardHint"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onRelocate(e: React.FormEvent) {
    e.preventDefault();
    if (!service || !relocateLocationId) return;
    setBusy(true);
    setError(null);
    try {
      await relocateService(service.id, {
        new_location_id: Number(relocateLocationId),
        complete: false,
      });
      setRelocateStep(2);
      setSuccess(t("relocateWizardHint"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onHold(e: React.FormEvent) {
    e.preventDefault();
    if (!service || !holdReason.trim()) return;
    setBusy(true);
    setError(null);
    try {
      await placeFinanceHold(service.id, { reason: holdReason.trim() });
      setSuccess(t("holdSuccess"));
      setHoldReason("");
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }


  async function onConfirmOnline(e: React.FormEvent) {
    e.preventDefault();
    if (!service || !nocReason.trim()) return;
    setBusy(true);
    setError(null);
    try {
      await confirmServiceOnline(service.id, { reason: nocReason.trim() });
      setSuccess(t("actions.confirmOnline"));
      setNocReason("");
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (error && !service) return <ErrorWorkspace message={error} onRetry={() => void load()} />;
  if (!service) return <EmptyWorkspace label={t("serviceNotFound")} />;

  return (
    <div className="space-y-4" data-testid="service-detail">
      <WorkspaceHeader
        title={service.service_number}
        subtitle={service.customer?.contact_name || t("detailSubtitle")}
        badges={[
          { label: t(`commercial.${service.commercial_status}` as "commercial.active"), tone: "info" },
          { label: t(`operational.${service.operational_status}` as "operational.online"), tone: "neutral" },
        ]}
        actions={
          <Link href="/services">
            <Button variant="secondary">{tCommon("back")}</Button>
          </Link>
        }
        meta={
          <>
            <StatusBadge status={service.commercial_status} />
            <StatusBadge status={service.operational_status} />
            <StatusBadge status={service.billing_status} />
          </>
        }
      />

      {error ? <Alert tone="danger">{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}

      <Panel className="p-4" data-testid="service-actions">
        <h2 className="mb-3 text-sm font-semibold">{t("lifecycleActions")}</h2>
        <StatusActionMenu
          transitions={transitions}
          onTransition={onAction}
          loading={busy}
          large
        />
      </Panel>

      <ResponsiveTabs tabs={tabs} value={tab} onChange={onTabChange} />

      {tab === "overview" ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <Panel className="space-y-4 p-4" data-testid="activation-checklist">
            <h3 className="text-sm font-semibold">{t("activationChecklistTitle")}</h3>
            {checklist ? (
              <ul className="space-y-2 text-sm">
                {ACTIVATION_CHECKLIST.map((key) => (
                  <li key={key} className="flex items-center justify-between gap-2">
                    <span>{t(`checklist.${key}` as "checklist.zoho_linked")}</span>
                    <StatusBadge status={checklist.checklist[key] ? "ok" : "missing"} />
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-muted">{t("checklistIncomplete")}</p>
            )}
            {canNocConfirm && service.commercial_status === "active" && service.operational_status === "ready" ? (
              <form className="mt-3 grid gap-2" onSubmit={onConfirmOnline}>
                <Alert>{t("nocConfirmHint")}</Alert>
                <Input
                  data-testid="noc-online-reason"
                  value={nocReason}
                  onChange={(e) => setNocReason(e.target.value)}
                  placeholder={t("fields.reason")}
                  required
                />
                <Button type="submit" disabled={busy || !nocReason.trim()}>
                  {t("actions.confirmOnline")}
                </Button>
              </form>
            ) : null}
          </Panel>
          <Panel className="space-y-4 p-4">
            <RecordSummary
              items={[
                {
                  label: t("fields.customer"),
                  value: service.customer?.contact_name || String(service.customer_id),
                },
                {
                  label: t("fields.package"),
                  value: service.package?.name || service.package?.code || "—",
                },
                {
                  label: t("fields.location"),
                  value: service.location?.name || "—",
                },
                { label: t("fields.mrr"), value: String(service.mrr ?? "—") },
                { label: t("fields.serviceType"), value: service.service_type_code || "—" },
                { label: t("fields.technology"), value: service.access_technology_code || "—" },
                { label: t("fields.circuitId"), value: service.circuit_id || "—" },
                { label: t("fields.staticIp"), value: service.static_ip || "—" },
              ]}
            />
          </Panel>
          <Panel className="p-4">
            <h3 className="mb-2 text-sm font-semibold">{t("tabs.timeline")}</h3>
            {timelineItems.length ? (
              <Timeline items={timelineItems.slice(0, 5)} />
            ) : (
              <EmptyWorkspace label={t("emptyTimeline")} />
            )}
          </Panel>
        </div>
      ) : null}

      {tab === "billing" ? (
        <Panel className="space-y-3 p-4" data-testid="service-billing">
          <Alert>{t("billingReadOnly")}</Alert>
          {billing ? (
            <RecordSummary
              items={[
                { label: t("fields.billingStatus"), value: billing.billing_status },
                { label: t("fields.mrr"), value: String(billing.mrr ?? "—") },
                { label: t("fields.sourceOfTruth"), value: billing.source_of_truth },
                {
                  label: t("fields.invoices"),
                  value: String(billing.invoices?.length ?? 0),
                },
                {
                  label: t("fields.payments"),
                  value: String(billing.payments?.length ?? 0),
                },
              ]}
            />
          ) : (
            <EmptyWorkspace label={t("emptyBilling")} />
          )}
        </Panel>
      ) : null}

      {tab === "equipment" ? (
        <Panel className="p-4" data-testid="service-equipment">
          {equipment.length === 0 ? (
            <EmptyWorkspace label={t("emptyEquipment")} />
          ) : (
            <ul className="divide-y divide-border">
              {equipment.map((eq) => (
                <li key={eq.id} className="flex items-center justify-between gap-2 py-3 text-sm">
                  <span>
                    {eq.product?.name_en || eq.serial_number || `#${eq.id}`}
                  </span>
                  <StatusBadge status={eq.status || "unknown"} />
                </li>
              ))}
            </ul>
          )}
        </Panel>
      ) : null}

      {tab === "timeline" ? (
        <Panel className="p-4" data-testid="service-timeline">
          {timelineItems.length ? <Timeline items={timelineItems} /> : <EmptyWorkspace label={t("emptyTimeline")} />}
        </Panel>
      ) : null}

      {tab === "change" ? (
        <Panel className="p-4" data-testid="service-change">
          {!canChange ? (
            <Alert tone="danger">{t("permissionDenied")}</Alert>
          ) : (
            <Alert>{t("changeWizardHint")} · step {changeStep}</Alert>
            <form className="grid max-w-lg gap-3" onSubmit={onChangePackage}>
              <label className="block space-y-1">
                <span className="text-sm font-medium">{t("fields.package")}</span>
                <Select
                  value={changePackageId}
                  onChange={(e) => setChangePackageId(e.target.value)}
                  data-testid="change-package"
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
                <span className="text-sm font-medium">{t("fields.reason")}</span>
                <Input value={changeReason} onChange={(e) => setChangeReason(e.target.value)} />
              </label>
              <Button type="submit" disabled={busy || !changePackageId}>
                {t("actions.submitChange")}
              </Button>
            </form>
          )}
        </Panel>
      ) : null}

      {tab === "relocate" ? (
        <Panel className="p-4" data-testid="service-relocate">
          {!canRelocate ? (
            <Alert tone="danger">{t("permissionDenied")}</Alert>
          ) : (
            <Alert>{t("relocateWizardHint")} · step {relocateStep}</Alert>
            <form className="grid max-w-lg gap-3" onSubmit={onRelocate}>
              <label className="block space-y-1">
                <span className="text-sm font-medium">{t("fields.location")}</span>
                <Select
                  value={relocateLocationId}
                  onChange={(e) => setRelocateLocationId(e.target.value)}
                  data-testid="relocate-location"
                >
                  <option value="">{t("fields.selectLocation")}</option>
                  {locations.map((loc) => (
                    <option key={loc.id} value={loc.id}>
                      {loc.name}
                    </option>
                  ))}
                </Select>
              </label>
              <Button type="submit" disabled={busy || !relocateLocationId}>
                {t("actions.submitRelocate")}
              </Button>
            </form>
          )}
        </Panel>
      ) : null}

      {tab === "hold" ? (
        <Panel className="p-4" data-testid="service-hold">
          {!canHold ? (
            <Alert tone="danger">{t("permissionDenied")}</Alert>
          ) : (
            <form className="grid max-w-lg gap-3" onSubmit={onHold}>
              <label className="block space-y-1">
                <span className="text-sm font-medium">{t("fields.reason")}</span>
                <TextArea
                  data-testid="hold-reason"
                  value={holdReason}
                  onChange={(e) => setHoldReason(e.target.value)}
                  rows={3}
                  required
                />
              </label>
              <Button type="submit" disabled={busy || !holdReason.trim()}>
                {t("actions.placeHold")}
              </Button>
            </form>
          )}
        </Panel>
      ) : null}

      {tab === "suspend" ? (
        <Panel className="p-4">
          <p className="mb-3 text-sm text-muted">{t("suspendHint")}</p>
          {canSuspend ? (
            <StatusActionMenu
              transitions={[
                {
                  id: "suspend",
                  label: t("actions.suspend"),
                  requiresReason: true,
                  danger: true,
                },
              ]}
              onTransition={onAction}
              loading={busy}
              large
            />
          ) : (
            <Alert tone="danger">{t("permissionDenied")}</Alert>
          )}
        </Panel>
      ) : null}

      {tab === "cancel" ? (
        <Panel className="space-y-3 p-4">
          <Alert>{t("cancelWizardHint")}</Alert>
          {canCancel ? (
            <>
              <StatusActionMenu
                transitions={[
                  {
                    id: "cancel",
                    label: t("actions.requestCancel"),
                    requiresReason: true,
                    danger: true,
                  },
                ]}
                onTransition={onAction}
                loading={busy}
                large
              />
              {cancelId ? (
                <p className="text-sm text-muted">Cancellation #{cancelId} requested — continue steps from queue or API.</p>
              ) : null}
            </>
          ) : (
            <Alert tone="danger">{t("permissionDenied")}</Alert>
          )}
        </Panel>
      ) : null}
    </div>
  );
}
