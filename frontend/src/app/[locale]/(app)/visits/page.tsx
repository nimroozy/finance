"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listVisitOutcomes, listVisits } from "@/lib/visits";
import type { CollectionVisit, VisitOutcome } from "@/lib/types";
import { formatDateTime } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function VisitsPage() {
  const t = useTranslations("visitsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [rows, setRows] = useState<CollectionVisit[]>([]);
  const [outcomes, setOutcomes] = useState<VisitOutcome[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [outcome, setOutcome] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listVisits({
          page,
          outcome: outcome || undefined,
        });
        setRows(res.data);
        setMeta({
          current_page: res.meta?.current_page ?? 1,
          last_page: res.meta?.last_page ?? 1,
        });
      } catch (err) {
        setError(err instanceof ApiError ? err.message : tCommon("error"));
      } finally {
        setLoading(false);
      }
    },
    [outcome, tCommon],
  );

  useEffect(() => {
    void load(1);
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
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      <Panel className="mb-4 p-4">
        <Select
          value={outcome}
          onChange={(e) => setOutcome(e.target.value)}
          className="sm:max-w-xs"
        >
          <option value="">{tCommon("all")}</option>
          {outcomes.map((o) => (
            <option key={o.code} value={o.code}>
              {locale === "fa" ? o.label_fa : o.label_en}
            </option>
          ))}
        </Select>
      </Panel>

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : rows.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[800px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("customer")}</th>
                  <th className="px-4 py-3 font-medium">{t("collector")}</th>
                  <th className="px-4 py-3 font-medium">{t("outcome")}</th>
                  <th className="px-4 py-3 font-medium">{t("visitedAt")}</th>
                  <th className="px-4 py-3 font-medium">{t("gps")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr
                    key={row.id}
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
                    <td className="px-4 py-3">
                      {row.collector?.user?.name || "—"}
                    </td>
                    <td className="px-4 py-3">{outcomeLabel(row.outcome)}</td>
                    <td className="px-4 py-3">
                      {formatDateTime(row.visited_at, locale)}
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge status={row.gps_risk_level} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {meta.last_page > 1 ? (
          <div className="flex items-center justify-between gap-3 border-t border-border px-4 py-3">
            <p className="text-sm text-muted">
              {tCommon("page", {
                page: meta.current_page,
                total: meta.last_page,
              })}
            </p>
            <div className="flex gap-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page <= 1 || loading}
                onClick={() => void load(meta.current_page - 1)}
              >
                {tCommon("previous")}
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page >= meta.last_page || loading}
                onClick={() => void load(meta.current_page + 1)}
              >
                {tCommon("next")}
              </Button>
            </div>
          </div>
        ) : null}
      </Panel>
    </div>
  );
}
