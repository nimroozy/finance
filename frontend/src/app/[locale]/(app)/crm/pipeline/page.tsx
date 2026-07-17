"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { PIPELINE_STAGES, listLeads, type Lead } from "@/lib/crm";
import {
  EmptyWorkspace,
  ErrorWorkspace,
  WorkspaceHeader,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { LoadingState, Panel } from "@/components/ui/layout";
import { useAuthStore } from "@/store/auth-store";

const BOARD_STAGES = [
  "lead",
  "contacted",
  "qualified",
  "coverage_check",
  "site_survey",
  "quotation",
  "negotiation",
  "approved",
  "finance_review",
  "installation_request",
] as const;

export default function CrmPipelinePage() {
  const t = useTranslations("crm");
  const tCommon = useTranslations("common");
  const canCreate = useAuthStore((s) => s.hasPermission("crm.leads.create"));
  const [rows, setRows] = useState<Lead[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listLeads({ page: 1, per_page: 100 });
      setRows(res.data);
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

  const columns = useMemo(() => {
    const map: Record<string, Lead[]> = {};
    for (const stage of BOARD_STAGES) map[stage] = [];
    for (const lead of rows) {
      if (BOARD_STAGES.includes(lead.pipeline_stage as (typeof BOARD_STAGES)[number])) {
        map[lead.pipeline_stage].push(lead);
      }
    }
    return map;
  }, [rows]);

  return (
    <div className="space-y-4">
      <WorkspaceHeader
        title={t("pipelineTitle")}
        subtitle={t("pipelineSubtitle")}
        actions={
          <>
            <Link href="/crm/leads">
              <Button variant="secondary">{t("leadsTitle")}</Button>
            </Link>
            {canCreate ? (
              <Link href="/crm/leads/new">
                <Button>{t("newLead")}</Button>
              </Link>
            ) : null}
          </>
        }
      />
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && rows.length === 0 ? (
        <EmptyWorkspace label={t("emptyLeads")} />
      ) : null}
      {!loading && !error ? (
        <div className="flex gap-3 overflow-x-auto pb-2">
          {BOARD_STAGES.map((stage) => (
            <Panel key={stage} className="min-w-[240px] max-w-[280px] shrink-0 p-3">
              <div className="mb-3 flex items-center justify-between gap-2">
                <h2 className="text-sm font-semibold">{t(`stages.${stage}` as "stages.lead")}</h2>
                <span className="text-xs text-muted">{columns[stage]?.length ?? 0}</span>
              </div>
              <div className="space-y-2">
                {(columns[stage] ?? []).map((lead) => (
                  <Link
                    key={lead.id}
                    href={`/crm/leads/${lead.id}`}
                    className="block rounded-md border border-border bg-surface p-3 hover:border-border-strong"
                  >
                    <div className="font-medium text-sm">{lead.lead_number}</div>
                    <div className="text-xs text-muted">
                      {lead.contact_person || lead.company || "—"}
                    </div>
                    <div className="mt-2 flex items-center justify-between gap-2">
                      <StatusBadge status={lead.pipeline_stage} />
                      <span className="text-xs text-muted">
                        {String(lead.opportunity_value ?? lead.lead_value ?? "")}
                      </span>
                    </div>
                  </Link>
                ))}
              </div>
            </Panel>
          ))}
        </div>
      ) : null}
      <p className="text-xs text-muted">{t("pipelineHint", { count: PIPELINE_STAGES.length })}</p>
    </div>
  );
}
