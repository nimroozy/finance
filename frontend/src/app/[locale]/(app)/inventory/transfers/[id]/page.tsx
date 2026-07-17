"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  approveTransfer,
  closeTransfer,
  dispatchTransfer,
  getTransfer,
  receiveTransfer,
  submitTransfer,
  type Transfer} from "@/lib/inventory";
import { useAuthStore } from "@/store/auth-store";
import {
  ErrorWorkspace,
  StatusActionMenu,
  WorkspaceHeader,
  type StatusTransition} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

export default function TransferDetailPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const params = useParams();
  const id = String(params.id);
  const canManage = useAuthStore((s) =>
    s.hasAnyPermission(["inventory.transfers.manage", "inventory.stock.transfer"]),
  );
  const canApprove = useAuthStore((s) => s.hasPermission("inventory.transfers.approve"));

  const [item, setItem] = useState<Transfer | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getTransfer(id);
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
  if (item) {
    if (canManage && item.status === "draft") {
      transitions.push({ id: "submit", label: t("actions.submit")});
    }
    if (canApprove && item.status === "submitted") {
      transitions.push({ id: "approve", label: t("actions.approve")});
    }
    if (canManage && item.status === "approved") {
      transitions.push({ id: "dispatch", label: t("actions.dispatch")});
    }
    if (canManage && ["dispatched", "in_transit", "approved"].includes(item.status)) {
      transitions.push({ id: "receive", label: t("actions.receive")});
    }
    if (canManage && item.status === "received") {
      transitions.push({ id: "close", label: t("actions.close")});
    }
  }

  async function onAction(actionId: string) {
    if (busy) return;
    setBusy(true);
    setError(null);
    try {
      let res;
      if (actionId === "submit") res = await submitTransfer(id);
      else if (actionId === "approve") res = await approveTransfer(id);
      else if (actionId === "dispatch") res = await dispatchTransfer(id);
      else if (actionId === "receive") res = await receiveTransfer(id);
      else res = await closeTransfer(id);
      setItem(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-4" data-testid="transfer-detail">
      <WorkspaceHeader
        title={item?.transfer_number || t("transfersTitle")}
        subtitle={
          item
            ? `${item.from_location_id} → ${item.to_location_id}`
            : undefined
        }
        actions={
          <>
            {transitions.length ? (
              <StatusActionMenu
                transitions={transitions}
                loading={busy}
                onTransition={(actionId) => void onAction(actionId)}
              />
            ) : null}
            <Link href="/inventory/transfers">
              <Button variant="secondary">{t("backToTransfers")}</Button>
            </Link>
          </>
        }
      />
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {error && !item ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {item ? (
        <Panel className="space-y-4 p-4">
          <StatusBadge status={item.status} />
          <ul className="divide-y divide-border">
            {(item.items || []).map((line) => (
              <li key={line.id} className="flex justify-between gap-2 py-2 text-sm">
                <span>{line.product?.name_en || `#${line.product_id}`}</span>
                <span className="text-muted">
                  {line.qty}
                  {line.qty_received != null ? ` / ${line.qty_received}` : ""}
                </span>
              </li>
            ))}
          </ul>
          {canManage && ["dispatched", "in_transit", "approved"].includes(item.status) ? (
            <Button
              data-testid="transfer-receive"
              className="min-h-14 w-full text-base sm:w-auto"
              disabled={busy}
              onClick={() => void onAction("receive")}
            >
              {t("actions.receiveTransfer")}
            </Button>
          ) : null}
        </Panel>
      ) : null}
    </div>
  );
}
