"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  fulfillReservation,
  getReservation,
  releaseReservation,
  reserveReservation,
  type Reservation} from "@/lib/inventory";
import { useAuthStore } from "@/store/auth-store";
import {
  ErrorWorkspace,
  StatusActionMenu,
  WorkspaceHeader,
  type StatusTransition} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

export default function ReservationDetailPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const params = useParams();
  const id = String(params.id);
  const canManage = useAuthStore((s) => s.hasPermission("inventory.reservations.manage"));

  const [item, setItem] = useState<Reservation | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getReservation(id);
      setItem(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setItem(null);
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  const transitions: StatusTransition[] = [];
  if (item && canManage) {
    if (item.status === "draft" || item.status === "approved") {
      transitions.push({ id: "reserve", label: t("actions.reserve")});
    }
    if (["approved", "partially_fulfilled", "draft"].includes(item.status)) {
      transitions.push({ id: "fulfill", label: t("actions.fulfill")});
    }
    if (!["fulfilled", "released", "cancelled"].includes(item.status)) {
      transitions.push({ id: "release", label: t("actions.release"), danger: true });
    }
  }

  async function onAction(actionId: string) {
    if (busy) return;
    setBusy(true);
    setError(null);
    try {
      let res;
      if (actionId === "reserve") res = await reserveReservation(id);
      else if (actionId === "fulfill") res = await fulfillReservation(id);
      else res = await releaseReservation(id);
      setItem(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-4" data-testid="reservation-detail">
      <WorkspaceHeader
        title={item?.reservation_number || t("reservationsTitle")}
        subtitle={item ? t("reservationDetailSubtitle") : undefined}
        actions={
          <>
            {transitions.length ? (
              <StatusActionMenu
                transitions={transitions}
                loading={busy}
                onTransition={(actionId) => void onAction(actionId)}
              />
            ) : null}
            <Link href="/inventory/reservations">
              <Button variant="secondary">{t("backToReservations")}</Button>
            </Link>
          </>
        }
      />
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {error && !item ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {item ? (
        <Panel className="space-y-3 p-4">
          <StatusBadge status={item.status} />
          <ul className="divide-y divide-border">
            {(item.items || []).map((line) => (
              <li key={line.id} className="flex justify-between gap-2 py-2 text-sm">
                <span>{line.product?.name_en || `#${line.product_id}`}</span>
                <span className="text-muted">
                  {t("columns.qty")}: {line.qty}
                </span>
              </li>
            ))}
          </ul>
          {canManage && (item.status === "draft" || item.status === "approved") ? (
            <Button
              data-testid="reserve-action"
              className="min-h-12 w-full sm:w-auto"
              disabled={busy}
              onClick={() => void onAction("reserve")}
            >
              {t("actions.reserve")}
            </Button>
          ) : null}
        </Panel>
      ) : null}
    </div>
  );
}
