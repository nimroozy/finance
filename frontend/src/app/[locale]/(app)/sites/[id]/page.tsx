"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { getSite, type Site } from "@/lib/inventory";
import {
  ErrorWorkspace,
  MobileRecordCard,
  RecordSummary,
  ResponsiveTabs,
  WorkspaceHeader,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { LoadingState, Panel } from "@/components/ui/layout";

export default function SiteDetailPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const params = useParams();
  const id = String(params.id);
  const [site, setSite] = useState<Site | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [tab, setTab] = useState("overview");

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getSite(id);
      setSite(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setSite(null);
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-4" data-testid="site-detail">
      <WorkspaceHeader
        title={site?.name || t("sitesTitle")}
        subtitle={site?.code}
        actions={
          <Link href="/sites">
            <Button variant="secondary">{t("backToSites")}</Button>
          </Link>
        }
      />
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {site ? (
        <>
          <ResponsiveTabs
            tabs={[
              { id: "overview", label: t("tabs.overview") },
              { id: "towers", label: t("tabs.towers"), badge: site.towers?.length },
            ]}
            value={tab}
            onChange={setTab}
          />
          {tab === "overview" ? (
            <Panel className="p-4">
              <div className="mb-3">
                <StatusBadge status={site.status || "active"} />
              </div>
              <RecordSummary
                items={[
                  { label: t("columns.code"), value: site.code },
                  { label: t("fields.address"), value: site.address || "—" },
                  { label: t("fields.siteType"), value: site.site_type || "—" },
                  { label: t("fields.powerSource"), value: site.power_source || "—" },
                  {
                    label: t("fields.gps"),
                    value:
                      site.gps_lat != null && site.gps_lng != null
                        ? `${site.gps_lat}, ${site.gps_lng}`
                        : "—",
                  },
                ]}
              />
            </Panel>
          ) : null}
          {tab === "towers" ? (
            <div className="space-y-3">
              {(site.towers || []).length === 0 ? (
                <Panel className="p-6 text-sm text-muted">{t("emptyTowers")}</Panel>
              ) : (
                (site.towers || []).map((tower) => (
                  <MobileRecordCard
                    key={tower.id}
                    title={tower.name}
                    subtitle={tower.code}
                    href={`/towers/${tower.id}`}
                    badges={<StatusBadge status={tower.status || "active"} />}
                  />
                ))
              )}
            </div>
          ) : null}
        </>
      ) : null}
    </div>
  );
}
