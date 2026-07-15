"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listVisitOutcomes, listVisits } from "@/lib/visits";
import type { CollectionVisit, VisitOutcome } from "@/lib/types";
import { formatDateTime } from "@/lib/utils";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CollectorVisitsPage() {
  const t = useTranslations("visitsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [rows, setRows] = useState<CollectionVisit[]>([]);
  const [outcomes, setOutcomes] = useState<VisitOutcome[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listVisits({ per_page: 30 });
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    void listVisitOutcomes()
      .then((res) => setOutcomes(res.data))
      .catch(() => setOutcomes([]));
  }, []);

  function outcomeLabel(code: string) {
    const found = outcomes.find((o) => o.code === code);
    if (!found) return code;
    return locale === "fa" ? found.label_fa : found.label_en;
  }

  return (
    <div className="space-y-4">
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          <Link href="/collector/visits/new">
            <button
              type="button"
              className="inline-flex h-10 items-center rounded-md bg-primary px-4 text-sm font-medium text-white"
            >
              {t("createTitle")}
            </button>
          </Link>
        }
      />

      {error ? <Alert>{error}</Alert> : null}

      {loading ? (
        <LoadingState label={tCommon("loading")} />
      ) : rows.length === 0 ? (
        <EmptyState label={tCommon("empty")} />
      ) : (
        <div className="space-y-3">
          {rows.map((row) => (
            <Panel key={row.id} className="p-4">
              <Link href={`/visits/${row.id}`} className="block">
                <p className="text-base font-semibold text-primary">
                  {row.customer?.contact_name || `#${row.customer_id}`}
                </p>
                <p className="mt-1 text-sm">{outcomeLabel(row.outcome)}</p>
                <p className="text-xs text-muted">
                  {formatDateTime(row.visited_at, locale)}
                </p>
              </Link>
            </Panel>
          ))}
        </div>
      )}
    </div>
  );
}
