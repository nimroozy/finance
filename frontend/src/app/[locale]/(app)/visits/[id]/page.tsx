"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { downloadVisitFile } from "@/lib/evidence";
import {
  addVisitCorrectionNote,
  getVisit,
  listVisitOutcomes,
} from "@/lib/visits";
import type { CollectionVisit, VisitOutcome } from "@/lib/types";
import { formatDateTime, formatMoney } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { LeafletMap } from "@/components/maps/map";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Label, TextArea } from "@/components/ui/form";
import { Alert, LoadingState, PageHeader, Panel } from "@/components/ui/layout";
import { ErrorState } from "@/components/ui/error-state";

export default function VisitDetailPage() {
  const t = useTranslations("visitsPage");
  const tCommon = useTranslations("common");
  const tMaps = useTranslations("maps");
  const locale = useLocale();
  const params = useParams();
  const id = Number(params.id);
  const canManage = useAuthStore((s) => s.hasPermission("visits.manage"));

  const [visit, setVisit] = useState<CollectionVisit | null>(null);
  const [outcomes, setOutcomes] = useState<VisitOutcome[]>([]);
  const [correction, setCorrection] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<unknown>(null);
  const [saveError, setSaveError] = useState<unknown>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await getVisit(id);
      setVisit(res.data);
    } catch (err) {
      setError(err);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    void listVisitOutcomes()
      .then((res) => setOutcomes(res.data))
      .catch(() => setOutcomes([]));
  }, []);

  const markers = useMemo(() => {
    if (!visit) return [];
    const lat = Number(visit.latitude);
    const lng = Number(visit.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return [];
    return [
      {
        id: visit.id,
        lat,
        lng,
        label: visit.customer?.contact_name || `Visit #${visit.id}`,
      },
    ];
  }, [visit]);

  function outcomeLabel(code: string) {
    const found = outcomes.find((o) => o.code === code);
    if (!found) return code;
    return locale === "fa" ? found.label_fa : found.label_en;
  }

  async function onCorrection(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setSaveError(null);
    try {
      await addVisitCorrectionNote(id, correction.trim());
      setSuccess(t("correctionSuccess"));
      setCorrection("");
      await load();
    } catch (err) {
      setSaveError(err);
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (error || !visit) {
    return <ErrorState error={error} onRetry={() => void load()} />;
  }

  return (
    <div>
      <PageHeader
        title={t("detailTitle")}
        subtitle={visit.customer?.contact_name || `#${visit.customer_id}`}
        actions={
          <Link href="/visits">
            <Button variant="secondary">{tCommon("back")}</Button>
          </Link>
        }
      />

      {saveError ? (
        <div className="mb-4">
          <ErrorState error={saveError} />
        </div>
      ) : null}
      {success ? (
        <div className="mb-4">
          <Alert tone="success">{success}</Alert>
        </div>
      ) : null}

      <Panel className="mb-4 grid gap-3 p-4 sm:grid-cols-2">
        <div>
          <p className="text-xs text-muted">{t("outcome")}</p>
          <p className="font-medium">{outcomeLabel(visit.outcome)}</p>
        </div>
        <div>
          <p className="text-xs text-muted">{t("visitedAt")}</p>
          <p>{formatDateTime(visit.visited_at, locale)}</p>
        </div>
        <div>
          <p className="text-xs text-muted">{t("collector")}</p>
          <p>{visit.collector?.user?.name || "—"}</p>
        </div>
        <div>
          <p className="text-xs text-muted">{t("gps")}</p>
          <StatusBadge status={visit.gps_risk_level} />
          {visit.distance_meters != null ? (
            <p className="text-xs text-muted">
              {t("distance")}: {visit.distance_meters}
            </p>
          ) : null}
        </div>
        <div className="sm:col-span-2">
          <p className="text-xs text-muted">{t("notes")}</p>
          <p className="whitespace-pre-wrap">{visit.notes || "—"}</p>
        </div>
        {visit.correction_note ? (
          <div className="sm:col-span-2">
            <p className="text-xs text-muted">{t("correction")}</p>
            <p className="whitespace-pre-wrap">{visit.correction_note}</p>
          </div>
        ) : null}
        {visit.promise ? (
          <div className="sm:col-span-2">
            <p className="text-xs text-muted">Promise</p>
            <p>
              {formatMoney(
                visit.promise.amount,
                visit.promise.currency,
                locale,
              )}{" "}
              · {visit.promise.promised_date}
            </p>
          </div>
        ) : null}
      </Panel>

      <div className="mb-4">
        <LeafletMap
          markers={markers}
          missingLabel={tMaps("missingLocation")}
          openInMapsLabel={tMaps("openInMaps")}
        />
      </div>

      {visit.files?.length ? (
        <Panel className="mb-4">
          <div className="border-b border-border px-4 py-3 text-sm font-medium">
            {t("files")}
          </div>
          <ul className="divide-y divide-border">
            {visit.files.map((f) => (
              <li
                key={f.id}
                className="flex items-center justify-between gap-3 px-4 py-3 text-sm"
              >
                <span>{f.original_name}</span>
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={() =>
                    void downloadVisitFile(f.id, f.original_name)
                  }
                >
                  Download
                </Button>
              </li>
            ))}
          </ul>
        </Panel>
      ) : null}

      {canManage ? (
        <Panel className="p-4">
          <form onSubmit={onCorrection} className="space-y-3">
            <Label>{t("addCorrection")}</Label>
            <TextArea
              value={correction}
              onChange={(e) => setCorrection(e.target.value)}
              required
            />
            <Button type="submit" disabled={saving}>
              {tCommon("save")}
            </Button>
          </form>
        </Panel>
      ) : null}
    </div>
  );
}
