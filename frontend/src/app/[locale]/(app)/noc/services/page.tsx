"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { getServicesNocWorkspace, type ServiceNocWorkspace } from "@/lib/services";
import {
  BranchPicker,
  EmptyWorkspace,
  ErrorWorkspace,
  MobileRecordCard,
  WorkspaceHeader,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

export default function NocServicesPage() {
  const t = useTranslations("services");
  const tCommon = useTranslations("common");
  const [data, setData] = useState<ServiceNocWorkspace | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [branchId, setBranchId] = useState("");
  const [branchLabel, setBranchLabel] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getServicesNocWorkspace(branchId || undefined);
      setData(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [branchId, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-4" data-testid="noc-services">
      <WorkspaceHeader title={t("nocTitle")} subtitle={t("nocSubtitle")} />
      <div className="max-w-sm">
        <BranchPicker
          value={branchId || null}
          selectedLabel={branchLabel}
          onChange={(opt) => {
            setBranchId(opt ? String(opt.id) : "");
            setBranchLabel(opt?.label ?? null);
          }}
        />
      </div>
      <Alert tone="success">{t("radiusDeferred")}</Alert>
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {data ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <Panel className="space-y-3 p-4">
            <h2 className="text-sm font-semibold">{t("nocAttention")}</h2>
            {data.attention_queue.length === 0 ? (
              <EmptyWorkspace label={t("emptyNocAttention")} />
            ) : (
              data.attention_queue.map((svc) => (
                <MobileRecordCard
                  key={svc.id}
                  href={`/services/${svc.id}`}
                  title={svc.service_number}
                  subtitle={svc.customer?.contact_name || String(svc.customer_id)}
                  badges={<StatusBadge status={svc.operational_status} />}
                />
              ))
            )}
          </Panel>
          <Panel className="space-y-3 p-4">
            <h2 className="text-sm font-semibold">{t("nocPendingActivation")}</h2>
            {data.pending_activation.length === 0 ? (
              <EmptyWorkspace label={t("emptyPendingActivation")} />
            ) : (
              data.pending_activation.map((svc) => (
                <MobileRecordCard
                  key={svc.id}
                  href={`/services/${svc.id}`}
                  title={svc.service_number}
                  subtitle={svc.customer?.contact_name || String(svc.customer_id)}
                  badges={<StatusBadge status={svc.commercial_status} />}
                />
              ))
            )}
          </Panel>
          <p className="text-sm text-muted lg:col-span-2">
            {data.note || t("nocNote")}{" · "}
            <Link href="/services" className="text-primary hover:underline">
              {t("allServices")}
            </Link>
          </p>
        </div>
      ) : null}
    </div>
  );
}
