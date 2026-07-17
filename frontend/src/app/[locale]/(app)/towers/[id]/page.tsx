"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { getTower, type Tower } from "@/lib/inventory";
import { ErrorWorkspace, RecordSummary, WorkspaceHeader } from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { LoadingState, Panel } from "@/components/ui/layout";

export default function TowerDetailPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const params = useParams();
  const id = String(params.id);
  const [tower, setTower] = useState<Tower | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getTower(id);
      setTower(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setTower(null);
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-4" data-testid="tower-detail">
      <WorkspaceHeader
        title={tower?.name || t("towersTitle")}
        subtitle={tower?.code}
        actions={
          tower?.site_id ? (
            <Link href={`/sites/${tower.site_id}`}>
              <Button variant="secondary">{t("backToSite")}</Button>
            </Link>
          ) : (
            <Link href="/sites">
              <Button variant="secondary">{t("backToSites")}</Button>
            </Link>
          )
        }
      />
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {tower ? (
        <Panel className="p-4">
          <div className="mb-3">
            <StatusBadge status={tower.status || "active"} />
          </div>
          <RecordSummary
            items={[
              { label: t("fields.structureType"), value: tower.structure_type || "—" },
              { label: t("fields.height"), value: tower.height_m != null ? String(tower.height_m) : "—" },
              {
                label: t("fields.gps"),
                value:
                  tower.gps_lat != null && tower.gps_lng != null
                    ? `${tower.gps_lat}, ${tower.gps_lng}`
                    : "—",
              },
              { label: t("fields.notes"), value: tower.notes || "—" },
            ]}
          />
        </Panel>
      ) : null}
    </div>
  );
}
