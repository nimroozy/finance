"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { getInventoryDashboard, type InventoryDashboard } from "@/lib/inventory";
import {
  BranchPicker,
  ErrorWorkspace,
  MetricCard,
  WorkspaceHeader,
} from "@/components/ops";
import { Button } from "@/components/ui/button";
import { LoadingState } from "@/components/ui/layout";

export default function InventoryDashboardPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const [data, setData] = useState<InventoryDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [branchId, setBranchId] = useState("");
  const [branchLabel, setBranchLabel] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getInventoryDashboard(branchId || undefined);
      setData(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [branchId, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-4" data-testid="inventory-dashboard">
      <WorkspaceHeader
        title={t("dashboardTitle")}
        subtitle={t("dashboardSubtitle")}
        actions={
          <>
            <Link href="/inventory/products">
              <Button variant="secondary">{t("productsTitle")}</Button>
            </Link>
            <Link href="/inventory/receiving">
              <Button>{t("receivingTitle")}</Button>
            </Link>
          </>
        }
      />
      <div className="max-w-sm">
        <BranchPicker
          value={branchId || null}
          selectedLabel={branchLabel}
          onChange={(opt) => {
            setBranchId(opt ? String(opt.id) : "");
            setBranchLabel(opt?.label ?? null);
          }}
        />
      </div>
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {data ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <MetricCard label={t("metrics.onHand")} value={data.on_hand} tone="primary" href="/inventory/stock" />
          <MetricCard label={t("metrics.available")} value={data.available} tone="success" href="/inventory/stock" />
          <MetricCard label={t("metrics.reserved")} value={data.reserved} tone="warning" href="/inventory/reservations" />
          <MetricCard label={t("metrics.inTransit")} value={data.in_transit} tone="warning" href="/inventory/transfers" />
          <MetricCard label={t("metrics.lowStock")} value={data.low_stock_products} tone="danger" href="/inventory/products" />
          <MetricCard label={t("metrics.openReservations")} value={data.open_reservations} href="/inventory/reservations" />
          <MetricCard label={t("metrics.openTransfers")} value={data.open_transfers} href="/inventory/transfers" />
          <MetricCard label={t("metrics.equipmentInStock")} value={data.equipment_in_stock} href="/inventory/equipment" />
          <MetricCard label={t("metrics.equipmentInstalled")} value={data.equipment_installed} href="/customer-equipment" />
        </div>
      ) : null}
    </div>
  );
}
