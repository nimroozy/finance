"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { getFixedAsset, type FixedAsset } from "@/lib/inventory";
import { ErrorWorkspace, RecordSummary, WorkspaceHeader } from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { LoadingState, Panel } from "@/components/ui/layout";

export default function AssetDetailPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const params = useParams();
  const id = String(params.id);
  const [asset, setAsset] = useState<FixedAsset | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getFixedAsset(id);
      setAsset(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setAsset(null);
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-4" data-testid="asset-detail">
      <WorkspaceHeader
        title={asset?.name || t("assetsTitle")}
        subtitle={asset?.asset_number}
        actions={
          <Link href="/assets">
            <Button variant="secondary">{t("backToAssets")}</Button>
          </Link>
        }
      />
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {asset ? (
        <Panel className="p-4">
          <div className="mb-3">
            <StatusBadge status={asset.status || "active"} />
          </div>
          <RecordSummary
            items={[
              { label: t("columns.assetNumber"), value: asset.asset_number },
              { label: t("fields.category"), value: asset.category || "—" },
              { label: t("fields.condition"), value: asset.condition || "—" },
              { label: t("fields.acquisitionCost"), value: String(asset.acquisition_cost ?? "—") },
              { label: t("fields.warrantyEnd"), value: asset.warranty_end || "—" },
              { label: t("fields.notes"), value: asset.notes || "—" },
            ]}
          />
        </Panel>
      ) : null}
    </div>
  );
}
