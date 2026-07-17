"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { getVersion, type SystemVersionInfo } from "@/lib/operations";
import {
  ErrorWorkspace,
  RecordSummary,
  WorkspaceHeader,
} from "@/components/ops";
import { LoadingState, Panel } from "@/components/ui/layout";

export default function SystemVersionPage() {
  const t = useTranslations("systemVersion");
  const tCommon = useTranslations("common");
  const [data, setData] = useState<SystemVersionInfo | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getVersion();
      setData(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-4">
      <WorkspaceHeader title={t("title")} subtitle={t("subtitle")} />
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {!loading && data ? (
        <Panel className="p-4">
          <RecordSummary
            items={[
              { label: t("appName"), value: data.app_name || "—" },
              { label: t("backendVersion"), value: data.backend_version || "—" },
              { label: t("frontendVersion"), value: data.frontend_version || "—" },
              { label: t("commitSha"), value: data.commit_sha || "—" },
              { label: t("buildTimestamp"), value: data.build_timestamp || "—" },
              { label: t("migrationBatch"), value: data.migration_batch ?? "—" },
              { label: t("latestMigration"), value: data.latest_migration || "—" },
              { label: t("phpVersion"), value: data.php_version || "—" },
              { label: t("laravelVersion"), value: data.laravel_version || "—" },
            ]}
          />
        </Panel>
      ) : null}
    </div>
  );
}
