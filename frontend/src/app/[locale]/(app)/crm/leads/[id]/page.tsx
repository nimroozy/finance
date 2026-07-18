"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  ACTIVITY_TYPES,
  assignLead,
  convertLead,
  createActivity,
  createFollowUp,
  createQuotation,
  getLead,
  linkLeadZohoCustomer,
  searchZohoMirrorCustomers,
  transitionLead,
  type Lead,
  type ZohoMirrorCustomer,
} from "@/lib/crm";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  RecordSummary,
  ResponsiveTabs,
  StatusActionMenu,
  Timeline,
  UserPicker,
  WorkspaceHeader,
  type ResponsiveTab,
  type StatusTransition,
  type TimelineItem,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Input, Select, TextArea } from "@/components/ui/form";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

export default function CrmLeadDetailPage() {
  const t = useTranslations("crm");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const params = useParams();
  const id = String(params.id || "");

  const canUpdate = useAuthStore((s) => s.hasPermission("crm.leads.update"));
  const canAssign = useAuthStore((s) => s.hasPermission("crm.leads.assign"));
  const canConvert = useAuthStore((s) => s.hasPermission("crm.leads.convert"));
  const canActivity = useAuthStore((s) => s.hasPermission("crm.activities.manage"));
  const canFollowUp = useAuthStore((s) => s.hasPermission("crm.follow_ups.manage"));
  const canQuote = useAuthStore((s) => s.hasPermission("crm.quotations.create"));

  const [lead, setLead] = useState<Lead | null>(null);
  const [tab, setTab] = useState("overview");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [ownerId, setOwnerId] = useState("");
  const [ownerLabel, setOwnerLabel] = useState<string | null>(null);
  const [activityType, setActivityType] = useState("call");
  const [activitySubject, setActivitySubject] = useState("");
  const [activityBody, setActivityBody] = useState("");
  const [followUpDue, setFollowUpDue] = useState("");
  const [followUpNotes, setFollowUpNotes] = useState("");
  const [quoteMonthly, setQuoteMonthly] = useState("");
  const [quoteInstall, setQuoteInstall] = useState("");
  const [winReason, setWinReason] = useState("");
  const [zohoQuery, setZohoQuery] = useState("");
  const [zohoResults, setZohoResults] = useState<ZohoMirrorCustomer[]>([]);
  const [zohoSearching, setZohoSearching] = useState(false);

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await getLead(id);
      setLead(res.data);
      setOwnerId(res.data.owner_id ? String(res.data.owner_id) : "");
      setOwnerLabel(res.data.owner?.name ?? null);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setLead(null);
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  const transitions: StatusTransition[] = useMemo(() => {
    if (!lead?.allowed_transitions) return [];
    return lead.allowed_transitions.map((stage) => ({
      id: stage,
      label: t(`stages.${stage}` as "stages.lead"),
      danger: stage === "lost" || stage === "cancelled",
      requiresReason: stage === "lost" || stage === "cancelled",
    }));
  }, [lead, t]);

  const tabs: ResponsiveTab[] = [
    { id: "overview", label: t("tabs.overview") },
    { id: "activity", label: t("tabs.activity") },
    { id: "followups", label: t("tabs.followUps") },
    { id: "quotes", label: t("tabs.quotations") },
  ];

  const timelineItems: TimelineItem[] = useMemo(() => {
    if (!lead) return [];
    const items: TimelineItem[] = [];
    for (const tr of lead.stage_transitions ?? []) {
      items.push({
        id: `tr-${tr.id}`,
        title: `${tr.from_stage || "—"} → ${tr.to_stage}`,
        description: tr.reason || tr.notes || undefined,
        meta: tr.created_at ? formatDateTime(tr.created_at, locale) : undefined,
      });
    }
    for (const act of lead.activities ?? []) {
      items.push({
        id: `act-${act.id}`,
        title: act.subject || act.type,
        description: act.body || undefined,
        meta:
          act.performed_at || act.created_at
            ? formatDateTime(act.performed_at || act.created_at, locale)
            : undefined,
      });
    }
    return items;
  }, [lead, locale]);

  async function onTransition(stage: string, reason?: string) {
    if (!lead) return;
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      await transitionLead(lead.id, stage, reason);
      setSuccess(t("transitionSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onAssign() {
    if (!lead || !ownerId) return;
    setBusy(true);
    setError(null);
    try {
      await assignLead(lead.id, Number(ownerId));
      setSuccess(t("assignSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onConvert() {
    if (!lead) return;
    setBusy(true);
    setError(null);
    try {
      const res = await convertLead(lead.id, { win_reason: winReason || undefined });
      setSuccess(t("convertSuccess", { customerId: res.data.customer_id }));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onAddActivity(e: React.FormEvent) {
    e.preventDefault();
    if (!lead) return;
    setBusy(true);
    setError(null);
    try {
      await createActivity({
        lead_id: lead.id,
        type: activityType,
        subject: activitySubject || undefined,
        body: activityBody || undefined,
      });
      setActivitySubject("");
      setActivityBody("");
      setSuccess(t("activitySuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onAddFollowUp(e: React.FormEvent) {
    e.preventDefault();
    if (!lead || !followUpDue) return;
    setBusy(true);
    setError(null);
    try {
      await createFollowUp({
        lead_id: lead.id,
        due_at: followUpDue,
        notes: followUpNotes || undefined,
      });
      setFollowUpDue("");
      setFollowUpNotes("");
      setSuccess(t("followUpSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onAddQuote(e: React.FormEvent) {
    e.preventDefault();
    if (!lead) return;
    setBusy(true);
    setError(null);
    try {
      await createQuotation({
        lead_id: lead.id,
        monthly_package: quoteMonthly ? Number(quoteMonthly) : undefined,
        installation_fee: quoteInstall ? Number(quoteInstall) : undefined,
      });
      setQuoteMonthly("");
      setQuoteInstall("");
      setSuccess(t("quoteSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onSearchZoho(e?: React.FormEvent) {
    e?.preventDefault();
    setZohoSearching(true);
    setError(null);
    try {
      const res = await searchZohoMirrorCustomers({
        phone: zohoQuery.trim() || lead?.phone || undefined,
        q: zohoQuery.trim() || undefined,
        branch_id: lead?.branch_id,
        limit: 20,
      });
      setZohoResults(res.data || []);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setZohoResults([]);
    } finally {
      setZohoSearching(false);
    }
  }

  async function onLinkZoho(customerId: number) {
    if (!lead) return;
    setBusy(true);
    setError(null);
    try {
      await linkLeadZohoCustomer(lead.id, customerId);
      setSuccess(t("zohoLinkSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (error && !lead) return <ErrorWorkspace message={error} onRetry={() => void load()} />;
  if (!lead) return <EmptyWorkspace label={t("leadNotFound")} />;

  return (
    <div className="space-y-4">
      <WorkspaceHeader
        title={lead.lead_number}
        subtitle={lead.contact_person || lead.company || t("leadsTitle")}
        badges={[{ label: t(`stages.${lead.pipeline_stage}` as "stages.lead"), tone: "info" }]}
        actions={
          <Link href="/crm/leads">
            <Button variant="secondary">{tCommon("back")}</Button>
          </Link>
        }
        meta={
          <>
            <span>{lead.phone || "—"}</span>
            <span>{lead.email || "—"}</span>
            <StatusBadge status={lead.pipeline_stage} />
          </>
        }
      />

      {error ? <Alert tone="danger">{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}

      {canUpdate ? (
        <Panel className="p-4">
          <h2 className="mb-3 text-sm font-semibold">{t("pipelineActions")}</h2>
          <StatusActionMenu
            transitions={transitions}
            onTransition={onTransition}
            loading={busy}
            large
          />
        </Panel>
      ) : null}

      <ResponsiveTabs tabs={tabs} value={tab} onChange={setTab} />

      {tab === "overview" ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <Panel className="space-y-4 p-4">
            <RecordSummary
              items={[
                { label: t("fields.company"), value: lead.company || "—" },
                { label: t("fields.contactPerson"), value: lead.contact_person || "—" },
                { label: t("fields.phone"), value: lead.phone || "—" },
                { label: t("fields.source"), value: lead.source_code || "—" },
                { label: t("fields.package"), value: lead.interested_package || "—" },
                {
                  label: t("fields.owner"),
                  value: lead.owner?.name || "—",
                },
                {
                  label: t("columns.value"),
                  value: String(lead.opportunity_value ?? lead.lead_value ?? "—"),
                },
                {
                  label: t("fields.estimatedMrr"),
                  value: String(lead.estimated_mrr ?? "—"),
                },
              ]}
            />
            {canAssign ? (
              <div className="flex flex-wrap items-end gap-2 border-t border-border pt-4">
                <div className="min-w-[14rem] flex-1">
                  <UserPicker
                    label={t("fields.owner")}
                    value={ownerId || null}
                    selectedLabel={ownerLabel}
                    onChange={(opt) => {
                      setOwnerId(opt ? String(opt.id) : "");
                      setOwnerLabel(opt?.label ?? null);
                    }}
                  />
                </div>
                <Button disabled={busy || !ownerId} onClick={() => void onAssign()}>
                  {t("assign")}
                </Button>
              </div>
            ) : null}
          </Panel>

          <Panel className="space-y-3 p-4" data-testid="zoho-link-panel">
            <h3 className="text-sm font-semibold">{t("zohoLinkTitle")}</h3>
            <p className="text-xs text-muted">{t("zohoLinkHint")}</p>
            <div
              className="text-sm"
              data-testid="zoho-link-status"
            >
              {lead.converted_customer_id
                ? t("zohoLinkedStatus", { id: lead.converted_customer_id })
                : t("zohoNotLinked")}
            </div>
            {canUpdate && !lead.converted_customer_id ? (
              <form className="grid gap-2" onSubmit={(e) => void onSearchZoho(e)}>
                <Input
                  data-testid="zoho-search-input"
                  value={zohoQuery}
                  onChange={(e) => setZohoQuery(e.target.value)}
                  placeholder={t("zohoSearchPlaceholder")}
                />
                <Button type="submit" variant="secondary" disabled={zohoSearching || busy} data-testid="zoho-search-submit">
                  {t("zohoSearch")}
                </Button>
              </form>
            ) : null}
            {zohoResults.length > 0 ? (
              <ul className="divide-y divide-border" data-testid="zoho-search-results">
                {zohoResults.map((c) => (
                  <li key={c.id} className="flex items-center justify-between gap-2 py-2 text-sm">
                    <span>
                      {c.contact_name || c.customer_number || `#${c.id}`}
                      {c.zoho_contact_id ? ` · ${c.zoho_contact_id}` : ""}
                    </span>
                    {canUpdate ? (
                      <Button
                        size="sm"
                        data-testid="zoho-link-customer"
                        disabled={busy}
                        onClick={() => void onLinkZoho(c.id)}
                      >
                        {t("zohoLinkAction")}
                      </Button>
                    ) : null}
                  </li>
                ))}
              </ul>
            ) : null}
            {canConvert ? (
              <div className="grid gap-2 border-t border-border pt-3">
                <Input
                  value={winReason}
                  onChange={(e) => setWinReason(e.target.value)}
                  placeholder={t("fields.winReason")}
                />
                <Button disabled={busy || !lead.converted_customer_id} onClick={() => void onConvert()}>
                  {t("convert")}
                </Button>
              </div>
            ) : null}
          </Panel>
          <Panel className="p-4">
            <h2 className="mb-3 text-sm font-semibold">{t("timeline")}</h2>
            <Timeline items={timelineItems} />
          </Panel>
        </div>
      ) : null}

      {tab === "activity" ? (
        <Panel className="space-y-4 p-4">
          {canActivity ? (
            <form className="grid gap-3 sm:grid-cols-2" onSubmit={onAddActivity}>
              <Select value={activityType} onChange={(e) => setActivityType(e.target.value)}>
                {ACTIVITY_TYPES.map((type) => (
                  <option key={type} value={type}>
                    {t(`activityTypes.${type}` as "activityTypes.call")}
                  </option>
                ))}
              </Select>
              <Input
                placeholder={t("fields.subject")}
                value={activitySubject}
                onChange={(e) => setActivitySubject(e.target.value)}
              />
              <div className="sm:col-span-2">
                <TextArea
                  placeholder={t("fields.notes")}
                  value={activityBody}
                  onChange={(e) => setActivityBody(e.target.value)}
                  rows={3}
                />
              </div>
              <Button type="submit" disabled={busy}>
                {t("addActivity")}
              </Button>
            </form>
          ) : null}
          <ul className="space-y-2">
            {(lead.activities ?? []).map((act) => (
              <li key={act.id} className="rounded-md border border-border p-3 text-sm">
                <div className="font-medium">
                  {act.subject || t(`activityTypes.${act.type}` as "activityTypes.call")}
                </div>
                <div className="text-muted">{act.body}</div>
                <div className="mt-1 text-xs text-muted">
                  {act.performed_at || act.created_at
                    ? formatDateTime(act.performed_at || act.created_at, locale)
                    : ""}
                </div>
              </li>
            ))}
          </ul>
        </Panel>
      ) : null}

      {tab === "followups" ? (
        <Panel className="space-y-4 p-4">
          {canFollowUp ? (
            <form className="grid gap-3 sm:grid-cols-2" onSubmit={onAddFollowUp}>
              <Input
                type="datetime-local"
                value={followUpDue}
                onChange={(e) => setFollowUpDue(e.target.value)}
                required
              />
              <Input
                placeholder={t("fields.notes")}
                value={followUpNotes}
                onChange={(e) => setFollowUpNotes(e.target.value)}
              />
              <Button type="submit" disabled={busy}>
                {t("scheduleFollowUp")}
              </Button>
            </form>
          ) : null}
          <ul className="space-y-2">
            {(lead.follow_ups ?? []).map((fu) => (
              <li key={fu.id} className="rounded-md border border-border p-3 text-sm">
                <div className="flex items-center justify-between gap-2">
                  <span>{formatDateTime(fu.due_at, locale)}</span>
                  <StatusBadge status={fu.status} />
                </div>
                <div className="text-muted">{fu.notes || "—"}</div>
              </li>
            ))}
          </ul>
        </Panel>
      ) : null}

      {tab === "quotes" ? (
        <Panel className="space-y-4 p-4">
          {canQuote ? (
            <form className="grid gap-3 sm:grid-cols-3" onSubmit={onAddQuote}>
              <Input
                type="number"
                placeholder={t("fields.monthlyPackage")}
                value={quoteMonthly}
                onChange={(e) => setQuoteMonthly(e.target.value)}
              />
              <Input
                type="number"
                placeholder={t("fields.installationFee")}
                value={quoteInstall}
                onChange={(e) => setQuoteInstall(e.target.value)}
              />
              <Button type="submit" disabled={busy}>
                {t("createQuote")}
              </Button>
            </form>
          ) : null}
          <ul className="space-y-2">
            {(lead.quotations ?? []).map((q) => (
              <li key={q.id} className="rounded-md border border-border p-3 text-sm">
                <div className="flex items-center justify-between gap-2">
                  <span className="font-medium">{q.quote_number}</span>
                  <StatusBadge status={q.status} />
                </div>
                <div className="text-muted">
                  {t("fields.monthlyPackage")}: {String(q.monthly_package ?? "—")} ·{" "}
                  {t("fields.installationFee")}: {String(q.installation_fee ?? "—")}
                </div>
              </li>
            ))}
          </ul>
        </Panel>
      ) : null}
    </div>
  );
}
