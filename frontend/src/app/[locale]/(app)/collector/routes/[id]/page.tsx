"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { mapsExternalUrl } from "@/lib/geolocation";
import {
  completeRoute,
  getRoute,
  startRoute,
} from "@/lib/routes";
import type { CollectionRoute } from "@/lib/types";
import { formatDate } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CollectorRouteDetailPage() {
  const t = useTranslations("routesPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const params = useParams();
  const id = Number(params.id);

  const [route, setRoute] = useState<CollectionRoute | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await getRoute(id);
      setRoute(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onStart() {
    setBusy(true);
    try {
      await startRoute(id);
      setSuccess(t("startSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onComplete() {
    setBusy(true);
    try {
      await completeRoute(id);
      setSuccess(t("completeSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (!route) {
    return error ? <Alert>{error}</Alert> : <EmptyState label={tCommon("empty")} />;
  }

  return (
    <div className="space-y-4">
      <PageHeader
        title={route.name}
        subtitle={formatDate(route.route_date, locale)}
      />

      {error ? <Alert>{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}

      <div className="flex items-center gap-2">
        <StatusBadge status={route.status} />
      </div>

      <div className="grid gap-2">
        {route.status === "published" ? (
          <Button className="h-12 w-full" disabled={busy} onClick={() => void onStart()}>
            {t("start")}
          </Button>
        ) : null}
        {route.status === "in_progress" ? (
          <Button
            className="h-12 w-full"
            disabled={busy}
            onClick={() => void onComplete()}
          >
            {t("complete")}
          </Button>
        ) : null}
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
                <li key={stop.id} className="space-y-3 p-4">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <p className="text-base font-semibold">
                        #{stop.sequence}{" "}
                        {stop.customer?.contact_name ||
                          `Customer #${stop.customer_id}`}
                      </p>
                      <StatusBadge status={stop.status} />
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    {hasLoc ? (
                      <a
                        href={mapsExternalUrl(lat, lng)}
                        target="_blank"
                        rel="noopener noreferrer"
                      >
                        <Button className="h-11 w-full" variant="secondary">
                          {t("navigate")}
                        </Button>
                      </a>
                    ) : (
                      <Button className="h-11 w-full" variant="secondary" disabled>
                        {t("missingLocation")}
                      </Button>
                    )}
                    <Link
                      href={`/collector/visits/new?customer_id=${stop.customer_id}${
                        stop.assignment_id
                          ? `&assignment_id=${stop.assignment_id}`
                          : ""
                      }`}
                    >
                      <Button className="h-11 w-full">Visit</Button>
                    </Link>
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </Panel>
    </div>
  );
}
