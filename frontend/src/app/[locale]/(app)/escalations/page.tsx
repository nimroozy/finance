"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listVisitOutcomes, listVisits } from "@/lib/visits";
import type { CollectionVisit, VisitOutcome } from "@/lib/types";
import { formatDateTime } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

const ESCALATION_OUTCOMES = ["refused", "follow_up"];

export default function EscalationsPage() {
  const t = useTranslations("escalations");
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
      const results = await Promise.all(
        ESCALATION_OUTCOMES.map((outcome) =>
          listVisits({ outcome, per_page: 25 }),
        ),
      );
      const merged = results.flatMap((r) => r.data);
      merged.sort((a, b) =>
        String(b.visited_at ?? "").localeCompare(String(a.visited_at ?? "")),
      );
      setRows(merged);
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
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          <Link href="/reports/collection">
            <Button variant="secondary">{t("reportsLink")}</Button>
          </Link>
        }
      />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : rows.length === 0 ? (
          <EmptyState label={t("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[700px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("customer")}</th>
                  <th className="px-4 py-3 font-medium">{t("outcome")}</th>
                  <th className="px-4 py-3 font-medium">{t("visitedAt")}</th>
                  <th className="px-4 py-3 font-medium">{t("notes")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr
                    key={`${row.outcome}-${row.id}`}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-4 py-3">
                      <Link
                        href={`/visits/${row.id}`}
                        className="font-medium text-primary hover:underline"
                      >
                        {row.customer?.contact_name || `#${row.customer_id}`}
                      </Link>
                    </td>
                    <td className="px-4 py-3">{outcomeLabel(row.outcome)}</td>
                    <td className="px-4 py-3">
                      {formatDateTime(row.visited_at, locale)}
                    </td>
                    <td className="px-4 py-3 max-w-xs truncate">
                      {row.notes || "—"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}
