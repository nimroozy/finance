"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { mapsExternalUrl } from "@/lib/geolocation";
import {
  cancelRoute,
  completeRoute,
  getRoute,
  publishRoute,
  startRoute,
} from "@/lib/routes";
import type { CollectionRoute } from "@/lib/types";
import { formatDate } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { LeafletMap } from "@/components/maps/map";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Alert, EmptyState, LoadingState, PageHeader, Panel } from "@/components/ui/layout";
import { ErrorState } from "@/components/ui/error-state";

const RESOLVED_STOP_STATUSES = new Set(["completed", "skipped"]);

export default function RouteDetailPage() {
  const t = useTranslations("routesPage");
  const tCommon = useTranslations("common");
  const tMaps = useTranslations("maps");
  const locale = useLocale();
  const params = useParams();
  const id = Number(params.id);

  const canManage = useAuthStore((s) => s.hasPermission("routes.manage"));

  const [route, setRoute] = useState<CollectionRoute | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const [actionError, setActionError] = useState<unknown>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await getRoute(id);
      setRoute(res.data);
    } catch (err) {
      setError(err);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  const markers = useMemo(
    () =>
      (route?.stops ?? [])
        .map((s) => {
          const lat = Number(s.customer?.latitude);
          const lng = Number(s.customer?.longitude);
          if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
          return {
            id: s.id,
            lat,
            lng,
            label: s.customer?.contact_name || `#${s.customer_id}`,
          };
        })
        .filter(Boolean) as { id: number; lat: number; lng: number; label: string }[],
    [route],
  );

  async function run(
    action: () => Promise<unknown>,
    successKey:
      | "publishSuccess"
      | "startSuccess"
      | "completeSuccess"
      | "cancelSuccess",
  ) {
    setBusy(true);
    setActionError(null);
    try {
      await action();
      setSuccess(t(successKey));
      await load();
    } catch (err) {
      setActionError(err);
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (error || !route) {
    return <ErrorState error={error} onRetry={() => void load()} />;
  }

  const stops = route.stops ?? [];
  const completedCount = stops.filter((s) => RESOLVED_STOP_STATUSES.has(s.status)).length;
  const remainingCount = stops.filter((s) => !RESOLVED_STOP_STATUSES.has(s.status)).length;
  const currentStop = stops.find((s) => !RESOLVED_STOP_STATUSES.has(s.status)) ?? null;

  return (
    <div>
      <PageHeader
        title={route.name}
        subtitle={formatDate(route.route_date, locale)}
        actions={
          <>
            {canManage && route.status === "draft" ? (
              <Button
                disabled={busy}
                onClick={() => void run(() => publishRoute(id), "publishSuccess")}
              >
                {t("publish")}
              </Button>
            ) : null}
            {route.status === "published" ? (
              <Button
                disabled={busy}
                onClick={() => void run(() => startRoute(id), "startSuccess")}
              >
                {t("start")}
              </Button>
            ) : null}
            {route.status === "in_progress" ? (
              <Button
                disabled={busy}
                onClick={() =>
                  void run(() => completeRoute(id), "completeSuccess")
                }
              >
                {t("complete")}
              </Button>
            ) : null}
            {canManage &&
            ["draft", "published", "in_progress"].includes(route.status) ? (
              <Button
                variant="danger"
                disabled={busy}
                onClick={() => void run(() => cancelRoute(id), "cancelSuccess")}
              >
                {t("cancel")}
              </Button>
            ) : null}
            <Link href="/routes">
              <Button variant="secondary">{tCommon("back")}</Button>
            </Link>
          </>
        }
      />

      {actionError ? (
        <div className="mb-4">
          <ErrorState error={actionError} />
        </div>
      ) : null}
      {success ? (
        <div className="mb-4">
          <Alert tone="success">{success}</Alert>
        </div>
      ) : null}

      <Panel className="mb-4 grid gap-3 p-4 sm:grid-cols-4">
        <div>
          <p className="text-xs text-muted">{t("status")}</p>
          <StatusBadge status={route.status} />
        </div>
        <div>
          <p className="text-xs text-muted">{t("collector")}</p>
          <p>{route.collector?.user?.name || `#${route.collector_id}`}</p>
        </div>
        <div>
          <p className="text-xs text-muted">{t("currentStop")}</p>
          <p className="font-medium">
            {currentStop
              ? `#${currentStop.sequence} ${currentStop.customer?.contact_name || `Customer #${currentStop.customer_id}`}`
              : "—"}
          </p>
        </div>
        <div>
          <p className="text-xs text-muted">{t("notes")}</p>
          <p>{route.notes || "—"}</p>
        </div>
      </Panel>

      <Panel className="mb-4 grid grid-cols-2 gap-3 p-4 sm:grid-cols-3">
        <div>
          <p className="text-xs text-muted">{t("stops")}</p>
          <p className="text-xl font-semibold">{stops.length}</p>
        </div>
        <div>
          <p className="text-xs text-muted">{t("completedStops")}</p>
          <p className="text-xl font-semibold text-success">{completedCount}</p>
        </div>
        <div>
          <p className="text-xs text-muted">{t("remainingStops")}</p>
          <p className="text-xl font-semibold text-warning">{remainingCount}</p>
        </div>
      </Panel>

      <div className="mb-4">
        <LeafletMap
          markers={markers}
          missingLabel={tMaps("missingLocation")}
          openInMapsLabel={tMaps("openInMaps")}
        />
      </div>

      <Panel>
        {!route.stops?.length ? (
          <EmptyState label={t("noStops")} />
        ) : (
          <ul className="divide-y divide-border">
            {route.stops.map((stop) => {
              const lat = Number(stop.customer?.latitude);
              const lng = Number(stop.customer?.longitude);
              const hasLoc = Number.isFinite(lat) && Number.isFinite(lng);
              return (
                <li
                  key={stop.id}
                  className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm"
                >
                  <div>
                    <p className="font-medium">
                      #{stop.sequence}{" "}
                      {stop.customer?.contact_name ||
                        `Customer #${stop.customer_id}`}
                    </p>
                    <StatusBadge status={stop.status} />
                  </div>
                  {hasLoc ? (
                    <a
                      href={mapsExternalUrl(lat, lng)}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-primary hover:underline"
                    >
                      {t("navigate")}
                    </a>
                  ) : (
                    <span className="text-muted">{t("missingLocation")}</span>
                  )}
                </li>
              );
            })}
          </ul>
        )}
      </Panel>
    </div>
  );
}
